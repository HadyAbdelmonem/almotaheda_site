<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Livewire\CheckoutPage;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\User;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PaymobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function paymobConfigure(): void
{
    config([
        'paymob.api_key' => 'test-api-key',
        'paymob.hmac_secret' => 'test-hmac-secret',
        'paymob.integration_id' => '12345',
        'paymob.iframe_id' => '6789',
        'paymob.merchant_id' => '555',
    ]);
}

function paymobProduct(): Product
{
    return Product::factory()->create([
        'name' => 'قاطع كهربائي', 'model_code' => 'PM'.fake()->unique()->numberBetween(10000, 99999),
        'price' => 100, 'cost_price' => 60, 'stock_quantity' => 20, 'track_stock' => true, 'sales_count' => 0,
    ]);
}

function paymobOrderFor(Product $product, PaymentMethod $method = PaymentMethod::Card, ?int $userId = null): Order
{
    $rate = ShippingRate::firstOrCreate(['name_ar' => 'القاهرة'], ['fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);

    return app(OrderService::class)->create(
        ['name' => 'أحمد علي', 'phone' => '01001234567', 'phone_alt' => null, 'email' => null, 'city' => 'مدينة نصر', 'address' => 'شارع 1'],
        [['product_id' => $product->id, 'variant_id' => null, 'quantity' => '2']],
        null,
        $rate->id,
        $method,
        $userId,
    );
}

function paymobAttemptFor(Order $order): PaymentTransaction
{
    return app(PaymentService::class)->createAttempt($order, 'paymob');
}

function paymobObj(PaymentTransaction $txn, array $overrides = []): array
{
    return array_merge([
        'amount_cents' => (int) bcmul((string) $txn->amount, '100', 0),
        'created_at' => '2026-11-23T10:00:00.000000',
        'currency' => 'EGP',
        'error_occured' => false,
        'has_parent_transaction' => false,
        'id' => 123456789,
        'integration_id' => 12345,
        'is_3d_secure' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_refunded' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'order' => ['id' => 98765, 'merchant_order_id' => $txn->merchant_reference],
        'owner' => 555,
        'pending' => false,
        'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
        'success' => true,
        'data' => ['message' => 'Approved'],
    ], $overrides);
}

function paymobSign(array $obj, string $secret = 'test-hmac-secret'): string
{
    $fields = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id',
        'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan', 'source_data.sub_type',
        'source_data.type', 'success'];
    $concat = '';
    foreach ($fields as $field) {
        $value = $obj;
        foreach (explode('.', $field) as $segment) {
            $value = is_array($value) ? ($value[$segment] ?? null) : null;
        }
        $concat .= is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
    }

    return hash_hmac('sha512', $concat, $secret);
}

function paymobPostCallback(array $obj, ?string $hmac = null): TestResponse
{
    return test()->postJson(route('payments.paymob.callback'), [
        'obj' => $obj,
        'hmac' => $hmac ?? paymobSign($obj),
        'type' => 'TRANSACTION',
    ]);
}

test('card is unavailable without config or without the owner setting', function () {
    $service = app(PaymentService::class);
    expect($service->cardAvailable())->toBeFalse();

    Setting::updateOrCreate(['key' => 'payment_card_enabled'], ['value' => true]);
    Cache::forget('setting.payment_card_enabled');
    expect($service->cardAvailable())->toBeFalse(); // Setting on, config missing.

    paymobConfigure();
    expect($service->cardAvailable())->toBeTrue(); // Both present.
});

test('attempt creation follows the classic Accept flow over the Http client', function () {
    paymobConfigure();
    Http::fake([
        '*/auth/tokens' => Http::response(['token' => 'auth-token'], 200),
        '*/ecommerce/orders' => Http::response(['id' => 98765], 200),
        '*/acceptance/payment_keys' => Http::response(['token' => 'pay-token'], 200),
    ]);

    $order = paymobOrderFor(paymobProduct());
    $result = app(PaymobService::class)->createPaymentAttempt($order);

    expect($result['iframe_url'])->toContain('iframes/6789')->toContain('payment_token=pay-token')
        ->and($result['transaction']->payload_redacted['paymob_order_id'] ?? null)->toBe(98765)
        ->and($order->fresh()->payment_expires_at)->not->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'ecommerce/orders')
        && $request['amount_cents'] === 25000
        && $request['merchant_order_id'] === $result['transaction']->merchant_reference);
});

test('valid HMAC callback pays exactly once and duplicate callbacks are no-ops', function () {
    paymobConfigure();
    $product = paymobProduct();
    $order = paymobOrderFor($product);
    $txn = paymobAttemptFor($order);
    expect($product->fresh()->stock_quantity)->toBe('18.000');

    paymobPostCallback(paymobObj($txn))->assertOk();
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($txn->fresh()->status)->toBe(PaymentTransactionStatus::Paid)
        ->and($txn->fresh()->provider_transaction_id)->toBe('123456789')
        // Stored payload is redacted: no card PAN survives.
        ->and(json_encode($txn->fresh()->payload_redacted))->not->toContain('2346');

    paymobPostCallback(paymobObj($txn))->assertOk(); // duplicate
    expect(PaymentTransaction::where('order_id', $order->id)->count())->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe('18.000')
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('invalid HMAC is rejected and nothing changes', function () {
    paymobConfigure();
    $order = paymobOrderFor(paymobProduct());
    $txn = paymobAttemptFor($order);

    paymobPostCallback(paymobObj($txn), paymobSign(paymobObj($txn), 'wrong-secret'))->assertForbidden();
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($txn->fresh()->status)->toBe(PaymentTransactionStatus::Pending);
});

test('wrong amount, wrong currency and unknown reference are rejected', function () {
    paymobConfigure();
    $order = paymobOrderFor(paymobProduct());
    $txn = paymobAttemptFor($order);

    paymobPostCallback(paymobObj($txn, ['amount_cents' => 999]))->assertStatus(422);
    paymobPostCallback(paymobObj($txn, ['currency' => 'USD']))->assertStatus(422);

    $unknown = paymobObj($txn, ['order' => ['id' => 1, 'merchant_order_id' => 'PAY-UNKNOWN-REF']]);
    paymobPostCallback($unknown)->assertStatus(422);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('failed callback marks the transaction failed and the order stays pending', function () {
    paymobConfigure();
    $order = paymobOrderFor(paymobProduct());
    $txn = paymobAttemptFor($order);

    $obj = paymobObj($txn, ['success' => false, 'data' => ['message' => 'Declined']]);
    paymobPostCallback($obj)->assertOk();

    expect($txn->fresh()->status)->toBe(PaymentTransactionStatus::Failed)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::New);
});

test('late success after cancellation is recorded and flagged, never revives or re-stocks', function () {
    paymobConfigure();
    $product = paymobProduct();
    $order = paymobOrderFor($product);
    $txn = paymobAttemptFor($order);

    app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);
    expect($product->fresh()->stock_quantity)->toBe('20.000'); // restored once

    paymobPostCallback(paymobObj($txn))->assertOk();
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_reconciliation_required)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Cancelled) // not revived
        ->and($product->fresh()->stock_quantity)->toBe('20.000'); // not re-deducted/restored again
});

test('a second distinct successful attempt on a paid order is recorded and flagged', function () {
    paymobConfigure();
    $product = paymobProduct();
    $order = paymobOrderFor($product);
    $first = paymobAttemptFor($order);
    paymobPostCallback(paymobObj($first))->assertOk();
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);

    $second = paymobAttemptFor($order->fresh());
    paymobPostCallback(paymobObj($second, ['id' => 999888777]))->assertOk();

    expect($second->fresh()->status)->toBe(PaymentTransactionStatus::Paid)
        ->and($second->fresh()->reconciliation_status)->toBe('pending')
        ->and($order->fresh()->payment_reconciliation_required)->toBeTrue()
        ->and($order->fresh()->total)->toBe('250.00')
        ->and($product->fresh()->stock_quantity)->toBe('18.000');
});

test('expiry command cancels unpaid card orders once and never touches bank orders', function () {
    paymobConfigure();
    $product = paymobProduct();
    $cardOrder = paymobOrderFor($product);
    $txn = paymobAttemptFor($cardOrder);
    $bankOrder = paymobOrderFor($product, PaymentMethod::BankTransfer);

    foreach ([$cardOrder, $bankOrder] as $old) {
        $old->forceFill(['placed_at' => now()->subMinutes(61), 'created_at' => now()->subMinutes(61)])->saveQuietly();
    }
    expect($product->fresh()->stock_quantity)->toBe('16.000');

    $this->artisan('payments:expire-card-orders')->assertExitCode(0);

    $cardOrder->refresh();
    expect($cardOrder->status)->toBe(OrderStatus::Cancelled)
        ->and($cardOrder->payment_status)->toBe(PaymentStatus::Failed)
        ->and($txn->fresh()->status)->toBe(PaymentTransactionStatus::Expired)
        ->and($bankOrder->fresh()->status)->toBe(OrderStatus::New)
        // Only the card order's stock came back (2 units), exactly once.
        ->and($product->fresh()->stock_quantity)->toBe('18.000');

    $this->artisan('payments:expire-card-orders')->assertExitCode(0);
    expect($product->fresh()->stock_quantity)->toBe('18.000'); // idempotent
});

test('checkout with card creates an attempt and redirects to the hosted payment page', function () {
    paymobConfigure();
    Setting::updateOrCreate(['key' => 'payment_card_enabled'], ['value' => true]);
    Cache::forget('setting.payment_card_enabled');
    Http::fake([
        '*/auth/tokens' => Http::response(['token' => 'auth-token'], 200),
        '*/ecommerce/orders' => Http::response(['id' => 98765], 200),
        '*/acceptance/payment_keys' => Http::response(['token' => 'pay-token'], 200),
    ]);
    ShippingRate::firstOrCreate(['name_ar' => 'القاهرة'], ['fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);
    $rate = ShippingRate::where('name_ar', 'القاهرة')->first();
    $product = paymobProduct();

    // Seed the cart through the real service (session-backed) BEFORE
    // the component mounts, as the existing checkout tests do.
    app(CartService::class)->addItem($product->id, null, '1');

    $component = Livewire::test(CheckoutPage::class, [])
        ->set('name', 'أحمد علي')
        ->set('phone', '01001234567')
        ->set('governorateId', $rate->id)
        ->set('city', 'مدينة نصر')
        ->set('address', 'شارع مصطفى النحاس')
        ->set('paymentMethod', 'card')
        ->call('submit');

    $order = Order::first();
    expect($order)->not->toBeNull()
        ->and($order->payment_method)->toBe(PaymentMethod::Card)
        ->and(PaymentTransaction::where('order_id', $order->id)->where('provider', 'paymob')->exists())->toBeTrue();
    $component->assertRedirect(route('payments.paymob.pay', $order->order_number));
});

test('bank receipt upload: guest with matching phone, private storage, status unchanged', function () {
    Storage::fake('local');
    Storage::fake('public');
    $order = paymobOrderFor(paymobProduct(), PaymentMethod::BankTransfer);

    $file = UploadedFile::fake()->image('receipt.jpg');
    $this->post(route('orders.receipt.upload', $order->order_number), [
        'phone' => '01001234567', 'receipt' => $file,
    ])->assertRedirect();

    $order->refresh();
    expect($order->payment_proof)->not->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::Pending); // receipt alone never pays
    Storage::disk('local')->assertExists($order->payment_proof);
    Storage::disk('public')->assertMissing($order->payment_proof);
});

test('bank receipt upload rejects wrong phone and other customers', function () {
    Storage::fake('local');
    $order = paymobOrderFor(paymobProduct(), PaymentMethod::BankTransfer);
    $file = UploadedFile::fake()->image('receipt.jpg');

    $this->post(route('orders.receipt.upload', $order->order_number), [
        'phone' => '01009998877', 'receipt' => $file,
    ])->assertForbidden();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->post(route('orders.receipt.upload', $order->order_number), [
        'receipt' => UploadedFile::fake()->image('receipt2.jpg'),
    ])->assertForbidden();

    expect($order->fresh()->payment_proof)->toBeNull();
});

test('bank receipt is viewable by admins only through the authorized route', function () {
    Storage::fake('local');
    $order = paymobOrderFor(paymobProduct(), PaymentMethod::BankTransfer);
    Storage::disk('local')->put('receipts/test.jpg', 'fake-image-bytes');
    $order->payment_proof = 'receipts/test.jpg';
    $order->save();

    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->get(route('orders.receipt.show', $order))->assertOk();

    $customer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($customer)->get(route('orders.receipt.show', $order))->assertForbidden();
});
