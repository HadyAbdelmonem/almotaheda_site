<?php

namespace App\Services;

use App\Enums\StockMovementReason;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

/**
 * Stock locking, deduction, restoration and movements (spec §4.6).
 * All methods must be called inside a DB transaction by the caller
 * (OrderService). Rows are locked with lockForUpdate in id order.
 */
class StockService
{
    public function __construct(private readonly QuantityService $quantities) {}

    /** Current tracked stock for a product or one of its variants. */
    public function available(Product $product, ?ProductVariant $variant): string
    {
        return $this->quantities->normalize((string) ($variant !== null ? $variant->stock_quantity : $product->stock_quantity));
    }

    /**
     * Deduct a decimal quantity. Throws RuntimeException when insufficient.
     * Writes a stock_movements row.
     */
    public function deduct(Product $product, ?ProductVariant $variant, string $quantity, ?Order $order = null, StockMovementReason $reason = StockMovementReason::OrderPlaced): void
    {
        if (! $product->track_stock) {
            return;
        }
        $target = $variant ?? $product;
        $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());
        $current = $this->quantities->normalize((string) $locked->stock_quantity);
        if ($this->quantities->compare($current, $quantity) < 0) {
            throw new \RuntimeException("Insufficient stock for product {$product->id}.");
        }
        $locked->stock_quantity = $this->quantities->sub($current, $quantity);
        $locked->save();
        $this->recordMovement($product, $variant, '-'.$this->quantities->normalize($quantity), $reason, $order);
    }

    /** Restore a decimal quantity and write a movement. */
    public function restore(Product $product, ?ProductVariant $variant, string $quantity, ?Order $order = null, StockMovementReason $reason = StockMovementReason::OrderCancelled): void
    {
        if (! $product->track_stock) {
            return;
        }
        $target = $variant ?? $product;
        $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());
        $locked->stock_quantity = $this->quantities->add((string) $locked->stock_quantity, $quantity);
        $locked->save();
        $this->recordMovement($product, $variant, $this->quantities->normalize($quantity), $reason, $order);
    }

    /** Manual admin adjustment (may be negative, never below zero stock). */
    public function adjust(Product $product, ?ProductVariant $variant, string $change, string $note): void
    {
        $target = $variant ?? $product;
        $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());
        $new = $this->quantities->add((string) $locked->stock_quantity, $change);
        if ($this->quantities->compare($new, '0') < 0) {
            throw new \RuntimeException('Stock cannot go below zero.');
        }
        $locked->stock_quantity = $new;
        $locked->save();
        $this->recordMovement($product, $variant, $change, StockMovementReason::ManualAdjustment, null, $note);
    }

    public function recordMovement(Product $product, ?ProductVariant $variant, string $change, StockMovementReason $reason, ?Order $order = null, ?string $note = null): StockMovement
    {
        $negative = str_starts_with($change, '-');
        $abs = $this->quantities->toStorage(ltrim($change, '+-'));

        return StockMovement::create([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'change' => $negative ? '-'.$abs : $abs,
            'reason' => $reason,
            'order_id' => $order?->id,
            'user_id' => Auth::id(),
            'unit_code' => $product->unit_code,
            'note' => $note,
        ]);
    }
}
