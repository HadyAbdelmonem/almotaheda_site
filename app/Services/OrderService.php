<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementReason;
use App\Enums\StockRestorationStatus;
use App\Exceptions\OrderException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Money;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Order lifecycle (spec §4.7). This is the SOLE service allowed to
 * change order status and to resolve stock disposition.
 *
 * Full transactional order creation (cart -> order with stock locking
 * and coupon locking) is implemented in Phase 5B; this Phase 1B version
 * provides the enforced status-transition graph, stock-disposition
 * resolution, coupon release and sales_count rules.
 */
class OrderService
{
    /** Allowed transitions: from => [to, ...] (spec §4.7). */
    public const TRANSITIONS = [
        'new' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned', 'cancelled'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    public function __construct(
        private readonly StockService $stock,
        private readonly QuantityService $quantities,
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
        private readonly ShippingService $shipping,
    ) {}

    /**
     * Create an order from raw cart lines in ONE transaction (spec §4,
     * Phase 5B). Lock order is deterministic: stock rows (products,
     * then variants) by ascending id, then the coupon row. Everything
     * is revalidated against the locked rows — prices, sale/tier rules,
     * quantity rules, stock, coupon and shipping — and any failure
     * aborts the whole transaction with nothing saved.
     *
     * @param  array{name: string, phone: string, phone_alt: ?string, email: ?string, city: string, address: string, notes: ?string}  $customer
     * @param  array<int, array{product_id: int, variant_id: ?int, quantity: string}>  $lines
     *
     * @throws OrderException
     */
    public function create(array $customer, array $lines, ?string $couponCode, int $shippingRateId, PaymentMethod $paymentMethod, ?int $userId = null): Order
    {
        if ($lines === []) {
            throw new OrderException('order_error_empty_cart');
        }

        $created = DB::transaction(function () use ($customer, $lines, $couponCode, $shippingRateId, $paymentMethod, $userId) {
            // 1) Lock stock rows first, in ascending id order.
            $productIds = array_values(array_unique(array_column($lines, 'product_id')));
            sort($productIds);
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with(['priceTiers'])
                ->get()
                ->keyBy('id');

            $variantIds = array_values(array_filter(array_column($lines, 'variant_id')));
            sort($variantIds);
            $variants = $variantIds === [] ? collect() : ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 2) Revalidate every line against the locked rows.
            $prepared = [];
            $subtotal = '0.00';
            foreach ($lines as $line) {
                $product = $products->get($line['product_id']);
                if ($product === null || ! $product->is_active) {
                    throw new OrderException('order_error_unavailable', $product?->name);
                }

                $variant = null;
                if ($line['variant_id'] !== null) {
                    $variant = $variants->get($line['variant_id']);
                    if ($variant === null || ! $variant->is_active || (int) $variant->product_id !== (int) $product->id) {
                        throw new OrderException('order_error_unavailable', $product->name);
                    }
                } elseif ($product->has_variants) {
                    throw new OrderException('order_error_unavailable', $product->name);
                }

                try {
                    $quantity = $this->quantities->validate(
                        (string) $line['quantity'],
                        (string) $product->min_order_quantity,
                        (string) $product->quantity_step,
                        $product->max_order_quantity !== null ? (string) $product->max_order_quantity : null,
                    );
                } catch (InvalidArgumentException) {
                    throw new OrderException('order_error_quantity', $product->name);
                }

                if ($product->track_stock) {
                    $available = $this->stock->available($product, $variant);
                    if ($this->quantities->compare($available, $quantity) < 0) {
                        throw new OrderException('order_error_stock', $product->name);
                    }
                }

                $pricing = $this->pricing->unitPrice($product, $variant, $quantity);
                $lineTotal = $this->pricing->lineTotal($pricing['unit_price'], $quantity);
                $subtotal = Money::add($subtotal, $lineTotal);

                $prepared[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $pricing['unit_price'],
                    'line_total' => $lineTotal,
                ];
            }

            // 3) Lock the coupon row AFTER the stock rows (spec §4.3).
            $coupon = null;
            $discount = '0.00';
            if ($couponCode !== null && trim($couponCode) !== '') {
                $coupon = Coupon::query()
                    ->where('code', $this->coupons->normalizeCode($couponCode))
                    ->lockForUpdate()
                    ->first();
                $error = $this->coupons->validate($coupon, $subtotal);
                if ($error !== null) {
                    throw new OrderException('coupon_'.$error);
                }
                $discount = $this->coupons->discount($coupon, $subtotal);
            }

            // 4) Shipping from the active rate; free-shipping rule §4.4.
            $rate = $this->shipping->rateFor($shippingRateId);
            if ($rate === null) {
                throw new OrderException('order_error_governorate');
            }
            $shippingFee = $this->shipping->fee($rate, $subtotal, $discount, $coupon) ?? '0.00';
            $total = Money::add(Money::sub($subtotal, $discount), $shippingFee);

            // 5) Create the order; the model hook assigns the order
            // number after insert, inside this same transaction.
            $order = Order::create([
                'user_id' => $userId,
                'customer_name' => $customer['name'],
                'phone' => $customer['phone'],
                'phone_alt' => $customer['phone_alt'] ?? null,
                'email' => $customer['email'] ?? null,
                'governorate' => $rate->name_ar,
                'city' => $customer['city'],
                'address' => $customer['address'],
                'notes' => $customer['notes'] ?? null,
                'payment_method' => $paymentMethod,
                'payment_status' => PaymentStatus::Pending,
                'status' => OrderStatus::New,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'coupon_code' => $coupon?->code,
                'stock_restoration_status' => StockRestorationStatus::NotRequired,
                'placed_at' => now(),
            ]);

            // 6) Immutable item snapshots + stock deduction + movements.
            foreach ($prepared as $line) {
                $product = $line['product'];
                $variant = $line['variant'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->model_code,
                    'unit_code' => $product->unit_code,
                    'unit_label_ar' => unit_label($product->unit_code),
                    'unit_price' => $line['unit_price'],
                    'cost_price' => (string) ($variant?->cost_price ?? $product->cost_price ?? 0),
                    'quantity' => $this->quantities->toStorage($line['quantity']),
                    'line_total' => $line['line_total'],
                ]);
                $this->stock->deduct($product, $variant, $line['quantity'], $order);
            }

            // 7) Coupon use is counted exactly once, in this transaction.
            if ($coupon !== null) {
                $coupon->increment('used_count');
            }

            // 8) First status history row. sales_count is NOT touched
            // here — it moves only on delivery (spec §4.7).
            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => OrderStatus::New,
                'changed_by' => $userId,
                'note' => null,
            ]);

            return $order->refresh();
        });

        $this->notifyAdminsOfNewOrder($created);

        return $created;
    }

    /**
     * Filament database notification to every admin on a new order
     * (spec §7, Phase 6). Best-effort: a notification failure must
     * never fail checkout, and mail is not sent from here.
     */
    private function notifyAdminsOfNewOrder(Order $order): void
    {
        try {
            $admins = User::query()->where('is_admin', true)->get();
            foreach ($admins as $admin) {
                Notification::make()
                    ->title('طلب جديد '.$order->order_number)
                    ->body('طلب جديد بقيمة '.number_format((float) $order->total).' ج.م من '.$order->customer_name)
                    ->icon('heroicon-o-shopping-bag')
                    ->sendToDatabase($admin);
            }
        } catch (\Throwable) {
            // Intentionally ignored: notifications are best-effort.
        }
    }

    /**
     * Manually mark a BANK-TRANSFER order as paid, only after the
     * admin independently verified the receipt (spec §4.8). COD is
     * paid only on delivery and card only via verified callback —
     * both are rejected here. Requires an audit note; the decision is
     * written into the status history in the same transaction.
     */
    public function markBankTransferPaid(Order $order, User $actor, string $note): Order
    {
        if (trim($note) === '') {
            throw new InvalidArgumentException('An audit note is required to mark a bank transfer as paid.');
        }

        return DB::transaction(function () use ($order, $actor, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payment_method !== PaymentMethod::BankTransfer) {
                throw new InvalidArgumentException('Only bank-transfer orders can be marked paid manually.');
            }
            if ($locked->payment_status === PaymentStatus::Paid) {
                return $locked; // Idempotent no-op.
            }
            if (in_array($locked->status, [OrderStatus::Cancelled, OrderStatus::Returned], true)) {
                throw new InvalidArgumentException('A cancelled or returned order cannot be marked paid.');
            }

            $locked->payment_status = PaymentStatus::Paid;
            $locked->payment_verified_at = now();
            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $locked->status,
                'to_status' => $locked->status,
                'changed_by' => $actor->id,
                'note' => 'bank_transfer_marked_paid: '.$note,
            ]);

            return $locked->refresh();
        });
    }

    public function canTransition(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * Change the order status, enforcing the transition graph and all
     * stock / coupon / sales_count side effects, in one transaction.
     */
    public function changeStatus(Order $order, OrderStatus $to, ?User $actor = null, ?string $note = null): Order
    {
        $from = $order->status;
        if (! $this->canTransition($from, $to)) {
            throw new InvalidArgumentException("Transition {$from->value} -> {$to->value} is not allowed.");
        }

        return DB::transaction(function () use ($order, $from, $to, $actor, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== $from) {
                throw new InvalidArgumentException('Order status changed concurrently.');
            }

            if ($to === OrderStatus::Cancelled) {
                $this->handleCancellation($locked, $from);
            }

            if ($to === OrderStatus::Returned) {
                $locked->stock_restoration_status = StockRestorationStatus::PendingInspection;
                $this->reverseSalesCountOnce($locked);
                if ($locked->payment_status === PaymentStatus::Paid) {
                    $locked->payment_reconciliation_required = true;
                }
            }

            if ($to === OrderStatus::Delivered) {
                if ($locked->payment_method->value === 'cod') {
                    $locked->payment_status = PaymentStatus::Paid;
                }
                $this->incrementSalesCountOnce($locked);
            }

            $locked->status = $to;
            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor?->id,
                'note' => $note,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Resolve a pending stock disposition after physical receipt and
     * inspection (spec §4.6). Idempotent: only runs from
     * pending_inspection, requires an admin note and the acting user.
     */
    public function resolveStockDisposition(Order $order, StockRestorationStatus $decision, User $actor, string $note): Order
    {
        if (! in_array($decision, [StockRestorationStatus::Restored, StockRestorationStatus::WrittenOff], true)) {
            throw new InvalidArgumentException('Disposition must be restored or written_off.');
        }
        if (trim($note) === '') {
            throw new InvalidArgumentException('A disposition note is required.');
        }

        return DB::transaction(function () use ($order, $decision, $actor, $note) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->stock_restoration_status !== StockRestorationStatus::PendingInspection) {
                return $locked; // Idempotent no-op.
            }

            if ($decision === StockRestorationStatus::Restored) {
                foreach ($locked->items as $item) {
                    if ($item->product !== null && $item->product->track_stock) {
                        $this->stock->restore($item->product, $item->variant, (string) $item->quantity, $locked, StockMovementReason::OrderReturned);
                    }
                }
                $locked->stock_restored_at = now();
            }

            $locked->stock_restoration_status = $decision;
            $locked->stock_disposition_note = $note;
            $locked->stock_disposition_by = $actor->id;
            $locked->save();

            return $locked->refresh();
        });
    }

    private function handleCancellation(Order $order, OrderStatus $from): void
    {
        $beforeShipping = in_array($from, [OrderStatus::New, OrderStatus::Confirmed, OrderStatus::Processing], true);

        if ($beforeShipping && $order->stock_restoration_status !== StockRestorationStatus::Restored) {
            foreach ($order->items as $item) {
                if ($item->product !== null && $item->product->track_stock) {
                    $this->stock->restore($item->product, $item->variant, (string) $item->quantity, $order);
                }
            }
            $order->stock_restoration_status = StockRestorationStatus::Restored;
            $order->stock_restored_at = now();
        } elseif (! $beforeShipping) {
            $order->stock_restoration_status = StockRestorationStatus::PendingInspection;
        }

        // Release the coupon use exactly once (cancellations only, not returns).
        if ($order->coupon_code !== null && $order->coupon_released_at === null) {
            $coupon = Coupon::query()
                ->where('code', $order->coupon_code)
                ->lockForUpdate()
                ->first();
            if ($coupon !== null && $coupon->used_count > 0) {
                $coupon->decrement('used_count');
            }
            $order->coupon_released_at = now();
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            $order->payment_reconciliation_required = true;
        }
    }

    private function incrementSalesCountOnce(Order $order): void
    {
        // Guarded by the status graph: delivered is reachable only once
        // from shipped, and sales_reversed_at tracks returns.
        $quantities = app(QuantityService::class);
        foreach ($order->items as $item) {
            if ($item->product !== null) {
                $item->product->update([
                    'sales_count' => $quantities->add((string) $item->product->sales_count, (string) $item->quantity),
                ]);
            }
        }
    }

    private function reverseSalesCountOnce(Order $order): void
    {
        if ($order->sales_reversed_at !== null) {
            return;
        }
        foreach ($order->items as $item) {
            if ($item->product !== null) {
                $current = (string) $item->product->sales_count;
                // Never below zero: floor before subtracting (QuantityService
                // rejects negative operands).
                $new = app(QuantityService::class)->compare($current, (string) $item->quantity) < 0
                    ? '0'
                    : app(QuantityService::class)->sub($current, (string) $item->quantity);
                $item->product->update(['sales_count' => $new]);
            }
        }
        $order->sales_reversed_at = now();
    }
}
