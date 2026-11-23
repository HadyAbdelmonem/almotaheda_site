<?php

namespace App\Livewire\Account;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Customer orders list + detail (spec §6.8). Ownership is enforced by
 * scoping every query to the authenticated user — changing the URL
 * order number of another customer yields a 404, never their data.
 * Guest orders (user_id null) are NOT attached by phone match.
 */
class AccountOrders extends Component
{
    public ?string $orderNumber = null;

    public function mount(?string $orderNumber = null): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function detailOrder(): ?Order
    {
        if ($this->orderNumber === null) {
            return null;
        }

        return Order::query()
            ->where('user_id', Auth::id())
            ->where('order_number', $this->orderNumber)
            ->with(['items', 'statusHistories.changedBy'])
            ->firstOrFail();
    }

    /** Customers may cancel ONLY their own order while it is `new` (§4.7). */
    public function cancelOrder(int $orderId, OrderService $orders): void
    {
        $order = Order::query()->where('user_id', Auth::id())->findOrFail($orderId);
        if ($order->status !== OrderStatus::New) {
            $this->dispatch('toast', message: __('store.cancel_only_new'), type: 'error');

            return;
        }
        $orders->changeStatus($order, OrderStatus::Cancelled, Auth::user());
        $this->dispatch('toast', message: __('store.order_cancelled_success'), type: 'success');
    }

    public function render(): View
    {
        $detail = $this->detailOrder();

        return view('livewire.account.orders', [
            'detail' => $detail,
            'orders' => $detail === null
                ? Auth::user()->orders()->latest()->paginate(10)
                : collect(),
        ])->layout('layouts.app', ['title' => __('store.my_orders'), 'robots' => 'noindex,follow']);
    }
}
