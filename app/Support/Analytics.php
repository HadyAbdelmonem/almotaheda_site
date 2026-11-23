<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * GA4 ecommerce payload builder (spec §9A). This is the ONLY place GA4
 * event payloads are constructed, and it works from an explicit
 * allowlist: item id/name/brand/category, price, quantity, order total,
 * shipping and transaction id. It NEVER includes cost_price, phone,
 * email, address, customer names, payment data or any secret — callers
 * cannot add fields because payloads are built here, not assembled ad
 * hoc in controllers or views.
 */
final class Analytics
{
    public static function enabled(): bool
    {
        return trim((string) setting('ga4_measurement_id', '')) !== '';
    }

    public static function measurementId(): string
    {
        return trim((string) setting('ga4_measurement_id', ''));
    }

    /** @return array<string, mixed> */
    private static function item(Product $product, ?ProductVariant $variant, string $unitPrice, string $quantity): array
    {
        return array_filter([
            'item_id' => $variant?->sku ?: $product->model_code,
            'item_name' => $product->name.($variant !== null ? ' — '.$variant->name : ''),
            'item_brand' => $product->brand?->name,
            'item_category' => $product->category?->name,
            'price' => (float) Money::of($unitPrice),
            'quantity' => (float) $quantity,
        ], fn ($value) => $value !== null);
    }

    /** @return array{event: string, params: array<string, mixed>} */
    public static function viewItem(Product $product): array
    {
        $card = ProductDisplay::cardPricing($product);

        return [
            'event' => 'view_item',
            'params' => [
                'currency' => 'EGP',
                'value' => (float) Money::of($card['pricing']['unit_price']),
                'items' => [self::item($product, $card['variant'], $card['pricing']['unit_price'], (string) $product->min_order_quantity)],
            ],
        ];
    }

    /** @return array{event: string, params: array<string, mixed>} */
    public static function addToCart(Product $product, ?ProductVariant $variant, string $unitPrice, string $quantity): array
    {
        return [
            'event' => 'add_to_cart',
            'params' => [
                'currency' => 'EGP',
                'value' => (float) Money::mulQty($unitPrice, $quantity),
                'items' => [self::item($product, $variant, $unitPrice, $quantity)],
            ],
        ];
    }

    /**
     * @param  array<int, array{product: ?Product, variant: ?ProductVariant, unit_price: ?string, quantity: string}>  $lines
     * @return array{event: string, params: array<string, mixed>}
     */
    public static function beginCheckout(array $lines, string $total): array
    {
        $items = [];
        foreach ($lines as $line) {
            if (($line['product'] ?? null) === null || ($line['unit_price'] ?? null) === null) {
                continue;
            }
            $items[] = self::item($line['product'], $line['variant'] ?? null, (string) $line['unit_price'], (string) $line['quantity']);
        }

        return [
            'event' => 'begin_checkout',
            'params' => [
                'currency' => 'EGP',
                'value' => (float) Money::of($total),
                'items' => $items,
            ],
        ];
    }

    /** @return array{event: string, params: array<string, mixed>} */
    public static function purchase(Order $order): array
    {
        $items = $order->items->map(fn ($item) => array_filter([
            'item_id' => $item->sku,
            'item_name' => $item->name.($item->variant_name ? ' — '.$item->variant_name : ''),
            'price' => (float) Money::of((string) $item->unit_price),
            'quantity' => (float) $item->quantity,
        ], fn ($value) => $value !== null))->values()->all();

        return [
            'event' => 'purchase',
            'params' => [
                'transaction_id' => $order->order_number,
                'currency' => 'EGP',
                'value' => (float) Money::of((string) $order->total),
                'shipping' => (float) Money::of((string) $order->shipping_fee),
                'items' => $items,
            ],
        ];
    }
}
