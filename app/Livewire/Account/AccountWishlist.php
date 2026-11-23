<?php

namespace App\Livewire\Account;

use App\Services\WishlistService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** Wishlist page (spec §6.8): product cards + empty scene M73. */
class AccountWishlist extends Component
{
    public function remove(int $productId, WishlistService $wishlist): void
    {
        $wishlist->toggle(Auth::user(), $productId);
    }

    public function render(): View
    {
        $products = Auth::user()->wishlist()
            ->with(['product.brand', 'product.media'])
            ->latest()
            ->get()
            ->map(fn ($row) => $row->product)
            ->filter(fn ($p) => $p !== null && $p->is_active)
            ->values();

        return view('livewire.account.wishlist', ['products' => $products])
            ->layout('layouts.app', ['title' => __('store.my_wishlist'), 'robots' => 'noindex,follow']);
    }
}
