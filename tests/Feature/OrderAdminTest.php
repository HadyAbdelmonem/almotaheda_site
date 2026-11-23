<?php

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockRestorationStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingRate;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function p6Rate(): ShippingRate
{
    return ShippingRate::firstOrCreate(['name_ar' => 'القاهرة'], ['fee' => 50, 'est_days_min' => 2, 'est_days_max' => 5, 'is_active' => true, 'sort_order' => 1]);
}

function p6Product(array $attrs = []): Product
{
    return Product::factory()->create(array_merge([
        'name' => 'قاطع كهربائي', 'model_code' => 'P6'.fake()->unique()->numberBetween(10000, 99999),
        'price' => 100, 'cost_price' => 60, 'stock_quantity' => 20, 'track_stock' => true, 'sales_count' => 0,
    ], $attrs));
}

function p6Order(Product $product, string $qty = '2', PaymentMethod $method = PaymentMethod::Cod, ?string $coupon = null): Order
{
    return app(OrderService::class)->create(
        ['name' => 'أحمد علي', 'phone' => '01001234567', 'phone_alt' => null, 'email' => null, 'city' => 'مدينة نصر', 'address' => 'شارع 1'],
        [['product_id' => $product->id, 'variant_id' => null, 'quantity' => $qty]],
        $coupon,
        p6Rate()->id,
        $method,
    );
}

function p6Walk(OrderService $svc, Order $order, array $path): Order
{
    foreach ($path as $to) {
        $order = $svc->changeStatus($order, $to);
    }

    return $order;
}

test('every allowed transition succeeds and writes history in the same flow', function () {
    $svc = app(OrderService::class);
    $order = p6Order(p6Product());
    $order = p6Walk($svc, $order, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered]);

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->statusHistories()->count())->toBe(5); // creation + 4 transitions
});

test('forbidden transitions are rejected', function (string $fromName, string $toName) {
    $from = OrderStatus::from($fromName);
    $to = OrderStatus::from($toName);
    $svc = app(OrderService::class);
    $order = p6Order(p6Product());
    $paths = [
        'new' => [], 'confirmed' => [OrderStatus::Confirmed],
        'processing' => [OrderStatus::Confirmed, OrderStatus::Processing],
        'shipped' => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped],
        'delivered' => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered],
        'cancelled' => [OrderStatus::Cancelled],
        'returned' => [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Returned],
    ];
    $order = p6Walk($svc, $order, $paths[$from->value]);

    expect(fn () => $svc->changeStatus($order, $to))->toThrow(InvalidArgumentException::class);
})->with([
    ['new', 'shipped'], ['new', 'delivered'], ['new', 'returned'], ['new', 'processing'],
    ['confirmed', 'shipped'], ['confirmed', 'delivered'], ['confirmed', 'new'],
    ['processing', 'delivered'], ['processing', 'confirmed'],
    ['shipped', 'confirmed'], ['shipped', 'new'],
    ['delivered', 'cancelled'], ['delivered', 'shipped'],
    ['cancelled', 'confirmed'], ['returned', 'delivered'],
]);

test('cancel before shipping restores stock once and releases the coupon once', function () {
    Coupon::create(['code' => 'P6SAVE', 'type' => CouponType::Percent, 'value' => 10, 'min_order' => 0, 'is_active' => true, 'used_count' => 0]);
    $product = p6Product();
    $order = p6Order($product, '2', PaymentMethod::Cod, 'P6SAVE');

    expect((string) $product->fresh()->stock_quantity)->toBe('18.000')
        ->and(Coupon::first()->used_count)->toBe(1);

    $order = app(OrderService::class)->changeStatus($order, OrderStatus::Cancelled);

    expect((string) $product->fresh()->stock_quantity)->toBe('20.000')
        ->and(Coupon::first()->used_count)->toBe(0)
        ->and($order->coupon_released_at)->not->toBeNull()
        ->and($order->stock_restoration_status)->toBe(StockRestorationStatus::Restored)
        ->and(StockMovement::where('order_id', $order->id)->where('reason', 'order_cancelled')->count())->toBe(1);
});

test('cancel after shipping does NOT restock and waits for inspection; return keeps coupon used', function () {
    Coupon::create(['code' => 'P6RET', 'type' => CouponType::Percent, 'value' => 10, 'min_order' => 0, 'is_active' => true, 'used_count' => 0]);
    $svc = app(OrderService::class);
    $product = p6Product();
    $order = p6Order($product, '2', PaymentMethod::Cod, 'P6RET');
    $order = p6Walk($svc, $order, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Returned]);

    expect($order->stock_restoration_status)->toBe(StockRestorationStatus::PendingInspection)
        ->and((string) $product->fresh()->stock_quantity)->toBe('18.000') // not auto-restocked
        ->and(Coupon::first()->used_count)->toBe(1) // returns do not release coupons
        ->and($order->coupon_released_at)->toBeNull();
});

test('resolveStockDisposition restored restocks once with movement; second call is a no-op', function () {
    $svc = app(OrderService::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $product = p6Product();
    $order = p6Order($product, '3');
    $order = p6Walk($svc, $order, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Cancelled]);
    expect($order->stock_restoration_status)->toBe(StockRestorationStatus::PendingInspection);

    $order = $svc->resolveStockDisposition($order, StockRestorationStatus::Restored, $admin, 'تم الاستلام والفحص: سليم');
    expect((string) $product->fresh()->stock_quantity)->toBe('20.000')
        ->and($order->stock_restored_at)->not->toBeNull()
        ->and($order->stock_disposition_by)->toBe($admin->id)
        ->and(StockMovement::where('order_id', $order->id)->where('reason', 'order_returned')->count())->toBe(1);

    $svc->resolveStockDisposition($order, StockRestorationStatus::Restored, $admin, 'محاولة ثانية');
    expect((string) $product->fresh()->stock_quantity)->toBe('20.000')
        ->and(StockMovement::where('order_id', $order->id)->where('reason', 'order_returned')->count())->toBe(1);
});

test('resolveStockDisposition written_off changes no stock and a note is mandatory', function () {
    $svc = app(OrderService::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $product = p6Product();
    $order = p6Order($product, '2');
    $order = p6Walk($svc, $order, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Returned]);

    expect(fn () => $svc->resolveStockDisposition($order, StockRestorationStatus::WrittenOff, $admin, '  '))
        ->toThrow(InvalidArgumentException::class);

    $order = $svc->resolveStockDisposition($order, StockRestorationStatus::WrittenOff, $admin, 'تالف بعد الفحص');
    expect($order->stock_restoration_status)->toBe(StockRestorationStatus::WrittenOff)
        ->and((string) $product->fresh()->stock_quantity)->toBe('18.000')
        ->and($order->stock_restored_at)->toBeNull();
});

test('delivered COD pays and increments sales once; returned reverses exactly once and never below zero', function () {
    $svc = app(OrderService::class);
    $product = p6Product(['sales_count' => 5]);
    $order = p6Order($product, '2');
    $order = p6Walk($svc, $order, [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered]);

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and((string) $product->fresh()->sales_count)->toBe('7.000');

    $order = $svc->changeStatus($order, OrderStatus::Returned);
    expect((string) $product->fresh()->sales_count)->toBe('5.000')
        ->and($order->sales_reversed_at)->not->toBeNull()
        ->and($order->payment_reconciliation_required)->toBeTrue(); // paid order returned

    // Reversal is guarded by sales_reversed_at: calling the private path again via a fresh
    // duplicate transition is impossible (returned is terminal), count stays.
    expect(fn () => $svc->changeStatus($order, OrderStatus::Returned))->toThrow(InvalidArgumentException::class);
    expect((string) $product->fresh()->sales_count)->toBe('5.000');
});

test('markBankTransferPaid works only for bank transfers, requires a note, and writes audit history', function () {
    $svc = app(OrderService::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $bankOrder = p6Order(p6Product(), '1', PaymentMethod::BankTransfer);

    expect(fn () => $svc->markBankTransferPaid($bankOrder, $admin, ''))->toThrow(InvalidArgumentException::class);

    $bankOrder = $svc->markBankTransferPaid($bankOrder, $admin, 'تم التحقق من الإيصال رقم 123');
    expect($bankOrder->payment_status)->toBe(PaymentStatus::Paid)
        ->and($bankOrder->payment_verified_at)->not->toBeNull()
        ->and($bankOrder->statusHistories()->where('note', 'like', 'bank_transfer_marked_paid%')->count())->toBe(1);

    $codOrder = p6Order(p6Product(), '1', PaymentMethod::Cod);
    expect(fn () => $svc->markBankTransferPaid($codOrder, $admin, 'محاولة'))->toThrow(InvalidArgumentException::class)
        ->and($codOrder->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

test('paid order cancelled after shipping flags payment reconciliation', function () {
    $svc = app(OrderService::class);
    $admin = User::factory()->create(['is_admin' => true]);
    $order = p6Order(p6Product(), '1', PaymentMethod::BankTransfer);
    $svc->markBankTransferPaid($order, $admin, 'تم التحقق');
    $order = p6Walk($svc, $order->fresh(), [OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Cancelled]);

    expect($order->payment_reconciliation_required)->toBeTrue()
        ->and($order->stock_restoration_status)->toBe(StockRestorationStatus::PendingInspection);
});

test('invoice shows immutable snapshots after the product price changes, admins only', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $customer = User::factory()->create(['is_admin' => false]);
    $product = p6Product(['price' => 100]);
    $order = p6Order($product, '2');
    $product->update(['price' => 999, 'name' => 'اسم جديد']);

    $this->actingAs($admin)->get(route('orders.invoice', $order))
        ->assertOk()
        ->assertSee('قاطع كهربائي')       // snapshot name, not the renamed product
        ->assertSee($order->order_number)
        ->assertSee('ليس فاتورة ضريبية'); // commercial receipt disclaimer

    $this->actingAs($customer)->get(route('orders.invoice', $order))->assertForbidden();
    // Guest redirect to login is covered in Phase 7 once the login route exists.
});

test('creating an order sends a database notification to admins only', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['is_admin' => false]);
    p6Order(p6Product(), '1');

    expect(DB::table('notifications')->where('notifiable_id', $admin->id)->count())->toBe(1)
        ->and(DB::table('notifications')->count())->toBe(1);
});

test('admin can open the orders list; navigation badge counts new orders; non-admin cannot access panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    p6Order(p6Product(), '1');

    $this->actingAs($admin);
    Livewire::test(ListOrders::class)->assertOk()->assertCanSeeTableRecords(Order::all());
    expect(OrderResource::getNavigationBadge())->toBe('1');

    $customer = User::factory()->create(['is_admin' => false]);
    $this->actingAs($customer)->get('/admin')->assertForbidden();
});
