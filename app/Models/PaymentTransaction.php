<?php

namespace App\Models;

use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PaymentTransactionStatus::class, 'amount' => 'decimal:2', 'is_success' => 'boolean', 'payload_redacted' => 'array', 'processed_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    protected $hidden = ['payload_redacted'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
