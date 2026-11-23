<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Paymob Accept (classic iFrame/hosted) integration, spec §4.8.
 *
 * - All HTTP goes through Laravel's Http client (fakeable in tests).
 * - Credentials come from config/paymob.php (env only).
 * - The ONLY path that can mark a card payment paid is the verified
 *   server-to-server callback (processCallback). Browser returns are
 *   display-only.
 * - v1 state: the owner has NO Paymob merchant account, so card
 *   payment stays disabled; this code is exercised only by mocked
 *   tests and must never be pointed at the real API without
 *   credentials and a verified test-mode callback.
 */
class PaymobService
{
    /** Canonical HMAC field order for the transaction callback (Paymob docs). */
    private const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured',
        'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
        'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type',
        'success',
    ];

    public function __construct(private readonly PaymentService $payments) {}

    private function log(): LoggerInterface
    {
        return Log::channel('payments');
    }

    /**
     * Create a payment attempt and return the hosted iFrame URL.
     * Flow: auth token -> order registration -> payment key -> iframe.
     *
     * @return array{transaction: PaymentTransaction, iframe_url: string}
     */
    public function createPaymentAttempt(Order $order): array
    {
        if (! $this->payments->paymobConfigured()) {
            throw new RuntimeException('Paymob is not configured.');
        }

        $transaction = $this->payments->createAttempt($order, 'paymob');
        $amountCents = (int) bcmul(Money::of((string) $order->total), '100', 0);
        $base = rtrim((string) config('paymob.base_url'), '/');

        $auth = Http::acceptJson()->post($base.'/auth/tokens', [
            'api_key' => (string) config('paymob.api_key'),
        ]);
        $auth->throw();
        $authToken = (string) $auth->json('token');

        $registration = Http::acceptJson()->post($base.'/ecommerce/orders', [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => 'EGP',
            'merchant_order_id' => $transaction->merchant_reference,
            'items' => [],
        ]);
        $registration->throw();
        $paymobOrderId = $registration->json('id');

        $names = preg_split('/\s+/', trim((string) $order->customer_name)) ?: [];
        $paymentKey = Http::acceptJson()->post($base.'/acceptance/payment_keys', [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'currency' => 'EGP',
            'integration_id' => (int) config('paymob.integration_id'),
            'billing_data' => [
                'first_name' => $names[0] ?? 'Customer',
                'last_name' => $names[1] ?? 'Customer',
                'phone_number' => (string) $order->phone,
                'email' => $order->email ?: 'na@example.com',
                'street' => (string) $order->address,
                'city' => (string) $order->city,
                'country' => 'EG',
                'state' => (string) $order->governorate,
                'postal_code' => '00000',
                'building' => 'NA',
                'floor' => 'NA',
                'apartment' => 'NA',
            ],
        ]);
        $paymentKey->throw();
        $token = (string) $paymentKey->json('token');

        $transaction->payload_redacted = ['paymob_order_id' => $paymobOrderId];
        $transaction->save();

        $order->payment_provider = 'paymob';
        $order->provider_reference = $transaction->merchant_reference;
        $order->payment_expires_at = now()->addMinutes(60);
        $order->save();

        $this->log()->info('paymob attempt created', [
            'order_number' => $order->order_number,
            'merchant_reference' => $transaction->merchant_reference,
            'paymob_order_id' => $paymobOrderId,
            'amount_cents' => $amountCents,
        ]);

        return [
            'transaction' => $transaction,
            'iframe_url' => 'https://accept.paymob.com/api/acceptance/iframes/'
                .config('paymob.iframe_id').'?payment_token='.$token,
        ];
    }

    /** Verify the callback HMAC (SHA-512, constant-time comparison). */
    public function verifyHmac(array $obj, string $providedHmac): bool
    {
        $secret = (string) config('paymob.hmac_secret', '');
        if ($secret === '' || $providedHmac === '') {
            return false;
        }

        $concatenated = '';
        foreach (self::HMAC_FIELDS as $field) {
            $value = $this->fieldValue($obj, $field);
            $concatenated .= is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
        }

        $calculated = hash_hmac('sha512', $concatenated, $secret);

        return hash_equals(strtolower($calculated), strtolower($providedHmac));
    }

    private function fieldValue(array $obj, string $field): mixed
    {
        $value = $obj;
        foreach (explode('.', $field) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Remove anything sensitive before a payload is stored or logged:
     * card PAN, tokens, keys, secrets — recursively, by key name.
     */
    public function redact(array $payload): array
    {
        $sensitive = ['pan', 'token', 'api_key', 'apikey', 'hmac', 'secret', 'password', 'card_number', 'cvv'];
        $clean = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            $isSensitive = false;
            foreach ($sensitive as $needle) {
                if (str_contains($lower, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }
            if ($isSensitive) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $clean;
    }

    /**
     * Process a verified transaction callback. Returns an outcome:
     * ['http' => int, 'result' => 'paid'|'failed'|'duplicate'|'rejected'].
     *
     * Rules (spec §4.8): idempotent per provider transaction; a late
     * success on a cancelled order is recorded as paid and flagged
     * for reconciliation but never revives the order or touches
     * stock; a second distinct successful attempt on an already-paid
     * order is recorded and flagged, totals/stock untouched.
     */
    public function processCallback(array $obj, string $hmac): array
    {
        if (! $this->verifyHmac($obj, $hmac)) {
            $this->log()->warning('paymob callback rejected: invalid HMAC', [
                'provider_transaction_id' => $obj['id'] ?? null,
            ]);

            return ['http' => 403, 'result' => 'rejected'];
        }

        $merchantReference = (string) ($obj['order']['merchant_order_id'] ?? '');
        $attempt = PaymentTransaction::query()
            ->where('provider', 'paymob')
            ->where('merchant_reference', $merchantReference)
            ->first();

        if ($attempt === null) {
            $this->log()->warning('paymob callback rejected: unknown merchant reference', [
                'merchant_reference' => $merchantReference,
            ]);

            return ['http' => 422, 'result' => 'rejected'];
        }

        $amountCents = (int) ($obj['amount_cents'] ?? -1);
        $expectedCents = (int) bcmul(Money::of((string) $attempt->amount), '100', 0);
        if ($amountCents !== $expectedCents || (string) ($obj['currency'] ?? '') !== 'EGP') {
            $this->log()->warning('paymob callback rejected: amount/currency mismatch', [
                'merchant_reference' => $merchantReference,
                'amount_cents' => $amountCents,
                'expected_cents' => $expectedCents,
                'currency' => $obj['currency'] ?? null,
            ]);

            return ['http' => 422, 'result' => 'rejected'];
        }

        return DB::transaction(function () use ($obj, $attempt) {
            $order = Order::query()->lockForUpdate()->findOrFail($attempt->order_id);
            $lockedAttempt = PaymentTransaction::query()->lockForUpdate()->findOrFail($attempt->id);
            $providerTxnId = (string) ($obj['id'] ?? '');

            // Idempotency: this provider transaction was already fully
            // processed for this attempt — acknowledge, change nothing.
            if ($lockedAttempt->provider_transaction_id === $providerTxnId && $lockedAttempt->processed_at !== null) {
                return ['http' => 200, 'result' => 'duplicate'];
            }
            if ($lockedAttempt->provider_transaction_id !== null && $lockedAttempt->provider_transaction_id !== $providerTxnId) {
                $this->log()->warning('paymob callback rejected: attempt already bound to another transaction', [
                    'merchant_reference' => $lockedAttempt->merchant_reference,
                ]);

                return ['http' => 422, 'result' => 'rejected'];
            }

            $success = ($obj['success'] ?? false) === true || ($obj['success'] ?? null) === 'true';

            $lockedAttempt->provider_transaction_id = $providerTxnId;
            $lockedAttempt->payload_redacted = $this->redact($obj);
            $lockedAttempt->processed_at = now();

            if (! $success) {
                $lockedAttempt->status = PaymentTransactionStatus::Failed;
                $lockedAttempt->is_success = false;
                $lockedAttempt->failure_reason = (string) ($obj['data']['message'] ?? 'payment_failed');
                $lockedAttempt->save();
                $this->log()->info('paymob callback: payment failed', [
                    'order_number' => $order->order_number,
                    'merchant_reference' => $lockedAttempt->merchant_reference,
                ]);

                return ['http' => 200, 'result' => 'failed'];
            }

            $lockedAttempt->status = PaymentTransactionStatus::Paid;
            $lockedAttempt->is_success = true;

            if ($order->payment_status === PaymentStatus::Paid) {
                // Second distinct successful payment for an order that
                // is already paid: record it truthfully and flag a
                // duplicate-payment reconciliation/refund. Totals and
                // stock are never touched here.
                $lockedAttempt->reconciliation_status = 'pending';
                $lockedAttempt->save();
                $order->payment_reconciliation_required = true;
                $order->save();
                $this->log()->warning('paymob callback: duplicate successful payment flagged for reconciliation', [
                    'order_number' => $order->order_number,
                    'merchant_reference' => $lockedAttempt->merchant_reference,
                    'provider_transaction_id' => $providerTxnId,
                ]);

                return ['http' => 200, 'result' => 'paid'];
            }

            $wasCancelled = $order->status === OrderStatus::Cancelled;
            $order->payment_status = PaymentStatus::Paid;
            $order->payment_verified_at = now();
            $order->payment_provider = 'paymob';
            $order->provider_reference = $providerTxnId;
            if ($wasCancelled) {
                // Late success after cancellation/expiry: the payment
                // is real, so record it — but the order stays
                // cancelled, stock is not re-deducted or re-restored,
                // and an admin must reconcile/refund externally.
                $order->payment_reconciliation_required = true;
                $lockedAttempt->reconciliation_status = 'pending';
                $this->log()->warning('paymob callback: late success on cancelled order flagged for reconciliation', [
                    'order_number' => $order->order_number,
                    'provider_transaction_id' => $providerTxnId,
                ]);
            }
            $lockedAttempt->save();
            $order->save();
            $this->log()->info('paymob callback: payment recorded as paid', [
                'order_number' => $order->order_number,
                'provider_transaction_id' => $providerTxnId,
                'late_on_cancelled' => $wasCancelled,
            ]);

            return ['http' => 200, 'result' => 'paid'];
        });
    }
}
