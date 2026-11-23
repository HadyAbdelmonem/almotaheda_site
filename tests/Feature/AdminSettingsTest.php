<?php

use App\Enums\CouponType;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\ShippingRates\Pages\ListShippingRates;
use App\Livewire\CartPage;
use App\Livewire\CheckoutPage;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\CartService;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function p8SetSetting(string $key, mixed $value): void
{
    Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    Cache::forget("setting.{$key}");
}

function p8Admin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

test('settings page saves every key with cache invalidation', function () {
    $this->actingAs(p8Admin());
    p8SetSetting('free_shipping_threshold', 3000);
    expect(setting('free_shipping_threshold'))->toBe(3000);

    Livewire::test(ManageSettings::class)
        ->set('data.free_shipping_threshold', 1500)
        ->set('data.store_name', 'متجر النور')
        ->set('data.whatsapp_number', '201001234567')
        ->set('data.motion_level', 'lite')
        ->call('save');

    expect(setting('free_shipping_threshold'))->toBe(1500)
        ->and(setting('store_name'))->toBe('متجر النور')
        ->and(setting('whatsapp_number'))->toBe('201001234567')
        ->and(setting('motion_level'))->toBe('lite');
});

test('motion_level setting renders into the layout data-motion attribute', function () {
    p8SetSetting('motion_level', 'off');
    $this->get('/')->assertOk()->assertSee('data-motion="off"', false);

    p8SetSetting('motion_level', 'lite');
    $this->get('/')->assertOk()->assertSee('data-motion="lite"', false);
});

test('changing free_shipping_threshold changes cart free-shipping progress and checkout fee', function () {
    $rate = ShippingRate::create(['name_ar' => 'القاهرة', 'fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);
    $product = Product::factory()->create(['price' => 400, 'stock_quantity' => 10, 'track_stock' => true]);
    app(CartService::class)->addItem($product->id, null, '1'); // subtotal 400

    p8SetSetting('free_shipping_threshold', 3000);
    $cart = Livewire::test(CartPage::class);
    expect($cart->viewData('progress'))->toBeLessThan(1.0)
        ->and($cart->viewData('threshold'))->toBe('3000');

    // Lower the threshold below the subtotal: shipping becomes free everywhere.
    p8SetSetting('free_shipping_threshold', 300);
    $cart = Livewire::test(CartPage::class);
    expect($cart->viewData('progress'))->toBe(1.0);

    Livewire::test(CheckoutPage::class)
        ->set('name', 'أحمد')->set('phone', '01001234567')->set('governorateId', $rate->id)
        ->set('city', 'مدينة نصر')->set('address', 'شارع 1')
        ->call('submit')
        ->assertRedirectContains('/order/success/');
    expect((string) Order::first()->shipping_fee)->toBe('0.00');
});

test('disabling a governorate removes it from checkout and blocks its use', function () {
    $rate = ShippingRate::create(['name_ar' => 'القاهرة', 'fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'track_stock' => true]);
    app(CartService::class)->addItem($product->id, null, '1');

    $rate->update(['is_active' => false]);

    $component = Livewire::test(CheckoutPage::class);
    expect($component->viewData('rates'))->toHaveCount(0);

    $component->set('name', 'أحمد')->set('phone', '01001234567')->set('governorateId', $rate->id)
        ->set('city', 'مدينة نصر')->set('address', 'شارع 1')
        ->call('submit');
    expect(Order::count())->toBe(0);
});

test('coupon admin CRUD: create normalizes the code and validation rejects bad percent', function () {
    $this->actingAs(p8Admin());

    Livewire::test(CreateCoupon::class)
        ->fillForm([
            'code' => ' eid20 ',
            'type' => CouponType::Percent->value,
            'value' => 20,
            'min_order' => 100,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Coupon::first()->code)->toBe('EID20');

    Livewire::test(CreateCoupon::class)
        ->fillForm([
            'code' => 'BAD',
            'type' => CouponType::Percent->value,
            'value' => -5,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['value']);

    Livewire::test(ListCoupons::class)->assertOk()->assertCanSeeTableRecords(Coupon::all());
});

test('shipping rates admin lists 27 governorates and fee edits apply to checkout', function () {
    $this->actingAs(p8Admin());
    foreach (range(1, 27) as $i) {
        ShippingRate::create(['name_ar' => 'محافظة '.$i, 'fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => $i]);
    }
    Livewire::test(ListShippingRates::class)->assertOk();
    expect(ShippingRate::count())->toBe(27);

    $rate = ShippingRate::first();
    $rate->update(['fee' => 80]);
    expect(app(ShippingService::class)->rateFor($rate->id)->fee)->toBe('80.00');
});

test('settings-driven storefront: whatsapp float hides when empty and shows when set', function () {
    p8SetSetting('whatsapp_number', '');
    $this->get('/')->assertOk()->assertDontSee('wa.me/');

    p8SetSetting('whatsapp_number', '201001234567');
    $this->get('/')->assertOk()->assertSee('wa.me/201001234567');
});

test('customers resource is read-only and lists only non-admin users', function () {
    $admin = p8Admin();
    $customer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($admin);

    expect(CustomerResource::canCreate())->toBeFalse()
        ->and(CustomerResource::canEdit($customer))->toBeFalse()
        ->and(CustomerResource::canDelete($customer))->toBeFalse();

    Livewire::test(ListCustomers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$admin]);
});
