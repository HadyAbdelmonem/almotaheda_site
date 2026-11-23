<?php

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\OrderException;
use App\Livewire\CheckoutPage;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function checkoutRate(array $attrs = []): ShippingRate
{
    return ShippingRate::create(array_merge([
        'name_ar' => 'القاهرة', 'fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5,
        'is_active' => true, 'sort_order' => 1,
    ], $attrs));
}

function checkoutProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'name' => 'قاطع كهربائي',
        'model_code' => 'CO'.fake()->unique()->numberBetween(10000, 99999),
        'price' => 100, 'cost_price' => 60, 'stock_quantity' => 20, 'track_stock' => true,
    ], $attrs));
}

function fillCheckout($component, ShippingRate $rate): mixed
{
    return $component
        ->set('name', 'أحمد علي')
        ->set('phone', '01001234567')
        ->set('governorateId', $rate->id)
        ->set('city', 'مدينة نصر')
        ->set('address', 'شارع مصطفى النحاس، عمارة 5');
}

test('guest COD checkout succeeds end to end with stock deduction and snapshots', function () {
    $rate = checkoutRate();
    $product = checkoutProduct();
    app(CartService::class)->addItem($product->id, null, '2');

    fillCheckout(Livewire::test(CheckoutPage::class), $rate)
        ->call('submit')
        ->assertRedirectContains('/order/success/ORD-');

    $order = Order::first();
    expect($order)->not->toBeNull()
        ->and($order->order_number)->toMatch('/^ORD-\d{4}-\d{5}$/')
        ->and($order->status)->toBe(OrderStatus::New)
        ->and((string) $order->subtotal)->toBe('200.00')
        ->and((string) $order->shipping_fee)->toBe('50.00')
        ->and((string) $order->total)->toBe('250.00')
        ->and($order->governorate)->toBe('القاهرة')
        ->and($order->user_id)->toBeNull();

    $item = $order->items->first();
    expect($item->name)->toBe('قاطع كهربائي')
        ->and($item->unit_label_ar)->toBe('قطعة')
        ->and((string) $item->unit_price)->toBe('100.00')
        ->and((string) $item->cost_price)->toBe('60.00')
        ->and((string) $item->quantity)->toBe('2.000');

    expect((string) $product->fresh()->stock_quantity)->toBe('18.000')
        ->and((string) $product->fresh()->sales_count)->toBe((string) $product->sales_count) // unchanged until delivery
        ->and(StockMovement::where('order_id', $order->id)->count())->toBe(1)
        ->and($order->statusHistories()->count())->toBe(1)
        ->and(app(CartService::class)->items())->toBe([]);

    // The creating session can view the success page.
    $this->get('/order/success/'.$order->order_number)
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee(__('store.order_success_hint'));
});

test('checkout rejects an invalid egyptian phone and creates nothing', function () {
    $rate = checkoutRate();
    app(CartService::class)->addItem(checkoutProduct()->id, null, '1');

    fillCheckout(Livewire::test(CheckoutPage::class), $rate)
        ->set('phone', '12345')
        ->call('submit')
        ->assertHasErrors('phone');

    expect(Order::count())->toBe(0);
});

test('totals math: tier price, coupon discount and shipping combine exactly', function () {
    $rate = checkoutRate(['fee' => 50]);
    $product = checkoutProduct(['price' => 200, 'stock_quantity' => 50]);
    ProductPriceTier::create(['product_id' => $product->id, 'min_qty' => 10, 'discount_percent' => 10]);
    Coupon::create(['code' => 'SAVE25', 'type' => CouponType::Fixed, 'value' => 25, 'min_order' => 0, 'is_active' => true, 'used_count' => 0]);

    $cart = app(CartService::class);
    $cart->addItem($product->id, null, '10'); // tier unit 180 -> subtotal 1800
    $cart->setCouponCode('SAVE25');

    fillCheckout(Livewire::test(CheckoutPage::class), $rate)->call('submit');

    $order = Order::first();
    expect((string) $order->subtotal)->toBe('1800.00')
        ->and((string) $order->discount_total)->toBe('25.00')
        ->and((string) $order->shipping_fee)->toBe('50.00')
        ->and((string) $order->total)->toBe('1825.00')
        ->and($order->coupon_code)->toBe('SAVE25')
        ->and(Coupon::first()->used_count)->toBe(1);
});

test('free shipping applies exactly at the threshold boundary', function () {
    Setting::updateOrCreate(['key' => 'free_shipping_threshold'], ['value' => 1000]);
    Cache::forget('setting.free_shipping_threshold');
    $rate = checkoutRate(['fee' => 50]);
    $product = checkoutProduct(['price' => 1000]);

    app(CartService::class)->addItem($product->id, null, '1');
    fillCheckout(Livewire::test(CheckoutPage::class), $rate)->call('submit');

    $order = Order::first();
    expect((string) $order->shipping_fee)->toBe('0.00')
        ->and((string) $order->total)->toBe('1000.00');
});

test('one bad line rolls back the whole order: no order, no stock change, no movements', function () {
    $rate = checkoutRate();
    $good = checkoutProduct(['stock_quantity' => 10]);
    $bad = checkoutProduct(['stock_quantity' => 10]);
    $cart = app(CartService::class);
    $cart->addItem($good->id, null, '2');
    $cart->addItem($bad->id, null, '2');

    // Stock disappears after the cart was built (another buyer took it).
    $bad->update(['stock_quantity' => 0]);

    fillCheckout(Livewire::test(CheckoutPage::class), $rate)
        ->call('submit')
        ->assertSet('orderError', __('store.order_error_cart_changed'));

    expect(Order::count())->toBe(0)
        ->and((string) $good->fresh()->stock_quantity)->toBe('10.000')
        ->and(StockMovement::count())->toBe(0);
});

test('order service itself rolls back when a line fails inside the transaction', function () {
    $rate = checkoutRate();
    $good = checkoutProduct(['stock_quantity' => 10]);
    $bad = checkoutProduct(['stock_quantity' => 1]);

    // Bypass the cart page (which pre-checks) and hit the service with
    // a raw session-style payload that exceeds the second line's stock.
    session()->put('cart.items', [
        ['product_id' => $good->id, 'variant_id' => null, 'quantity' => '2'],
        ['product_id' => $bad->id, 'variant_id' => null, 'quantity' => '5'],
    ]);

    $component = fillCheckout(Livewire::test(CheckoutPage::class), $rate);
    // resolvedLines clamps the bad line to 1 in the preview, so call the
    // service directly to prove transactional rollback.
    try {
        app(OrderService::class)->create(
            customer: ['name' => 'أحمد', 'phone' => '01001234567', 'phone_alt' => null, 'email' => null, 'city' => 'القاهرة', 'address' => 'عنوان', 'notes' => null],
            lines: session('cart.items'),
            couponCode: null,
            shippingRateId: $rate->id,
            paymentMethod: PaymentMethod::Cod,
        );
        $this->fail('Expected OrderException');
    } catch (OrderException $e) {
        expect($e->errorKey)->toBe('order_error_stock');
    }

    expect(Order::count())->toBe(0)
        ->and((string) $good->fresh()->stock_quantity)->toBe('10.000')
        ->and(StockMovement::count())->toBe(0);
});

test('fractional meter order keeps quantity and unit snapshots', function () {
    $rate = checkoutRate();
    $cable = checkoutProduct(['unit_code' => 'meter', 'quantity_step' => '0.25', 'price' => 40, 'stock_quantity' => 100]);
    app(CartService::class)->addItem($cable->id, null, '2.5');

    fillCheckout(Livewire::test(CheckoutPage::class), $rate)->call('submit');

    $order = Order::first();
    $item = $order->items->first();
    expect((string) $item->quantity)->toBe('2.500')
        ->and($item->unit_label_ar)->toBe('متر')
        ->and((string) $item->line_total)->toBe('100.00')
        ->and((string) $cable->fresh()->stock_quantity)->toBe('97.500');
});

test('duplicate submit with the same checkout token creates exactly one order', function () {
    $rate = checkoutRate();
    $product = checkoutProduct();
    app(CartService::class)->addItem($product->id, null, '1');

    $component = fillCheckout(Livewire::test(CheckoutPage::class), $rate);
    $component->call('submit');
    $component->call('submit'); // double-click / retry replay

    expect(Order::count())->toBe(1)
        ->and((string) $product->fresh()->stock_quantity)->toBe('19.000');
});

test('success page is forbidden for another session but allowed for the owning user', function () {
    $rate = checkoutRate();
    $product = checkoutProduct();
    $user = User::factory()->create();

    $order = app(OrderService::class)->create(
        customer: ['name' => 'صاحب الطلب', 'phone' => '01001234567', 'phone_alt' => null, 'email' => null, 'city' => 'القاهرة', 'address' => 'عنوان', 'notes' => null],
        lines: [['product_id' => $product->id, 'variant_id' => null, 'quantity' => '1']],
        couponCode: null,
        shippingRateId: $rate->id,
        paymentMethod: PaymentMethod::Cod,
        userId: $user->id,
    );

    // A different session (no creator flag, not the owner) is refused.
    $this->get('/order/success/'.$order->order_number)->assertForbidden();

    // The owning user can view it.
    $this->actingAs($user)->get('/order/success/'.$order->order_number)->assertOk();
});

test('coupon with usage limit 1 works once and blocks the second order', function () {
    $rate = checkoutRate();
    $product = checkoutProduct();
    Coupon::create(['code' => 'ONCE', 'type' => CouponType::Percent, 'value' => 10, 'min_order' => 0, 'is_active' => true, 'used_count' => 0, 'usage_limit' => 1]);

    $cart = app(CartService::class);
    $cart->addItem($product->id, null, '1');
    $cart->setCouponCode('ONCE');
    fillCheckout(Livewire::test(CheckoutPage::class), $rate)->call('submit');
    expect(Order::count())->toBe(1)->and(Coupon::first()->used_count)->toBe(1);

    // Second buyer with the same coupon: rejected inside the order flow.
    // (A genuinely different session: the idempotency token is per
    // checkout session, so it must be reset here — reusing the first
    // buyer's token would correctly replay their success page instead.)
    session()->forget('checkout.completed');
    session()->put('checkout.token', (string) Str::uuid());
    $cart->addItem($product->id, null, '1');
    $cart->setCouponCode('ONCE');
    fillCheckout(Livewire::test(CheckoutPage::class), $rate)
        ->call('submit')
        ->assertSet('orderError', __('store.coupon_exhausted'));
    expect(Order::count())->toBe(1);
});

test('bank transfer checkout shows details and creates a pending bank order', function () {
    Setting::updateOrCreate(['key' => 'bank_transfer_details'], ['value' => 'حساب بنكي رقم 123456']);
    Cache::forget('setting.bank_transfer_details');
    $rate = checkoutRate();
    app(CartService::class)->addItem(checkoutProduct()->id, null, '1');

    fillCheckout(Livewire::test(CheckoutPage::class), $rate)
        ->set('paymentMethod', 'bank_transfer')
        ->assertSee('حساب بنكي رقم 123456')
        ->call('submit');

    expect(Order::first()->payment_method->value)->toBe('bank_transfer');
});

test('card method stays hidden while disabled in settings', function () {
    checkoutRate();
    app(CartService::class)->addItem(checkoutProduct()->id, null, '1');

    Livewire::test(CheckoutPage::class)->assertDontSee('بطاقة ائتمان');
});

test('empty cart redirects checkout to the cart page', function () {
    $this->get('/checkout')->assertRedirect('/cart');
});
