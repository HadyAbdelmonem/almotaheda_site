# Phase 6 Report — Admin Orders, Stock Disposition, Invoice, Dashboard

Spec: PROJECT_SPEC.md §4.6–4.8, §5, §7, §7.2, §8 · Prompt: PHASE_PROMPTS.md Phase 6

## What was built

- **OrderService (verified + completed)**: `changeStatus()` was already the sole
  transition path with the exact §4.7 graph, history in the same transaction,
  cancel-before-shipping stock restore + one-time coupon release
  (`coupon_released_at`), cancel-after-shipping / return → `pending_inspection`
  with no automatic restock, delivered → COD paid + `sales_count` increment,
  returned → one-time reversal via `sales_reversed_at`. Fixed a real bug found
  by the new tests: the reversal floor-to-zero called `QuantityService::sub`
  with a would-be negative result, which the service rejects — the floor is
  now applied before subtracting.
- **OrderService::resolveStockDisposition()** (already present, now fully
  tested): admin + mandatory note, `restored` restocks once with an
  `order_returned` movement and sets `stock_restored_at`; `written_off`
  changes no stock; second call is an idempotent no-op.
- **OrderService::markBankTransferPaid()** (new): bank-transfer only, audit
  note required, sets `payment_status=paid` + `payment_verified_at` and writes
  an audit row into the status history. COD (paid only on delivery) and card
  (paid only via verified callback) are rejected.
- **New-order admin notification**: after the order transaction commits,
  every admin receives a Filament database notification (new `notifications`
  table migration). Best-effort try/catch — it can never fail checkout. No mail.
- **Filament Orders resource** (`App\Filament\Resources\Orders`): Arabic labels,
  navigation group "الطلبات", badge with the count of `new` orders. Table with
  status/payment-method/payment-status/date filters and search by number,
  name and phone. Record + view-page actions: change status (only allowed
  transitions offered, optional note), WhatsApp (wa.me with normalized phone
  and Arabic confirmation prefill), print invoice, mark bank transfer paid
  (audit note form), resolve pending stock disposition (decision + mandatory
  note). View page infolist: order data, customer/address, immutable item
  snapshots with unit quantities, totals, shipping/tracking/admin notes,
  stock disposition, payment attempts, full status history. Edit page edits
  only shipping company / tracking number / admin notes.
- **Invoice** (`GET /orders/{order}/invoice`, auth + admin only): printable
  A4 RTL page built purely from order snapshots, `@media print` with all
  motion disabled, explicitly labelled a commercial receipt and "ليس فاتورة
  ضريبية إلكترونية" — never a tax e-invoice.
- **Dashboard widgets**: `OrdersStatsWidget` (today's orders, today's revenue,
  new orders, pending bank transfers), `LowStockWidget` (effective per-product
  threshold), `TopProductsWidget` (top 5 of the last 30 days counting only
  currently-delivered orders, i.e. net of returns), `PendingBankTransfersWidget`
  (aging pending transfers; no automatic expiry per §4.10).

## Tests (exact results)

- `php artisan test` → **150 passed** (525 assertions), including 26 new
  Phase 6 tests in `tests/Feature/OrderAdminTest.php`: all allowed transitions
  + 16 forbidden ones, cancel timing (stock/coupon), disposition restored /
  written_off / idempotency / mandatory note, sales increment + reversal once,
  paid-order reconciliation flag, bank-paid audit rules, invoice snapshot
  integrity after a product price/name change + non-admin 403, admin DB
  notification on order creation, orders list + badge + panel permission.
- `vendor/bin/pint` applied to all touched files; `npm run build` succeeds.

## NOT RUN / notes

- MySQL concurrency tests: NOT RUN (dev/test use SQLite, per project setup).
- Browser/manual print preview of the invoice: NOT RUN (covered by HTTP
  content assertions instead).
- Guest redirect-to-login for the invoice route is asserted in Phase 7, where
  the named `login` route is created.
