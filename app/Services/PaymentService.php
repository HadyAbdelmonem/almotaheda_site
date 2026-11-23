<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

/**
 * Payment attempts (spec §4.8). Phase 1B provides attempt creation only.
 * Paymob callback verification (HMAC, idempotency, reconciliation) is
 * Phase 10 and must never mark a payment paid from a browser redirect.
 */
class PaymentService
{
    /** True only when every Paymob credential/integration id is configured. */
    public function paymobConfigured(): bool
    {
        foreach (['paymob.api_key', 'paymob.hmac_secret', 'paymob.integration_id', 'paymob.iframe_id'] as $key) {
            if (trim((string) config($key, '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Card payment is offered only when the owner enabled it in
     * settings AND the Paymob configuration is complete (spec §4.8).
     * With no merchant account (owner decision, 2026-11-23) this is
     * always false in v1.
     */
    public function cardAvailable(): bool
    {
        return (bool) setting('payment_card_enabled', false) && $this->paymobConfigured();
    }

    public function createAttempt(Order $order, string $provider): PaymentTransaction
    {
        return PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => $provider,
            'provider_transaction_id' => null,
            'merchant_reference' => 'PAY-'.$order->id.'-'.Str::upper(Str::random(10)),
            'amount' => $order->total,
            'currency' => 'EGP',
            'status' => PaymentTransactionStatus::Pending,
            'is_success' => false,
            'reconciliation_status' => 'not_required',
        ]);
    }
}
