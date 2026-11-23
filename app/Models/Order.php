<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockRestorationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['admin_notes', 'payment_proof'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'stock_restoration_status' => StockRestorationStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_reconciliation_required' => 'boolean',
            'payment_expires_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'coupon_released_at' => 'datetime',
            'stock_restored_at' => 'datetime',
            'sales_reversed_at' => 'datetime',
            'placed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Order number is assigned after insert: ORD-{YYYY}-{id padded to 5} (spec §5).
        static::created(function (Order $order) {
            if ($order->order_number === null) {
                $order->order_number = 'ORD-'.now()->format('Y').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function stockDispositionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stock_disposition_by');
    }
}
