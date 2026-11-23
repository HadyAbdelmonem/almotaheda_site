<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use InvalidArgumentException;

/**
 * Customer reviews (spec §6.8, Phase 7): one per user/product, always
 * pending moderation on submission. `is_verified_purchase` is true
 * only when the user has a DELIVERED order containing the product.
 * Aggregates count approved reviews only.
 */
class ReviewService
{
    public function submit(User $user, Product $product, int $rating, ?string $comment): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Rating must be between 1 and 5.');
        }
        if (Review::query()->where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
            throw new InvalidArgumentException('review_already_exists');
        }

        $verified = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Delivered)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        return Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => $rating,
            'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            'status' => ReviewStatus::Pending,
            'is_verified_purchase' => $verified,
        ]);
    }

    /** @return array{count: int, average: float} Approved reviews only. */
    public function aggregate(Product $product): array
    {
        $approved = $product->reviews()->where('status', ReviewStatus::Approved);

        return [
            'count' => (clone $approved)->count(),
            'average' => (float) ((clone $approved)->avg('rating') ?? 0),
        ];
    }
}
