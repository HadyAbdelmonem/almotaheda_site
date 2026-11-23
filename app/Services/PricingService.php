<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Effective unit price calculation (spec §4.2).
 *
 * 1. regular = variant.price ?? product.price
 * 2. A variant's own sale_price uses the variant's own sale_ends_at;
 *    otherwise the product sale_price / sale_ends_at are inherited.
 * 3. A sale applies only when non-null, lower than regular, and its
 *    end date is null or in the future.
 * 4. base = valid sale price or regular price.
 * 5. Quantity tier: greatest min_qty <= quantity; its percentage
 *    discount is applied to base and the unit price is rounded
 *    half-up to 2 decimals. Tiers stack with a valid sale price.
 * 6. line_total = rounded unit price x quantity, half-up to 2 decimals.
 */
class PricingService
{
    public function __construct(private readonly QuantityService $quantities) {}

    /**
     * @return array{
     *     regular: string, base: string, unit_price: string,
     *     sale_applied: bool, sale_price: ?string,
     *     tier_discount_percent: ?string, discount_percent: ?string
     * }
     */
    public function unitPrice(Product $product, ?ProductVariant $variant, string $quantity, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $quantity = $this->quantities->normalize($quantity);

        $regular = Money::of((string) ($variant?->price ?? $product->price));

        // Sale resolution: variant's own sale price carries its own end date.
        $salePrice = null;
        $saleEndsAt = null;
        if ($variant !== null && $variant->sale_price !== null) {
            $salePrice = (string) $variant->sale_price;
            $saleEndsAt = $variant->sale_ends_at;
        } elseif ($product->sale_price !== null) {
            $salePrice = (string) $product->sale_price;
            $saleEndsAt = $product->sale_ends_at;
        }

        $saleApplied = false;
        if ($salePrice !== null) {
            $salePrice = Money::of($salePrice);
            $notExpired = $saleEndsAt === null || $saleEndsAt->isFuture();
            // isFuture() is evaluated against real now; when a custom $now is
            // supplied (tests), compare explicitly instead.
            $notExpired = $saleEndsAt === null || $saleEndsAt->greaterThan($now);
            if (Money::compare($salePrice, $regular) < 0 && $notExpired) {
                $saleApplied = true;
            }
        }

        $base = $saleApplied ? $salePrice : $regular;

        // Quantity tier: greatest min_qty <= quantity.
        $tierPercent = null;
        $tiers = $product->relationLoaded('priceTiers')
            ? $product->priceTiers
            : $product->priceTiers()->get();
        $best = null;
        foreach ($tiers as $tier) {
            if ($this->quantities->compare((string) $tier->min_qty, $quantity) <= 0
                && ($best === null || $this->quantities->compare((string) $tier->min_qty, (string) $best->min_qty) > 0)) {
                $best = $tier;
            }
        }
        if ($best !== null) {
            $tierPercent = (string) $best->discount_percent;
        }

        $unitPrice = $tierPercent !== null && Money::compare($tierPercent, '0') > 0
            ? Money::discount($base, $tierPercent)
            : $base;

        // Overall discount percent vs regular price (for the sale badge).
        $discountPercent = null;
        if (Money::compare($unitPrice, $regular) < 0 && Money::compare($regular, '0') > 0) {
            $discountPercent = Money::round(
                bcdiv(bcmul(Money::sub($regular, $unitPrice), '100', 6), $regular, 6)
            );
        }

        return [
            'regular' => $regular,
            'base' => $base,
            'unit_price' => $unitPrice,
            'sale_applied' => $saleApplied,
            'sale_price' => $saleApplied ? $salePrice : null,
            'tier_discount_percent' => $tierPercent,
            'discount_percent' => $discountPercent,
        ];
    }

    /** line_total = rounded unit price x quantity, rounded half-up to 2 decimals. */
    public function lineTotal(string $unitPrice, string $quantity): string
    {
        return Money::mulQty(Money::of($unitPrice), $this->quantities->normalize($quantity));
    }
}
