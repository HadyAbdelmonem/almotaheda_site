<?php

namespace App\Livewire;

use App\Exceptions\CartException;
use App\Models\Product;
use App\Services\CartService;
use App\Services\PricingService;
use App\Support\Analytics;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Standalone add-to-cart button for product cards (spec §6.3, M43).
 * Only simple (non-variant) products use it; variant products link to
 * the product page to pick a variant first. The `cart-added` browser
 * event — which drives fly-to-cart — is dispatched ONLY after the
 * server has stored the line.
 */
class AddToCartButton extends Component
{
    public int $productId;

    public string $quantity = '1';

    public function add(CartService $cart): void
    {
        try {
            $cart->addItem($this->productId, null, $this->quantity);
        } catch (CartException $e) {
            $this->dispatch('toast', message: __('store.'.$e->errorKey), type: 'error');

            return;
        }

        $this->dispatch('cart-updated');
        $this->dispatch('cart-added');
        // GA4 add_to_cart only after the server stored the line (§9A).
        $product = Product::query()->with(['brand', 'category'])->find($this->productId);
        if ($product !== null) {
            $pricing = app(PricingService::class)->unitPrice($product, null, $this->quantity);
            $this->dispatch('ga-add-to-cart', payload: Analytics::addToCart($product, null, $pricing['unit_price'], $this->quantity));
        }
        $this->dispatch('toast', message: __('store.added_to_cart'), actionLabel: __('store.view_cart'), actionUrl: url('/cart'));
    }

    public function render(): View
    {
        return view('livewire.add-to-cart-button');
    }
}
