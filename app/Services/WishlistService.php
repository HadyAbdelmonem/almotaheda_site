<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wishlist;

/** Wishlist toggling (spec §6.8, Phase 7). Unique per user+product. */
class WishlistService
{
    /** Returns true when the product is now IN the wishlist. */
    public function toggle(User $user, int $productId): bool
    {
        $existing = Wishlist::query()->where('user_id', $user->id)->where('product_id', $productId)->first();
        if ($existing !== null) {
            $existing->delete();

            return false;
        }
        Wishlist::create(['user_id' => $user->id, 'product_id' => $productId]);

        return true;
    }

    public function contains(User $user, int $productId): bool
    {
        return Wishlist::query()->where('user_id', $user->id)->where('product_id', $productId)->exists();
    }
}
