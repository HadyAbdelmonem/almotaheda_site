<?php

namespace App\Livewire;

use App\Exceptions\CartException;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\QuantityService;
use App\Services\ShippingService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Cart page (spec §6.6). Every render recomputes prices, sale/tier
 * rules, stock and totals from the database via CartService; the
 * session only ever holds ids + decimal quantities.
 */
class CartPage extends Component
{
    public string $couponInput = '';

    public ?string $couponError = null;

    public bool $couponJustApplied = false;

    public ?string $lineError = null;

    public function increment(int $productId, ?int $variantId): void
    {
        $this->step($productId, $variantId, 1);
    }

    public function decrement(int $productId, ?int $variantId): void
    {
        $this->step($productId, $variantId, -1);
    }

    private function step(int $productId, ?int $variantId, int $direction): void
    {
        $cart = app(CartService::class);
        $qty = app(QuantityService::class);
        foreach ($cart->items() as $item) {
            if ($item['product_id'] !== $productId || $item['variant_id'] !== $variantId) {
                continue;
            }
            $product = Product::query()->find($productId);
            if ($product === null) {
                return;
            }
            try {
                $candidate = $direction > 0
                    ? $qty->add($item['quantity'], (string) $product->quantity_step)
                    : $qty->sub($item['quantity'], (string) $product->quantity_step);
                if ($qty->compare($candidate, (string) $product->min_order_quantity) < 0) {
                    return; // At the minimum: the stepper does not go below it.
                }
                $cart->updateQuantity($productId, $variantId, $candidate);
                $this->lineError = null;
            } catch (CartException $e) {
                $this->lineError = __('store.'.$e->errorKey);
            }

            return;
        }
    }

    public function setQuantity(int $productId, ?int $variantId, string $quantity): void
    {
        try {
            app(CartService::class)->updateQuantity($productId, $variantId, $quantity);
            $this->lineError = null;
        } catch (CartException $e) {
            $this->lineError = __('store.'.$e->errorKey);
        }
    }

    public function removeLine(int $productId, ?int $variantId): void
    {
        app(CartService::class)->remove($productId, $variantId);
        $this->dispatch('cart-updated');
    }

    public function applyCoupon(CartService $cart, CouponService $coupons): void
    {
        $this->couponError = null;
        $this->couponJustApplied = false;

        $key = 'coupon-apply:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->couponError = __('store.form_throttled');

            return;
        }
        RateLimiter::hit($key, 60);

        $code = trim($this->couponInput);
        if ($code === '') {
            $this->couponError = __('store.coupon_not_found');

            return;
        }

        $lines = $cart->resolvedLines();
        $subtotal = $cart->subtotal($lines);
        $coupon = $coupons->findByCode($code);
        $error = $coupons->validate($coupon, $subtotal);
        if ($error !== null) {
            $this->couponError = __('store.coupon_'.$error);

            return;
        }

        $cart->setCouponCode($coupons->normalizeCode($code));
        $this->couponInput = '';
        $this->couponJustApplied = true;
        $this->dispatch('toast', message: __('store.coupon_applied'));
    }

    public function removeCoupon(CartService $cart): void
    {
        $cart->setCouponCode(null);
        $this->couponError = null;
        $this->couponJustApplied = false;
    }

    public function render(CartService $cart, CouponService $coupons, ShippingService $shipping): View
    {
        $lines = $cart->resolvedLines();
        $subtotal = $cart->subtotal($lines);

        $coupon = null;
        $discount = '0.00';
        $couponError = $this->couponError;
        $code = $cart->couponCode();
        if ($code !== null) {
            $candidate = $coupons->findByCode($code);
            $error = $coupons->validate($candidate, $subtotal);
            if ($error === null) {
                $coupon = $candidate;
                $discount = $coupons->discount($candidate, $subtotal);
            } else {
                // The stored coupon no longer validates (expired, limit
                // reached, subtotal dropped): show why and ignore it.
                $couponError ??= __('store.coupon_'.$error);
            }
        }

        $threshold = (string) setting('free_shipping_threshold', 3000);
        $goodsTotal = Money::sub($subtotal, $discount);
        $progress = 0.0;
        if (Money::compare($threshold, '0') > 0) {
            $progress = min(1.0, (float) bcdiv($goodsTotal, $threshold, 4));
        }
        $remaining = $shipping->remainingForFreeShipping($subtotal, $discount);

        return view('livewire.cart-page', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon' => $coupon,
            'couponError' => $couponError,
            'total' => $goodsTotal,
            'progress' => $progress,
            'remainingFormatted' => Money::format($remaining),
            'threshold' => $threshold,
        ])->layout('layouts.app', ['title' => __('store.cart'), 'robots' => 'noindex,follow']);
    }
}
