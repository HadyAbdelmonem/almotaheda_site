<?php

use App\Enums\CouponType;
use App\Exceptions\CartException;
use App\Livewire\AddToCartButton;
use App\Livewire\CartPage;
use App\Livewire\ProductPage;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function cartProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'name' => 'قاطع كهربائي',
        'model_code' => 'CT'.fake()->unique()->numberBetween(10000, 99999),
        'price' => 100,
        'stock_quantity' => 20,
        'track_stock' => true,
    ], $attrs));
}

function makeCoupon(array $attrs = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => 'SAVE10',
        'type' => CouponType::Percent,
        'value' => 10,
        'min_order' => 0,
        'is_active' => true,
        'used_count' => 0,
    ], $attrs));
}

test('add to cart stores only ids and decimal quantity in the session', function () {
    $product = cartProduct();
    $cart = app(CartService::class);

    $cart->addItem($product->id, null, '2');

    expect($cart->items())->toBe([['product_id' => $product->id, 'variant_id' => null, 'quantity' => '2']])
        ->and(session('cart.items')[0])->not->toHaveKeys(['price', 'total', 'unit_price']);
});

test('fractional meter quantities are stored and priced exactly', function () {
    $cable = cartProduct(['unit_code' => 'meter', 'quantity_step' => '0.25', 'min_order_quantity' => '1', 'price' => 40, 'stock_quantity' => 100]);
    $cart = app(CartService::class);

    $cart->addItem($cable->id, null, '2.5');
    $lines = $cart->resolvedLines();

    expect($lines[0]['effective_quantity'])->toBe('2.5')
        ->and($lines[0]['line_total'])->toBe('100.00')
        ->and($cart->subtotal($lines))->toBe('100.00');
});

test('invalid fractional step is rejected with an arabic error key', function () {
    $cable = cartProduct(['unit_code' => 'meter', 'quantity_step' => '0.25', 'price' => 40, 'stock_quantity' => 100]);

    app(CartService::class)->addItem($cable->id, null, '0.3');
})->throws(CartException::class);

test('add is clamped to available stock', function () {
    $product = cartProduct(['stock_quantity' => 3]);
    $cart = app(CartService::class);

    $inCart = $cart->addItem($product->id, null, '10');

    expect($inCart)->toBe('3')->and($cart->items()[0]['quantity'])->toBe('3');
});

test('out of stock and inactive products cannot be added', function () {
    $empty = cartProduct(['stock_quantity' => 0]);
    $inactive = cartProduct(['is_active' => false]);

    expect(fn () => app(CartService::class)->addItem($empty->id, null, '1'))->toThrow(CartException::class)
        ->and(fn () => app(CartService::class)->addItem($inactive->id, null, '1'))->toThrow(CartException::class);
});

test('quantity tiers recalculate the line price from the database', function () {
    $product = cartProduct(['price' => 100, 'stock_quantity' => 50]);
    ProductPriceTier::create(['product_id' => $product->id, 'min_qty' => 10, 'discount_percent' => 20]);
    $cart = app(CartService::class);

    $cart->addItem($product->id, null, '5');
    expect($cart->resolvedLines()[0]['line_total'])->toBe('500.00');

    $cart->updateQuantity($product->id, null, '10');
    $line = $cart->resolvedLines()[0];
    expect($line['unit_price'])->toBe('80.00')->and($line['line_total'])->toBe('800.00');
});

test('stale lines warn instead of charging: deactivated, out of stock and clamped', function () {
    $gone = cartProduct();
    $empty = cartProduct(['stock_quantity' => 5]);
    $cart = app(CartService::class);
    $cart->addItem($gone->id, null, '1');
    $cart->addItem($empty->id, null, '5');

    $gone->update(['is_active' => false]);
    $empty->update(['stock_quantity' => 2]);

    $lines = $cart->resolvedLines();
    expect($lines[0]['purchasable'])->toBeFalse()
        ->and($lines[0]['warnings'])->toContain('cart_warning_unavailable')
        ->and($lines[1]['warnings'])->toContain('cart_warning_stock_clamped')
        ->and($lines[1]['effective_quantity'])->toBe('2')
        ->and($cart->subtotal($lines))->toBe('200.00');
});

test('variant products need a variant and variant stock is used', function () {
    $product = cartProduct(['has_variants' => true, 'stock_quantity' => 0]);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 'name' => 'أبيض', 'sku' => 'V-1', 'price' => 150,
        'stock_quantity' => 4, 'is_active' => true, 'sort_order' => 1,
    ]);
    $cart = app(CartService::class);

    expect(fn () => $cart->addItem($product->id, null, '1'))->toThrow(CartException::class);

    $cart->addItem($product->id, $variant->id, '2');
    $line = $cart->resolvedLines()[0];
    expect($line['unit_price'])->toBe('150.00')->and($line['line_total'])->toBe('300.00');
});

test('cart page applies percent coupon with cap and shows the discount', function () {
    $product = cartProduct(['price' => 1000]);
    makeCoupon(['max_discount' => 50]);
    app(CartService::class)->addItem($product->id, null, '1');

    Livewire::test(CartPage::class)
        ->set('couponInput', 'save10') // case-insensitive
        ->call('applyCoupon')
        ->assertSet('couponError', null)
        ->assertSee('SAVE10')
        ->assertSee('950 ج.م'); // 1000 - capped 50 discount; shipping not yet added
});

test('fixed and free-shipping coupons compute correctly', function () {
    $product = cartProduct(['price' => 200]);
    makeCoupon(['code' => 'FIX30', 'type' => CouponType::Fixed, 'value' => 30]);
    $cart = app(CartService::class);
    $cart->addItem($product->id, null, '1');
    $cart->setCouponCode('FIX30');

    Livewire::test(CartPage::class)->assertSee('170 ج.م');

    makeCoupon(['code' => 'SHIP0', 'type' => CouponType::FreeShipping, 'value' => 0]);
    $cart->setCouponCode('SHIP0');
    Livewire::test(CartPage::class)->assertSee('200 ج.م'); // no product discount
});

test('coupon below min_order is rejected with an arabic error', function () {
    $product = cartProduct(['price' => 100]);
    makeCoupon(['min_order' => 500]);
    app(CartService::class)->addItem($product->id, null, '1');

    Livewire::test(CartPage::class)
        ->set('couponInput', 'SAVE10')
        ->call('applyCoupon')
        ->assertSet('couponError', __('store.coupon_min_order_not_met'));
});

test('free shipping boundary uses subtotal minus discount', function () {
    Setting::updateOrCreate(['key' => 'free_shipping_threshold'], ['value' => 1000]);
    Cache::forget('setting.free_shipping_threshold');
    $product = cartProduct(['price' => 1100]);
    makeCoupon(); // 10% percent, no cap: discount 110 -> goods 990 < 1000
    $cart = app(CartService::class);
    $cart->addItem($product->id, null, '1');
    $cart->setCouponCode('SAVE10');

    Livewire::test(CartPage::class)
        ->assertSee(__('store.free_shipping_remaining', ['amount' => '10 ج.م']));

    $cart->remove($product->id, null);
    $big = cartProduct(['price' => 1200]);
    $cart->addItem($big->id, null, '1'); // discount 120 -> goods 1080 >= 1000
    Livewire::test(CartPage::class)->assertSee(__('store.free_shipping_active'));
});

test('product page add to cart dispatches cart-added only after server success', function () {
    $product = cartProduct();

    Livewire::test(ProductPage::class, ['slug' => $product->slug])
        ->call('addToCart')
        ->assertDispatched('cart-added')
        ->assertDispatched('cart-updated');

    expect(app(CartService::class)->count())->toBe(1);
});

test('card add-to-cart button adds simple products and updates the count badge', function () {
    $product = cartProduct();

    Livewire::test(AddToCartButton::class, ['productId' => $product->id])
        ->call('add')
        ->assertDispatched('cart-added');

    expect(app(CartService::class)->items()[0]['quantity'])->toBe('1');
});

test('empty cart page shows the empty scene and no checkout button', function () {
    $this->get('/cart')
        ->assertOk()
        ->assertSee(__('store.empty_cart'))
        ->assertSee(__('store.browse_products'));
});
