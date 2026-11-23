<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['type' => CouponType::class, 'value' => 'decimal:2', 'min_order' => 'decimal:2', 'max_discount' => 'decimal:2', 'usage_limit' => 'integer', 'used_count' => 'integer', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
