<?php

namespace App\Livewire;

use App\Services\WishlistService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Wishlist heart (M80, Phase 7): finite pop animation on toggle,
 * reduced-motion aware via the motion variants. Guests are sent to
 * login — wishlist rows belong to a user.
 */
class WishlistButton extends Component
{
    public int $productId;

    public bool $active = false;

    public function mount(int $productId, WishlistService $wishlist): void
    {
        $this->productId = $productId;
        $this->active = Auth::check() && $wishlist->contains(Auth::user(), $productId);
    }

    public function toggle(WishlistService $wishlist): mixed
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }
        $this->active = $wishlist->toggle(Auth::user(), $this->productId);

        return null;
    }

    public function render(): View
    {
        return view('livewire.wishlist-button');
    }
}
