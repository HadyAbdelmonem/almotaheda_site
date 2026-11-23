<?php

namespace App\Livewire;

use App\Enums\PaymentMethod;
use App\Exceptions\OrderException;
use App\Models\Order;
use App\Models\ShippingRate;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PaymobService;
use App\Services\ShippingService;
use App\Support\Analytics;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * One-page guest checkout (spec §6.7, Phase 5B). The account is
 * never required. All money/stock/coupon/shipping decisions are
 * re-made inside OrderService's transaction; this component only
 * collects data and previews totals with the same services.
 */
class CheckoutPage extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $phoneAlt = '';

    public string $email = '';

    public ?int $governorateId = null;

    public string $city = '';

    public string $address = '';

    public string $notes = '';

    public string $paymentMethod = 'cod';

    /** Honeypot: real users never fill this hidden field. */
    public string $website = '';

    public ?string $orderError = null;

    public function mount(CartService $cart): void
    {
        if ($cart->items() === []) {
            $this->redirect(url('/cart'));

            return;
        }

        // Double-submit protection: one idempotency token per checkout
        // session; a completed token replays to its success page.
        if (! session()->has('checkout.token')) {
            session()->put('checkout.token', (string) Str::uuid());
        }

        $user = Auth::user();
        if ($user !== null) {
            $this->name = (string) ($user->name ?? '');
            $this->email = (string) ($user->email ?? '');
            $this->phone = (string) ($user->phone ?? '');
            if (Schema::hasTable('addresses')) {
                $default = $user->addresses()->where('is_default', true)->first()
                    ?? $user->addresses()->first();
                if ($default !== null) {
                    $this->name = $default->recipient_name ?: $this->name;
                    $this->phone = $default->phone ?: $this->phone;
                    $this->city = (string) ($default->city ?? '');
                    $this->address = (string) ($default->address_line ?? '');
                    $rate = ShippingRate::query()->where('name_ar', $default->governorate)->where('is_active', true)->first();
                    $this->governorateId = $rate?->id;
                }
            }
        }
    }

    /** Payment methods the owner has enabled in settings (spec §4.1). */
    public function enabledMethods(): array
    {
        $methods = [];
        if (setting('payment_cod_enabled', true)) {
            $methods[] = PaymentMethod::Cod;
        }
        if (setting('payment_bank_enabled', true)) {
            $methods[] = PaymentMethod::BankTransfer;
        }
        // Card appears only when enabled AND fully configured (§4.8).
        if (app(PaymentService::class)->cardAvailable()) {
            $methods[] = PaymentMethod::Card;
        }

        return $methods === [] ? [PaymentMethod::Cod] : $methods;
    }

    public function submit(CartService $cart, OrderService $orders): mixed
    {
        $this->orderError = null;

        if ($this->website !== '') {
            // Honeypot: pretend the order went through, create nothing.
            return $this->redirect(url('/cart'));
        }

        $token = (string) session('checkout.token', '');
        $completed = session('checkout.completed');
        if ($token !== '' && is_array($completed) && ($completed['token'] ?? null) === $token && ! empty($completed['order_number'])) {
            return $this->redirect(route('order.success', $completed['order_number']));
        }

        $key = 'checkout-submit:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->orderError = __('store.form_throttled');

            return null;
        }
        RateLimiter::hit($key, 60);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'phoneAlt' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'governorateId' => ['required', 'integer'],
            'city' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paymentMethod' => ['required', 'string'],
        ]);

        if (! Phone::isValidMobile($this->phone)) {
            $this->addError('phone', __('store.invalid_phone'));

            return null;
        }

        $method = PaymentMethod::tryFrom($this->paymentMethod);
        if ($method === null || ! in_array($method, $this->enabledMethods(), true)) {
            $this->addError('paymentMethod', __('store.order_error_payment_method'));

            return null;
        }

        $lines = $cart->resolvedLines();
        foreach ($lines as $line) {
            if (! $line['purchasable']) {
                $this->orderError = __('store.order_error_cart_changed');

                return null;
            }
        }

        try {
            $order = $orders->create(
                customer: [
                    'name' => $this->name,
                    'phone' => Phone::normalize($this->phone),
                    'phone_alt' => $this->phoneAlt !== '' ? Phone::normalize($this->phoneAlt) : null,
                    'email' => $this->email !== '' ? $this->email : null,
                    'city' => $this->city,
                    'address' => $this->address,
                    'notes' => $this->notes !== '' ? $this->notes : null,
                ],
                lines: $cart->items(),
                couponCode: $cart->couponCode(),
                shippingRateId: (int) $this->governorateId,
                paymentMethod: $method,
                userId: Auth::id(),
            );
        } catch (OrderException $e) {
            $this->orderError = $e->translatedMessage();

            return null;
        }

        // Only after the transaction has committed: authorize this
        // session to view the success page, remember the idempotency
        // token as completed, and clear the cart.
        session()->put('order.success.'.$order->order_number, true);
        session()->put('checkout.completed', ['token' => $token, 'order_number' => $order->order_number]);
        $cart->clear();

        // Card payment (Phase 10): create the payment attempt and send
        // the customer to the hosted iFrame page. Payment state can
        // only change later via the verified server callback. If
        // attempt creation fails, the pending order stands and the
        // hourly expiry flow will cancel it — never fake a payment.
        if ($method === PaymentMethod::Card) {
            try {
                $result = app(PaymobService::class)->createPaymentAttempt($order);
                session()->put('paymob.iframe.'.$order->order_number, $result['iframe_url']);

                return $this->redirect(route('payments.paymob.pay', $order->order_number));
            } catch (\Throwable $e) {
                Log::channel('payments')->error('paymob attempt creation failed at checkout', [
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->redirect(route('order.success', $order->order_number));
    }

    public function render(CartService $cart, CouponService $coupons, ShippingService $shipping): View
    {
        $lines = $cart->resolvedLines();
        $subtotal = $cart->subtotal($lines);

        $coupon = null;
        $discount = '0.00';
        if ($cart->couponCode() !== null) {
            $candidate = $coupons->findByCode($cart->couponCode());
            if ($coupons->validate($candidate, $subtotal) === null) {
                $coupon = $candidate;
                $discount = $coupons->discount($candidate, $subtotal);
            }
        }

        $rate = $this->governorateId !== null ? $shipping->rateFor($this->governorateId) : null;
        $fee = $shipping->fee($rate, $subtotal, $discount, $coupon);
        $total = Money::add(Money::sub($subtotal, $discount), $fee ?? '0.00');

        $threshold = (string) setting('free_shipping_threshold', 3000);
        $goodsTotal = Money::sub($subtotal, $discount);
        $progress = Money::compare($threshold, '0') > 0 ? min(1.0, (float) bcdiv($goodsTotal, $threshold, 4)) : 0.0;

        return view('livewire.checkout-page', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon' => $coupon,
            'fee' => $fee,
            'total' => $total,
            'rates' => ShippingRate::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'methods' => $this->enabledMethods(),
            'bankDetails' => (string) setting('bank_transfer_details', ''),
            'progress' => $progress,
            'remainingFormatted' => Money::format($shipping->remainingForFreeShipping($subtotal, $discount)),
            'threshold' => $threshold,
            'selectedRate' => $rate,
        ])->layout('layouts.app', [
            'title' => __('store.checkout'),
            'robots' => 'noindex,follow',
            'gaEvents' => [Analytics::beginCheckout($lines, $total)],
        ]);
    }
}
