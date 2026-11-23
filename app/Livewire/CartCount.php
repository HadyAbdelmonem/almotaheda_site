<?php

namespace App\Livewire;

use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Header cart badge (E14): re-renders when a `cart-updated` event is
 * dispatched after a server-confirmed cart change.
 */
class CartCount extends Component
{
    #[On('cart-updated')]
    public function refreshCount(): void
    {
        // Re-render with the fresh session count.
    }

    public function render(): View
    {
        return view('livewire.cart-count', [
            'count' => app(CartService::class)->count(),
        ]);
    }
}
