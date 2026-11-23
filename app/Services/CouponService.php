<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Coupon validation and discount calculation (spec §4.3).
 * One coupon per order, never stacked. Final race-safe validation is
 * repeated inside the order transaction in OrderService (Phase 5B).
 */
class CouponService
{
    public function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::query()->where('code', $this->normalizeCode($code))->first();
    }

    /**
     * Validate a coupon against a subtotal.
     * Returns null when valid, otherwise an internal error key.
     */
    public function validate(?Coupon $coupon, string $subtotal, ?CarbonInterface $now = null): ?string
    {
        $now ??= now();
        if ($coupon === null) {
            return 'not_found';
        }
        if (! $coupon->is_active) {
            return 'inactive';
        }
        if ($coupon->starts_at !== null && $coupon->starts_at->greaterThan($now)) {
            return 'not_started';
        }
        if ($coupon->expires_at !== null && $coupon->expires_at->lessThan($now)) {
            return 'expired';
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return 'exhausted';
        }
        if (Money::compare($subtotal, (string) $coupon->min_order) < 0) {
            return 'min_order_not_met';
        }

        return null;
    }

    /** Product discount for a valid coupon. free_shipping coupons discount 0. */
    public function discount(Coupon $coupon, string $subtotal): string
    {
        return match ($coupon->type) {
            CouponType::Percent => $this->percentDiscount($coupon, $subtotal),
            CouponType::Fixed => Money::min((string) $coupon->value, $subtotal),
            CouponType::FreeShipping => '0.00',
        };
    }

    private function percentDiscount(Coupon $coupon, string $subtotal): string
    {
        $discount = Money::percent($subtotal, (string) $coupon->value);
        if ($coupon->max_discount !== null) {
            $discount = Money::min($discount, (string) $coupon->max_discount);
        }

        return $discount;
    }
}
