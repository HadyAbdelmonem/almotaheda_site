<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\ShippingRate;
use App\Support\Money;

/**
 * Shipping fees and the free-shipping rule (spec §4.4).
 * Free when (subtotal - coupon_discount) >= free_shipping_threshold
 * (threshold > 0), or when the coupon type is free_shipping.
 */
class ShippingService
{
    public function rateFor(int $shippingRateId): ?ShippingRate
    {
        return ShippingRate::query()->where('is_active', true)->find($shippingRateId);
    }

    public function isFreeShipping(string $subtotal, string $discount, ?Coupon $coupon = null): bool
    {
        if ($coupon !== null && $coupon->type === CouponType::FreeShipping) {
            return true;
        }
        $threshold = (string) setting('free_shipping_threshold', 3000);
        if (Money::compare($threshold, '0') <= 0) {
            return false;
        }
        $goodsTotal = Money::sub($subtotal, $discount);

        return Money::compare($goodsTotal, $threshold) >= 0;
    }

    /** Fee for a chosen governorate, or null when no governorate is chosen yet. */
    public function fee(?ShippingRate $rate, string $subtotal, string $discount, ?Coupon $coupon = null): ?string
    {
        if ($rate === null) {
            return null;
        }
        if ($this->isFreeShipping($subtotal, $discount, $coupon)) {
            return '0.00';
        }

        return Money::of((string) $rate->fee);
    }

    /** Remaining amount to reach free shipping ("0.00" when already free or disabled). */
    public function remainingForFreeShipping(string $subtotal, string $discount): string
    {
        $threshold = (string) setting('free_shipping_threshold', 3000);
        if (Money::compare($threshold, '0') <= 0) {
            return '0.00';
        }
        $remaining = Money::sub($threshold, Money::sub($subtotal, $discount));

        return Money::compare($remaining, '0') < 0 ? '0.00' : $remaining;
    }
}
