# Phase 7 Report — Customer Account, Wishlist, Reviews, Order Tracking

Spec: PROJECT_SPEC.md §4.7, §4.9, §6.8, §8, §11.8 (M64, M73, M80) · Prompt: PHASE_PROMPTS.md Phase 7

## What was built

- **Auth** (`App\Livewire\Auth\LoginPage` / `RegisterPage`): a single
  identifier field accepting an Egyptian phone (normalized via `Phone`,
  incl. +20 / Arabic-Indic forms) or an email, plus password. Login is
  rate-limited (5 attempts/min per identifier+IP) with a generic Arabic
  error; registration validates uniqueness of the normalized phone/email
  and logs the user in. No OTP, no social login. Password reset is left
  to Laravel's default email flow (only usable when mail is configured).
  Header account icon now points to the account area when logged in.
- **Account area** (auth middleware; every query scoped to the user):
  - `/account/orders` and `/account/orders/{order_number}` — list and
    detail with item snapshots, totals, tracking number and the **M64
    circuit timeline** (`<x-order-timeline>`: lit wire + nodes, current
    step gets a one-time finite `animate-pop`, never an infinite pulse;
    cancelled/returned render as a terminal badge). Another user's order
    number returns 404.
  - Customer cancel: only own orders in status `new`, via
    `OrderService::changeStatus`; other states show an Arabic error toast.
  - `/account/profile` — name/phone/email with uniqueness checks and a
    password change that verifies the current password.
  - `/account/addresses` — CRUD; a transaction unsets the previous
    default when a new default is set, so at most one default per user.
    Checkout prefill from the default address already existed (Phase 5B)
    and now has real data to prefill from.
  - `/account/wishlist` — product cards; empty state uses the **M73**
    `empty-wishlist` electric scene.
- **Wishlist** (`WishlistService` + `WishlistButton`): heart on product
  cards (top-end, outside the card link) and account page toggling;
  **M80** finite heart pop with fill change, motion-gated; guests are
  redirected to login.
- **Reviews** (`ReviewService` + `ReviewForm` on the product page):
  auth required, one per user/product (unique rule enforced in the
  service), always `pending` on submission, `is_verified_purchase` true
  only when the user has a delivered order containing the product.
  Aggregates count approved reviews only. **Filament Review resource**
  with pending-count badge, approve/reject record + bulk actions.
- **Guest tracking** `/track-order`: order number + normalized phone
  must both match exactly; any mismatch shows the same generic Arabic
  message (no existence leakage); rate-limited (10/min/IP). Guest
  orders are never attached to an account by phone — account lists show
  only `user_id`-linked orders (tested).

## Tests (exact results)

- `php artisan test` → **164 passed** (595 assertions), including 14 new
  Phase 7 tests in `tests/Feature/CustomerAccountTest.php`: phone login
  (normalized) + email login, wrong-password generic error, registration
  + duplicate rejection, auth-required account pages, cross-user order /
  address 404s, cancel-only-when-new, guest-order non-attachment,
  tracking exact-match + generic mismatch, review pending / verified /
  duplicate / approved-only aggregates + product-page display, Filament
  moderation approve action, single-default addresses, wishlist toggle
  + guest redirect + M73 empty scene, M64 timeline rendering.
- `vendor/bin/pint` applied to touched files; `npm run build` succeeds.

## NOT RUN / notes

- Password-reset email delivery: NOT RUN (no mail server configured in
  dev; Laravel's default flow is not wired to custom pages in v1 scope —
  login/register are the custom surfaces per spec).
- Real-device / browser QA of M64/M73/M80: NOT RUN (covered by markup
  and state assertions; motion is CSS-variant gated as in Phase 1C).
