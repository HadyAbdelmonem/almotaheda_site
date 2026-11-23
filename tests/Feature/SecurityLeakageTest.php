<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function leakageProduct(): Product
{
    return Product::factory()->create([
        'is_active' => true,
        'name' => 'قاطع تسريب اختباري',
        'price' => 250,
        'cost_price' => 777777,
        'stock_quantity' => 10,
    ]);
}

test('storefront pages and JSON-LD never leak cost price', function () {
    $product = leakageProduct();

    foreach ([
        route('products.show', $product->slug),
        route('categories.show', $product->category->slug),
        url('/search?q='.urlencode('قاطع')),
        url('/'),
    ] as $url) {
        $response = $this->get($url)->assertOk();
        $response->assertDontSee('777777', false)
            ->assertDontSee('cost_price', false);
    }
});

test('order success page never leaks admin notes, payment proof paths or provider payloads', function () {
    config(['paymob.hmac_secret' => 'super-secret-hmac-value', 'paymob.api_key' => 'super-secret-api-key']);
    $order = Order::create([
        'customer_name' => 'أحمد علي', 'phone' => '01001234567',
        'governorate' => 'القاهرة', 'city' => 'مدينة نصر', 'address' => 'شارع 1',
        'payment_method' => 'bank_transfer', 'payment_status' => 'pending', 'status' => 'new',
        'subtotal' => 200, 'discount_total' => 0, 'shipping_fee' => 50, 'total' => 250,
        'stock_restoration_status' => 'not_required', 'placed_at' => now(),
        'admin_notes' => 'ملاحظة داخلية سرية جدًا',
        'payment_proof' => 'receipts/ORD-SECRET/hidden-receipt.jpg',
    ]);
    $order->paymentTransactions()->create([
        'provider' => 'paymob', 'merchant_reference' => 'PAY-LEAK-1', 'amount' => 250,
        'currency' => 'EGP', 'status' => 'pending', 'is_success' => false,
        'payload_redacted' => ['note' => 'payload-marker-secret'],
        'reconciliation_status' => 'not_required',
    ]);

    $response = $this->withSession(['order.success.'.$order->order_number => true])
        ->get(route('order.success', $order->order_number))->assertOk();

    $response->assertDontSee('ملاحظة داخلية سرية جدًا', false)
        ->assertDontSee('hidden-receipt.jpg', false)
        ->assertDontSee('payload-marker-secret', false)
        ->assertDontSee('super-secret-hmac-value', false)
        ->assertDontSee('super-secret-api-key', false);
});

test('registration cannot mass-assign is_admin', function () {
    $user = User::create([
        'name' => 'مهاجم', 'email' => 'attacker@example.com',
        'phone' => '01001234567', 'password' => 'password123',
        'is_admin' => true, // must be ignored: not fillable (Phase 11)
    ]);

    expect($user->is_admin)->toBeFalsy()
        ->and($user->canAccessPanel(new Panel))->toBeFalse();
});

test('purge-demo command removes products and orders but keeps structure', function () {
    $category = Category::factory()->create(['is_active' => true]);
    leakageProduct();
    Order::create([
        'customer_name' => 'أحمد علي', 'phone' => '01001234567',
        'governorate' => 'القاهرة', 'city' => 'مدينة نصر', 'address' => 'شارع 1',
        'payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'new',
        'subtotal' => 200, 'discount_total' => 0, 'shipping_fee' => 50, 'total' => 250,
        'stock_restoration_status' => 'not_required', 'placed_at' => now(),
    ]);

    $this->artisan('store:purge-demo')->assertExitCode(0);

    expect(Product::withTrashed()->count())->toBe(0)
        ->and(Order::count())->toBe(0)
        ->and(Category::count())->toBeGreaterThan(0); // structure kept
});
