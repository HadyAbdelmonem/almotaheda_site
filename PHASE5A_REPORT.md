# Phase 5A Report — Cart, pricing revalidation and cart animation

Spec: §4.2–§4.6, §4.11, §6.6, §11.8 (M14, M43, M50–M53).

## What was built
- **CartService (rewritten)**: the session stores only `product_id`, `variant_id` and the decimal quantity string.
  - `addItem()` — full server validation: active product/variant, `QuantityService` min/step/max rules, max-order cap, and clamping of the resulting line quantity to available stock; out-of-stock/inactive products throw the new `App\Exceptions\CartException` carrying a `store.*` Arabic error key.
  - `updateQuantity()` — same validation/clamping for steppers.
  - `resolvedLines()` — every line is resolved against current DB state on every render: product/variant availability, quantity-rule fallback, stock clamping, fresh `PricingService` pricing (sale + tier at the effective quantity) and warning keys (`cart_warning_unavailable`, `cart_warning_out_of_stock`, `cart_warning_stock_clamped`, `cart_warning_quantity_adjusted`). Non-purchasable lines are excluded from `subtotal()`.
- **Add-to-cart wiring**: product page (main button + mobile sticky bar) via `ProductPage::addToCart()`, and product cards via the new standalone `AddToCartButton` Livewire component (variant products link to the product page to choose a variant). `cart-added` is dispatched **only after** `CartService` stored the line — never optimistically — followed by `cart-updated` and a toast that now supports an action ("عرض السلة").
- **Header badge (M14)**: new `CartCount` Livewire component re-renders the E14 cart icon on `cart-updated`; fly-to-cart (M43) and the ring/pop/spark feedback were already in `fly-to-cart.js` and now actually fire.
- **/cart page** (`CartPage` Livewire): lines with image, variant, unit price + unit label, steppers, remove; stale-line warnings in Arabic; coupon apply/remove through `CouponService` (case-insensitive, throttled 10/min/IP, Arabic error per failure reason, M52 shake on error + spark on success); summary with subtotal/discount/total; E11 cable free-shipping bar driven by **post-coupon** goods total, hidden when the threshold is 0, shipping shown as "يُحسب عند إتمام الطلب" (no governorate in the cart). M51: auto-animate on the stable-`wire:key` line list + opacity-only totals flash. M53: E16 empty-cart scene with CTA.
- The homepage product carousel no longer wraps cards in `wire:ignore`, so the nested add-to-cart components stay fully interactive there (Swiper without loop mode does not clone slides).

## Verification (exact results)
- `php artisan test` → **111 passed** (400 assertions), up from 96. New: `tests/Feature/CartTest.php` (15 tests) — session purity (ids+qty only), fractional meter pricing, step rejection, stock clamping, out-of-stock/inactive rejection, tier recalculation, stale/deactivated/clamped lines, variant rules, percent coupon with cap, fixed + free-shipping coupons, min-order rejection, free-shipping boundary on (subtotal − discount), `cart-added` dispatched only after success, card button, empty-cart scene.
- `vendor/bin/pint --test` → PASS (after `vendor/bin/pint`).
- `npm run build` → PASS.

## NOT RUN / notes
- Browser verification of fly-to-cart arc, badge pop and coupon spark — NOT RUN (no browser in this environment); the JS modules were built in Phase 1C and are now wired to real server-confirmed events.
- Final coupon race-safety (usage_limit under concurrency) belongs to the order transaction in Phase 5B; cart-level validation is advisory, exactly as §4.3 requires.
