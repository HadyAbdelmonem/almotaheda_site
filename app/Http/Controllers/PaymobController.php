<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentService;
use App\Services\PaymobService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Paymob HTTP endpoints (spec §4.8, Phase 10).
 *
 * - callback(): the ONLY route that can change online payment state.
 *   CSRF-exempt (exact route only, see bootstrap/app.php) because it is
 *   authenticated by the HMAC signature instead.
 * - pay(): local interstitial hosting the provider iFrame; creating a
 *   fresh attempt here is the retry path (distinct attempt, no
 *   duplicate order or stock movement).
 * - returnPage(): display-only. It reads payment state and NEVER
 *   mutates anything — a browser redirect is not proof of payment.
 */
class PaymobController extends Controller
{
    public function callback(Request $request, PaymobService $paymob): Response
    {
        $obj = $request->input('obj');
        $hmac = (string) ($request->input('hmac') ?? $request->query('hmac') ?? '');

        if (! is_array($obj)) {
            Log::channel('payments')->warning('paymob callback rejected: missing obj payload');

            return response('invalid payload', 422);
        }

        $outcome = $paymob->processCallback($obj, $hmac);

        return response($outcome['result'], $outcome['http']);
    }

    public function pay(Request $request, string $orderNumber, PaymobService $paymob, PaymentService $payments): View|RedirectResponse
    {
        $order = $this->authorizedOrder($request, $orderNumber);

        if (! $payments->cardAvailable() || $order->payment_status->value === 'paid') {
            return redirect()->route('order.success', $order->order_number);
        }

        $iframeUrl = session('paymob.iframe.'.$order->order_number);
        if (! is_string($iframeUrl) || $iframeUrl === '') {
            try {
                $result = $paymob->createPaymentAttempt($order);
                $iframeUrl = $result['iframe_url'];
                session()->put('paymob.iframe.'.$order->order_number, $iframeUrl);
            } catch (\Throwable $e) {
                Log::channel('payments')->error('paymob attempt creation failed', [
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->route('order.success', $order->order_number);
            }
        }

        return view('payments.paymob-pay', [
            'order' => $order,
            'iframeUrl' => $iframeUrl,
            'title' => __('store.card_payment_title'),
            'robots' => 'noindex,nofollow',
        ]);
    }

    /** Display-only return page: reads state, never changes it. */
    public function returnPage(Request $request, string $orderNumber): View
    {
        $order = $this->authorizedOrder($request, $orderNumber);

        return view('payments.paymob-return', [
            'order' => $order->fresh(),
            'title' => __('store.card_payment_title'),
            'robots' => 'noindex,nofollow',
        ]);
    }

    /** Same authorization rule as the order success page (§6.7). */
    private function authorizedOrder(Request $request, string $orderNumber): Order
    {
        $order = Order::query()->where('order_number', $orderNumber)->with('items')->firstOrFail();

        $isCreatorSession = session()->get('order.success.'.$order->order_number) === true;
        $isOwner = $request->user() !== null && $order->user_id !== null
            && (int) $order->user_id === (int) $request->user()->id;

        abort_unless($isCreatorSession || $isOwner, 403);

        return $order;
    }
}
