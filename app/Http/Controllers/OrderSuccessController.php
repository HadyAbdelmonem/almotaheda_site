<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Analytics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Order success page (spec §6.7): visible ONLY to the session that
 * created the order or to the user who owns it.
 */
class OrderSuccessController extends Controller
{
    public function show(Request $request, string $orderNumber): View
    {
        $order = Order::query()->where('order_number', $orderNumber)->with('items')->firstOrFail();

        $isCreatorSession = session()->get('order.success.'.$order->order_number) === true;
        $isOwner = $request->user() !== null && $order->user_id !== null
            && (int) $order->user_id === (int) $request->user()->id;

        abort_unless($isCreatorSession || $isOwner, 403);

        // GA4 purchase event (spec §9A): rendered exactly once per order
        // for this session — a session flag dedupes reloads and
        // back-navigation; analytics.js dedupes again by transaction id.
        $gaEvents = [];
        if (Analytics::enabled() && ! session()->has('ga.purchase.'.$order->order_number)) {
            session()->put('ga.purchase.'.$order->order_number, true);
            $gaEvents = [Analytics::purchase($order)];
        }

        return view('order.success', [
            'order' => $order,
            'title' => __('store.order_success_title'),
            'robots' => 'noindex,nofollow',
            'gaEvents' => $gaEvents,
        ]);
    }
}
