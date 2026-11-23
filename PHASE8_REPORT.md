# Phase 8 Report — Coupons, Shipping, Settings and Remaining Admin

Spec: PROJECT_SPEC.md §4.1, §4.3, §4.4, §4.10, §7 · Prompt: PHASE_PROMPTS.md Phase 8

## What was built

- **Coupon resource** (group التسويق): type / value / min_order /
  max_discount / usage_limit / dates / active toggle. `used_count` is
  displayed read-only (it moves only inside order transactions).
  Codes are normalized (trim + uppercase) on create and edit.
- **ShippingRate resource** (group الإعدادات): the 27 seeded
  governorates are editable (fee, active toggle, est. days, sort);
  create/delete are disabled since the governorate set is fixed.
  The form labels the seeded fees as placeholders to replace.
- **Banner resource** (group المحتوى): hero/promo position, desktop +
  mobile image uploads (public disk, ≤4MB, image preview), link,
  sort, scheduling dates, active toggle. `Banner::imageUrl()` resolves
  uploaded paths for the storefront; the home hero now uses it.
- **Review resource** was built in Phase 7 (moderation + bulk actions).
- **Customer resource** (group الطلبات): read-only list of non-admin
  users with order counts; view page shows customer data and linked
  orders. Create/edit/delete are disabled.
- **Settings page** (`ManageSettings`, group الإعدادات): tabs for
  Store, Contact, Shipping, Payment, Homepage, Social (inside Contact),
  Appearance and Analytics covering **every §4.1 key**:
  store identity/logo/address/email/tax number + tax note, WhatsApp
  number, phones (tags), map embed, social links (key/value),
  free-shipping threshold, delivery estimate text, return days,
  low-stock default, COD/bank/card toggles + bank transfer details,
  topbar text, `why_us_blocks` repeater with the `config/icons.php`
  whitelist select, `motion_level` (full/lite/off with Arabic
  explanations), GA4 ID, Search Console verification and
  `catalog_filter_max_options`. Saving writes the settings table and
  forgets each `setting.{key}` cache entry; numeric settings are
  normalized on save.
- **Sidebar order**: the panel now declares navigation groups in the
  spec order — الطلبات، الكتالوج، التسويق، المحتوى، الإعدادات
  (Orders, Catalog, Marketing, Content, Settings).

## Hardcoded-data audit (grep, exact)

Commands run over `resources/views`, `app/Livewire`, `app/Support`:

- Egyptian phone pattern `01[0125][0-9]{8}` / international numbers in
  storefront views: **no matches** (all numbers come from settings).
- Bank / InstaPay literals: **no matches** (bank details render only
  from `bank_transfer_details` setting).
- `3000` appears only as the documented default fallback argument of
  `setting('free_shipping_threshold', 3000)` in the free-shipping
  components, CartPage and CheckoutPage — the live value always comes
  from the settings table.
- The unused stock Laravel `welcome.blade.php` scaffold was removed.

## Tests (exact results)

- `php artisan test` → **172 passed** (636 assertions), including 8 new
  Phase 8 tests in `tests/Feature/AdminSettingsTest.php`: settings page
  save + cache invalidation, `motion_level` reflected in the layout
  `data-motion` attribute, free-shipping threshold change affecting
  cart progress AND checkout shipping fee (0.00 at the new threshold),
  disabled governorate removed from checkout and rejected on submit,
  coupon admin create (code normalization) + validation, 27 shipping
  rows + fee edit reaching ShippingService, WhatsApp float hidden/shown
  by setting, customers resource read-only + non-admin scoping.
- `vendor/bin/pint` applied to touched files; `npm run build` succeeds.

## NOT RUN / notes

- Real store data (name, phones, WhatsApp, real fees, bank details)
  has NOT been provided by the owner yet — all settings remain the
  seeded placeholders, editable in `/admin` → إعدادات المتجر, exactly
  as the spec requires (nothing is hardcoded).
- Browser/manual admin UI QA: NOT RUN (covered by Livewire component
  tests for every new page/resource).
- Phase 10 (Paymob) intentionally not started — card toggle stays off
  and the storefront keeps card payment hidden until Phase 10.
