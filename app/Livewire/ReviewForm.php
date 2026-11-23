<?php

namespace App\Livewire;

use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Review submission on the product page (Phase 7). Auth required;
 * one review per user/product; new reviews are pending moderation.
 */
class ReviewForm extends Component
{
    public int $productId;

    public int $rating = 5;

    public string $comment = '';

    public ?string $notice = null;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
    }

    public function submit(ReviewService $reviews): void
    {
        // Rate limit review submissions per user + IP (spec §9).
        $key = 'review-submit:'.Auth::id().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->notice = 'error';

            return;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $product = Product::findOrFail($this->productId);
        try {
            $reviews->submit(Auth::user(), $product, $this->rating, $this->comment);
            $this->notice = 'pending';
            $this->reset(['comment']);
        } catch (\InvalidArgumentException $e) {
            $this->notice = $e->getMessage() === 'review_already_exists' ? 'exists' : 'error';
        }
    }

    public function render(): View
    {
        return view('livewire.review-form');
    }
}
