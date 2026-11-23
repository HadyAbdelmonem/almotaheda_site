<?php

namespace App\Livewire;

use App\Models\Order;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Guest order tracking (spec §4.9): BOTH order number and phone must
 * match exactly. Any mismatch returns the same generic message — the
 * response never reveals whether either field exists. Rate-limited.
 */
class TrackOrderPage extends Component
{
    public string $orderNumber = '';

    public string $phone = '';

    public ?Order $found = null;

    public bool $searched = false;

    public function track(): void
    {
        $this->validate([
            'orderNumber' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $key = 'track-order:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->searched = true;
            $this->found = null;

            return;
        }
        RateLimiter::hit($key, 60);

        $this->found = Order::query()
            ->where('order_number', trim($this->orderNumber))
            ->where('phone', Phone::normalize($this->phone))
            ->with(['items'])
            ->first();
        $this->searched = true;
    }

    public function render(): View
    {
        return view('livewire.track-order')->layout('layouts.app', ['title' => __('store.track_order'), 'robots' => 'noindex,follow']);
    }
}
