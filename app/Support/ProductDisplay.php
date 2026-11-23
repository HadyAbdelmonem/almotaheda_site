<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\PricingService;
use App\Services\QuantityService;

/**
 * Shared storefront display calculations for products (spec §4.2, §4.6).
 * Used by the product card, the product page and listings so price/stock
 * logic lives in exactly one place. Never exposes cost_price.
 */
final class ProductDisplay
{
    /** Available stock for a product or one of its variants (decimal string). */
    public static function availableStock(Product $product, ?ProductVariant $variant = null): string
    {
        if (! $product->track_stock) {
            return '999';
        }
        $raw = $variant !== null ? (string) $variant->stock_quantity : (string) $product->stock_quantity;

        return app(QuantityService::class)->normalize($raw);
    }

    /** Stock badge state: 'in' | 'low' | 'out' (spec §4.6). */
    public static function stockState(Product $product, ?ProductVariant $variant = null): string
    {
        if (! $product->track_stock) {
            return 'in';
        }
        $qty = app(QuantityService::class);
        $available = self::availableStock($product, $variant);
        if ($qty->compare($available, '0') <= 0) {
            return 'out';
        }
        $threshold = $product->effectiveLowStockThreshold();

        return $qty->compare($available, $threshold) <= 0 ? 'low' : 'in';
    }

    /**
     * Card pricing at the product's minimum quantity. For variant products
     * without a selected variant this returns the cheapest active variant
     * price and flags "starts from" (spec §4.2 rule 8).
     *
     * @return array{pricing: array, starts_from: bool, variant: ?ProductVariant}
     */
    public static function cardPricing(Product $product): array
    {
        $pricing = app(PricingService::class);
        $minQty = (string) $product->min_order_quantity;

        if ($product->has_variants) {
            $best = null;
            $bestVariant = null;
            foreach ($product->variants->where('is_active', true) as $variant) {
                $candidate = $pricing->unitPrice($product, $variant, $minQty);
                if ($best === null || Money::compare($candidate['unit_price'], $best['unit_price']) < 0) {
                    $best = $candidate;
                    $bestVariant = $variant;
                }
            }
            if ($best !== null) {
                return ['pricing' => $best, 'starts_from' => true, 'variant' => $bestVariant];
            }
        }

        return [
            'pricing' => $pricing->unitPrice($product, null, $minQty),
            'starts_from' => false,
            'variant' => null,
        ];
    }

    /** Format a decimal quantity for display, trimming trailing zeros (spec §8.2). */
    public static function formatQuantity(string $quantity): string
    {
        return app(QuantityService::class)->normalize($quantity);
    }

    /** Arabic per-unit label, e.g. للمتر / للقطعة (config/units.php). */
    public static function perUnitLabel(Product $product): string
    {
        return config("units.{$product->unit_code}.per_ar", '');
    }

    /** Primary gallery image URL (medium conversion) or null when the product has no media. */
    public static function primaryImageUrl(Product $product, string $conversion = 'medium'): ?string
    {
        $media = $product->getFirstMedia('gallery');

        return $media ? $media->getUrl($conversion) : null;
    }
}
