<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\ProductDisplay;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;

/**
 * Session cart (spec §3, §4.6, §6.6). The session stores ONLY
 * product_id, variant_id and the decimal quantity string. Prices,
 * sale/tier rules, stock and totals are always recomputed from the
 * database on every render (see resolvedLines()).
 */
class CartService
{
    private const SESSION_KEY = 'cart.items';

    public function __construct(
        private readonly QuantityService $quantities,
        private readonly PricingService $pricing,
    ) {}

    /** @return array<int, array{product_id: int, variant_id: ?int, quantity: string}> */
    public function items(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    private function putItems(array $items): void
    {
        Session::put(self::SESSION_KEY, array_values($items));
    }

    /**
     * Add a quantity to the cart with full server-side validation
     * (spec §4.6/§4.11): the product and variant must be active, the
     * quantity must satisfy min/step/max, and the resulting line
     * quantity is clamped to available stock. Returns the quantity
     * actually in the cart after the add.
     *
     * @throws CartException with a `store.` translation key.
     */
    public function addItem(int $productId, ?int $variantId, string $quantity): string
    {
        $product = Product::query()->where('is_active', true)->with(['variants' => fn ($q) => $q->where('is_active', true), 'priceTiers'])->find($productId);
        if ($product === null) {
            throw new CartException('cart_error_unavailable');
        }

        $variant = null;
        if ($product->has_variants) {
            if ($variantId === null) {
                throw new CartException('cart_error_choose_variant');
            }
            $variant = $product->variants->firstWhere('id', $variantId);
            if ($variant === null) {
                throw new CartException('cart_error_unavailable');
            }
        } elseif ($variantId !== null) {
            throw new CartException('cart_error_unavailable');
        }

        try {
            $quantity = $this->quantities->validate(
                $quantity,
                (string) $product->min_order_quantity,
                (string) $product->quantity_step,
                $product->max_order_quantity !== null ? (string) $product->max_order_quantity : null,
            );
        } catch (InvalidArgumentException) {
            throw new CartException('cart_error_quantity');
        }

        $items = $this->items();
        $existing = '0';
        foreach ($items as $item) {
            if ($item['product_id'] === $productId && $item['variant_id'] === $variantId) {
                $existing = $item['quantity'];
            }
        }
        $wanted = $this->quantities->add($existing, $quantity);

        if ($product->max_order_quantity !== null && $this->quantities->compare($wanted, (string) $product->max_order_quantity) > 0) {
            $wanted = $this->quantities->normalize((string) $product->max_order_quantity);
        }

        if ($product->track_stock) {
            $available = ProductDisplay::availableStock($product, $variant);
            if ($this->quantities->compare($available, '0') <= 0) {
                throw new CartException('cart_error_out_of_stock');
            }
            if ($this->quantities->compare($wanted, $available) > 0) {
                $wanted = $this->quantities->clampToStock($wanted, $available, (string) $product->quantity_step);
            }
            if ($this->quantities->compare($wanted, (string) $product->min_order_quantity) < 0) {
                throw new CartException('cart_error_out_of_stock');
            }
        }

        $found = false;
        foreach ($items as $i => $item) {
            if ($item['product_id'] === $productId && $item['variant_id'] === $variantId) {
                $items[$i]['quantity'] = $wanted;
                $found = true;
            }
        }
        if (! $found) {
            $items[] = ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => $wanted];
        }
        $this->putItems($items);

        return $wanted;
    }

    /** Set a line quantity (already validated/clamped by the caller flow in CartPage; re-validated here). */
    public function updateQuantity(int $productId, ?int $variantId, string $quantity): string
    {
        $product = Product::query()->where('is_active', true)->with(['variants' => fn ($q) => $q->where('is_active', true)])->find($productId);
        if ($product === null) {
            throw new CartException('cart_error_unavailable');
        }
        $variant = $variantId !== null ? $product->variants->firstWhere('id', $variantId) : null;
        if ($variantId !== null && $variant === null) {
            throw new CartException('cart_error_unavailable');
        }
        try {
            $quantity = $this->quantities->validate(
                $quantity,
                (string) $product->min_order_quantity,
                (string) $product->quantity_step,
                $product->max_order_quantity !== null ? (string) $product->max_order_quantity : null,
            );
        } catch (InvalidArgumentException) {
            throw new CartException('cart_error_quantity');
        }
        if ($product->track_stock) {
            $available = ProductDisplay::availableStock($product, $variant);
            if ($this->quantities->compare($quantity, $available) > 0) {
                $quantity = $this->quantities->clampToStock($quantity, $available, (string) $product->quantity_step);
            }
        }
        $items = $this->items();
        foreach ($items as $i => $item) {
            if ($item['product_id'] === $productId && $item['variant_id'] === $variantId) {
                $items[$i]['quantity'] = $quantity;
            }
        }
        $this->putItems($items);

        return $quantity;
    }

    public function remove(int $productId, ?int $variantId): void
    {
        $items = array_filter($this->items(), fn ($item) => ! ($item['product_id'] === $productId && $item['variant_id'] === $variantId));
        $this->putItems($items);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget('cart.coupon_code');
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function setCouponCode(?string $code): void
    {
        $code === null ? Session::forget('cart.coupon_code') : Session::put('cart.coupon_code', $code);
    }

    public function couponCode(): ?string
    {
        return Session::get('cart.coupon_code');
    }

    /**
     * Resolve every session line against the current database state.
     * Each line reports its effective (possibly clamped) quantity,
     * freshly computed pricing and any warning keys the UI must show
     * (price/stock changed, out of stock, product unavailable...).
     * Lines that cannot be purchased get `purchasable = false` and are
     * excluded from the subtotal.
     *
     * @return array<int, array{
     *     product_id: int, variant_id: ?int, product: ?Product, variant: ?ProductVariant,
     *     quantity: string, effective_quantity: string, purchasable: bool,
     *     warnings: array<int, string>, pricing: ?array, unit_price: ?string, line_total: ?string
     * }>
     */
    public function resolvedLines(): array
    {
        $items = $this->items();
        if ($items === []) {
            return [];
        }

        $products = Product::query()
            ->with(['brand', 'category.parent', 'media', 'variants', 'priceTiers'])
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $line = [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'product' => $product,
                'variant' => null,
                'quantity' => $item['quantity'],
                'effective_quantity' => $item['quantity'],
                'purchasable' => false,
                'warnings' => [],
                'pricing' => null,
                'unit_price' => null,
                'line_total' => null,
            ];

            if ($product === null || ! $product->is_active) {
                $line['warnings'][] = 'cart_warning_unavailable';
                $lines[] = $line;

                continue;
            }

            $variant = null;
            if ($item['variant_id'] !== null) {
                $variant = $product->variants->firstWhere('id', $item['variant_id']);
                if ($variant === null || ! $variant->is_active) {
                    $line['warnings'][] = 'cart_warning_unavailable';
                    $lines[] = $line;

                    continue;
                }
            } elseif ($product->has_variants) {
                $line['warnings'][] = 'cart_warning_unavailable';
                $lines[] = $line;

                continue;
            }
            $line['variant'] = $variant;

            // Quantity rules: an invalid stored quantity falls back to
            // the product minimum and is flagged, never trusted.
            try {
                $effective = $this->quantities->validate(
                    $item['quantity'],
                    (string) $product->min_order_quantity,
                    (string) $product->quantity_step,
                    $product->max_order_quantity !== null ? (string) $product->max_order_quantity : null,
                );
            } catch (InvalidArgumentException) {
                $effective = $this->quantities->normalize((string) $product->min_order_quantity);
                $line['warnings'][] = 'cart_warning_quantity_adjusted';
            }

            if ($product->track_stock) {
                $available = ProductDisplay::availableStock($product, $variant);
                if ($this->quantities->compare($available, '0') <= 0) {
                    $line['warnings'][] = 'cart_warning_out_of_stock';
                    $line['effective_quantity'] = $effective;
                    $lines[] = $line;

                    continue;
                }
                if ($this->quantities->compare($effective, $available) > 0) {
                    $effective = $this->quantities->clampToStock($effective, $available, (string) $product->quantity_step);
                    $line['warnings'][] = 'cart_warning_stock_clamped';
                }
            }

            $pricing = $this->pricing->unitPrice($product, $variant, $effective);
            $line['effective_quantity'] = $effective;
            $line['pricing'] = $pricing;
            $line['unit_price'] = $pricing['unit_price'];
            $line['line_total'] = $this->pricing->lineTotal($pricing['unit_price'], $effective);
            $line['purchasable'] = true;
            $lines[] = $line;
        }

        return $lines;
    }

    /** Subtotal of purchasable lines only, recomputed from the DB. */
    public function subtotal(array $lines): string
    {
        $subtotal = '0.00';
        foreach ($lines as $line) {
            if ($line['purchasable'] && $line['line_total'] !== null) {
                $subtotal = Money::add($subtotal, $line['line_total']);
            }
        }

        return $subtotal;
    }
}
