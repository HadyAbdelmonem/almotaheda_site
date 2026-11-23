# Phase 10 Report — Paymob Card Payment & Bank-Transfer Receipt

## ⚠️ Merchant account status (owner decision)
**The owner confirmed there is NO Paymob merchant account.** Therefore:
- `payment_card_enabled` remains **false**; v1 payment methods are **Cash on Delivery + bank transfer only**.
- The full Paymob integration below is implemented and exercised **only with HTTP-mocked tests** (`Http::fake`). No real Paymob API call was made, no credentials exist, and none were invented.
- Card payment can be enabled later only after: a merchant account exists → credentials are placed in `.env` → a test-mode callback is verified → the owner enables the setting. The code enforces this: the card option renders only when the setting is on **AND** all Paymob config values are present (`PaymentService::cardAvailable()`).

## What was built

### Paymob adapter (`app/Services/PaymobService.php`, `config/paymob.php`)
- Classic Accept flow over Laravel's Http client: auth token → order registration (`merchant_order_id` = the attempt's `merchant_reference`, amount in cents) → payment key → hosted iFrame URL (`/payments/paymob/pay/{orderNumber}` interstitial page).
- Config from env only: `PAYMOB_API_KEY`, `PAYMOB_HMAC_SECRET`, `PAYMOB_INTEGRATION_ID`, `PAYMOB_IFRAME_ID`, `PAYMOB_MERCHANT_ID` (blank in `.env.example`).
- Dedicated `payments` log channel (daily, 30 days). All stored/logged payloads pass through a recursive `redact()` that strips PAN/token/key/secret fields; the PAN from the callback is never persisted (asserted by test).

### Callback (`POST /payments/paymob/callback`)
- CSRF-exempt for **this exact route only** (`bootstrap/app.php`); authenticated by HMAC instead.
- HMAC-SHA512 over Paymob's documented canonical field order (amount_cents, created_at, currency, error_occured, has_parent_transaction, id, integration_id, is_3d_secure, is_auth, is_capture, is_refunded, is_standalone_payment, is_voided, order.id, owner, pending, source_data.pan, source_data.sub_type, source_data.type, success), verified with `hash_equals`.
- Validates merchant reference → `payment_transactions.merchant_reference`, `amount_cents` == attempt amount × 100, currency `EGP`; processes inside a DB transaction with `lockForUpdate` on order + attempt.
- Idempotent: a duplicate callback for an already-processed provider transaction is a 200 no-op.
- Success → order + transaction `paid`. Failure → transaction `failed`, order stays pending.
- **Late success on a cancelled order**: recorded as `paid` + `payment_reconciliation_required`, order status NOT revived, stock NOT re-deducted or re-restored.
- **Second distinct successful attempt on an already-paid order**: recorded as paid, attempt + order flagged for duplicate-payment reconciliation; totals/stock untouched.
- Browser return page (`/payments/paymob/return/{orderNumber}`) is **display-only** — it reads payment state and never mutates it.

### Checkout integration
- `CheckoutPage::enabledMethods()` now uses `PaymentService::cardAvailable()` (setting AND config). When card is chosen, `OrderService::create` runs as usual, then the attempt is created and the customer is redirected to the iFrame page; an attempt-creation failure is logged and falls back to the success page with the order pending (the expiry flow then applies) — a payment is never faked.

### Expiry (scheduler)
- `payments:expire-card-orders` artisan command, scheduled **hourly** in `routes/console.php`: cancels unpaid card orders older than 60 minutes through `OrderService::changeStatus` (stock restored exactly once, coupon released once), marks attempts `expired` and the order payment `failed`. It re-checks under lock that no paid transaction exists (callback/expiry race). **Bank-transfer orders never auto-expire** (asserted by test, including idempotency on re-run).

### Bank-transfer receipt
- Upload (`POST /orders/{orderNumber}/receipt`): image ≤ 4 MB (jpg/png/webp, MIME + image validation), stored on the **private** local disk under `receipts/{order_number}/…` with a UUID name; path saved to `orders.payment_proof` (already `$hidden`). Access: the order's authenticated owner, an admin, the success-page session, or a guest presenting the exact order phone (same proof as order tracking). Forms added to the guest track-order result and the account order detail.
- Admin viewing: `GET /admin/orders/{order}/receipt` (auth + `is_admin`, streamed from the private disk) plus a «عرض إيصال التحويل» action on the Filament order view page, visible only when a receipt exists.
- A receipt **never** changes payment status (asserted by test); only the Phase 6 manual mark-paid flow (with audit note) can do that.

## Tests — exact results
- `php artisan test --filter=Paymob` → **13 passed (67 assertions)**: card availability matrix (setting/config), classic Accept attempt flow over `Http::fake` (amount_cents + merchant reference asserted on the wire), valid HMAC pays once + duplicate no-op + PAN redaction, invalid HMAC → 403, wrong amount/currency/unknown reference → 422, failed callback, late success after cancel (paid + flagged, no revival, stock untouched), second distinct success (recorded + flagged, totals/stock untouched), expiry command (card cancelled once, attempts expired, bank order untouched, re-run idempotent), checkout card flow (attempt created, redirect to hosted page), receipt upload by tracked guest (private disk only, status unchanged), receipt upload rejected for wrong phone/stranger, receipt view admin-only.
- `php artisan test` (full suite) → **205 passed (823 assertions)**.
- `vendor/bin/pint` applied to all touched PHP files.

## NOT RUN
- **Merchant account verification: NOT RUN — by owner decision there is no account** (see header). Card stays disabled.
- **Live/test-mode Paymob callback against the real provider: NOT RUN** — no account or credentials; the integration is verified exclusively against signed, HTTP-mocked callbacks.
- **Real expiry/callback race on MySQL: NOT RUN** — locking is implemented with `lockForUpdate` and tested logically on SQLite; re-verify on MySQL 8 before go-live (Phase 11 pre-launch step).
