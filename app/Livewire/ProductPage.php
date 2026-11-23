<?php

namespace App\Livewire;

use App\Enums\ReviewStatus;
use App\Exceptions\CartException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\CatalogAttributeService;
use App\Services\PricingService;
use App\Services\QuantityService;
use App\Support\Analytics;
use App\Support\Money;
use App\Support\ProductDisplay;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Product detail page (spec §6.2). Class-based Livewire component.
 * Prices, sale validity, tiers and stock are always recomputed on the
 * server from the database via PricingService / QuantityService; nothing
 * price-related is trusted from the client. Add-to-cart (Phase 5A) goes
 * through CartService and only dispatches success events after the
 * server has stored the line.
 */
class ProductPage extends Component
{
    public string $slug;

    public ?int $variantId = null;

    public string $quantity = '1';

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $product = $this->product();
        $this->quantity = app(QuantityService::class)->normalize((string) $product->min_order_quantity);
    }

    public function product(): Product
    {
        return Product::query()
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->with([
                'category.parent',
                'brand',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'priceTiers' => fn ($q) => $q->orderBy('min_qty'),
                'attributeValues.attribute',
                'media',
            ])
            ->firstOrFail();
    }

    /**
     * Add the current selection to the cart (Phase 5A). The
     * `cart-added` event (fly-to-cart, M43) is dispatched ONLY after
     * CartService has validated and stored the line server-side.
     */
    public function addToCart(CartService $cart): void
    {
        $product = $this->product();
        $variant = $this->selectedVariant($product);

        if ($product->has_variants && $variant === null) {
            $this->dispatch('toast', message: __('store.cart_error_choose_variant'), type: 'error');

            return;
        }

        try {
            $cart->addItem($product->id, $variant?->id, $this->quantity);
        } catch (CartException $e) {
            $this->dispatch('toast', message: __('store.'.$e->errorKey), type: 'error');

            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-added');
        // GA4 add_to_cart fires only after the server stored the line
        // (spec §9A); the payload is built by the Analytics allowlist.
        $pricing = app(PricingService::class)->unitPrice($product, $variant, $this->quantity);
        $this->dispatch('ga-add-to-cart', payload: Analytics::addToCart($product, $variant, $pricing['unit_price'], $this->quantity));
        $this->dispatch('toast', message: __('store.added_to_cart'), actionLabel: __('store.view_cart'), actionUrl: url('/cart'));
    }

    public function selectVariant(int $variantId): void
    {
        $product = $this->product();
        $variant = $product->variants->firstWhere('id', $variantId);
        if ($variant === null) {
            return; // Inactive or foreign variants can never be selected.
        }
        $this->variantId = $variant->id;
        $this->clampQuantity($product, $variant);
    }

    public function increment(): void
    {
        $this->stepBy(1);
    }

    public function decrement(): void
    {
        $this->stepBy(-1);
    }

    public function updatedQuantity(mixed $value): void
    {
        $product = $this->product();
        $variant = $this->selectedVariant($product);
        try {
            $this->quantity = app(QuantityService::class)->validate(
                (string) $value,
                (string) $product->min_order_quantity,
                (string) $product->quantity_step,
                $product->max_order_quantity !== null ? (string) $product->max_order_quantity : null,
            );
        } catch (\InvalidArgumentException) {
            // Invalid input falls back to the clamped minimum; the server
            // stays the source of truth and never accepts a bad quantity.
            $this->quantity = app(QuantityService::class)->normalize((string) $product->min_order_quantity);
        }
        $this->clampQuantity($product, $variant);
    }

    private function stepBy(int $direction): void
    {
        $product = $this->product();
        $qty = app(QuantityService::class);
        try {
            $candidate = $direction > 0
                ? $qty->add($this->quantity, (string) $product->quantity_step)
                : $qty->sub($this->quantity, (string) $product->quantity_step);
            $this->quantity = $qty->validate(
                $candidate,
                (string) $product->min_order_quantity,
                (string) $product->quantity_step,
                $product->max_order_quantity !== null ? (string) $product->max_order_quantity : null,
            );
        } catch (\InvalidArgumentException) {
            // At a boundary: keep the current valid quantity.
        }
        $this->clampQuantity($product, $this->selectedVariant($product));
    }

    /** Clamp the quantity to available stock (spec §4.6). */
    private function clampQuantity(Product $product, ?ProductVariant $variant): void
    {
        if (! $product->track_stock) {
            return;
        }
        $qty = app(QuantityService::class);
        $available = ProductDisplay::availableStock($product, $variant);
        if ($qty->compare($this->quantity, $available) > 0) {
            $clamped = $qty->clampToStock($this->quantity, $available, (string) $product->quantity_step);
            $this->quantity = $qty->compare($clamped, (string) $product->min_order_quantity) >= 0
                ? $clamped
                : $qty->normalize((string) $product->min_order_quantity);
        }
    }

    public function selectedVariant(Product $product): ?ProductVariant
    {
        if ($this->variantId === null) {
            return null;
        }

        return $product->variants->firstWhere('id', $this->variantId);
    }

    public function render(): View
    {
        $product = $this->product();
        $variant = $this->selectedVariant($product);
        $pricingService = app(PricingService::class);
        $qty = app(QuantityService::class);

        // Until a variant is chosen, variant products show "starts from"
        // (cheapest active variant at minimum quantity, spec §4.2 rule 8).
        if ($product->has_variants && $variant === null) {
            $pricing = ProductDisplay::cardPricing($product)['pricing'];
            $startsFrom = true;
        } else {
            $pricing = $pricingService->unitPrice($product, $variant, $this->quantity);
            $startsFrom = false;
        }

        $stockState = ProductDisplay::stockState($product, $variant);
        $available = ProductDisplay::availableStock($product, $variant);

        // Quantity tiers; the active tier is the greatest min_qty the
        // current quantity has reached (M42 highlight).
        $activeTierQty = null;
        foreach ($product->priceTiers as $tier) {
            if ($qty->compare($this->quantity, (string) $tier->min_qty) >= 0) {
                $activeTierQty = (string) $tier->min_qty;
            }
        }
        $tiers = $product->priceTiers->map(fn ($tier) => [
            'min_qty' => (string) $tier->min_qty,
            'discount_percent' => (string) $tier->discount_percent,
            'unit_price' => $pricingService->unitPrice($product, $variant, (string) $tier->min_qty)['unit_price'],
            'active' => $activeTierQty !== null && (string) $tier->min_qty === $activeTierQty,
        ]);

        // Technical attributes (structured, spec §4.12) for this category.
        $attributes = app(CatalogAttributeService::class)->forCategory($product->category);
        $valuesByAttribute = $product->attributeValues->keyBy('category_attribute_id');
        $technicalSpecs = $attributes
            ->map(fn ($attribute) => isset($valuesByAttribute[$attribute->id])
                ? ['label' => $attribute->name_ar.($attribute->unit_label ? ' ('.$attribute->unit_label.')' : ''), 'value' => $valuesByAttribute[$attribute->id]->displayValue()]
                : null)
            ->filter()
            ->values();

        $reviews = $product->reviews()
            ->where('status', ReviewStatus::Approved)
            ->with('user')
            ->latest()
            ->get();
        $averageRating = $reviews->avg('rating') ?? 0;

        $related = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with(['brand', 'category', 'variants' => fn ($q) => $q->where('is_active', true), 'priceTiers', 'media'])
            ->orderByRaw('CASE WHEN brand_id = ? THEN 0 ELSE 1 END', [$product->brand_id ?? 0])
            ->orderByDesc('sales_count')
            ->limit(8)
            ->get();

        $gallery = $product->getMedia('gallery');
        $variantImage = $variant?->getFirstMediaUrl('image', 'large');

        $whatsAppMessage = $this->buildWhatsAppMessage($product, $variant, $pricing, $stockState);

        return view('livewire.product-page', [
            'product' => $product,
            'variant' => $variant,
            'pricing' => $pricing,
            'startsFrom' => $startsFrom,
            'stockState' => $stockState,
            'available' => $available,
            'tiers' => $tiers,
            'technicalSpecs' => $technicalSpecs,
            'reviews' => $reviews,
            'averageRating' => $averageRating,
            'related' => $related,
            'gallery' => $gallery,
            'variantImage' => $variantImage ?: null,
            'whatsAppMessage' => $whatsAppMessage,
            'lineTotal' => $pricingService->lineTotal($pricing['unit_price'], $this->quantity),
        ])->layout('layouts.app', [
            'title' => $product->meta_title ?: $product->name,
            'metaDescription' => $product->meta_description ?: ($product->short_description ?: $product->name),
            'canonical' => route('products.show', $product->slug),
            'ogType' => 'product',
            'ogImage' => $product->getFirstMediaUrl('gallery', 'large') ?: null,
            'jsonLd' => [
                Seo::productJsonLd($product),
                Seo::breadcrumbJsonLd(array_filter([
                    $product->category?->parent ? ['label' => $product->category->parent->name, 'url' => route('categories.show', $product->category->parent->slug)] : null,
                    $product->category ? ['label' => $product->category->name, 'url' => route('categories.show', $product->category->slug)] : null,
                    ['label' => $product->name, 'url' => route('products.show', $product->slug)],
                ])),
            ],
            'gaEvents' => [Analytics::viewItem($product)],
        ]);
    }

    private function buildWhatsAppMessage(Product $product, ?ProductVariant $variant, array $pricing, string $stockState): string
    {
        $key = $stockState === 'out' ? 'store.whatsapp_availability_message' : 'store.whatsapp_order_message';
        $lines = [__($key, ['product' => $product->name])];
        $lines[] = __('store.model_code').': '.$product->model_code;
        if ($variant !== null) {
            $lines[] = $variant->name;
            $lines[] = __('store.sku').': '.$variant->sku;
        }
        if ($stockState !== 'out') {
            $lines[] = __('store.quantity').': '.ProductDisplay::formatQuantity($this->quantity).' '.unit_label($product->unit_code);
            $lines[] = Money::format($pricing['unit_price']).' '.ProductDisplay::perUnitLabel($product);
        }
        $lines[] = route('products.show', $product->slug);

        return implode("\n", $lines);
    }
}
