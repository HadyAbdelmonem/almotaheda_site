# Phase 3A Report — Product cards and product detail page

Spec sections: §4.2, §4.6, §4.11–4.12, §6.2, §8, §10, §11.8 (M30–M32, M40–M45). Phase prompt: `docs/PHASE_PROMPTS.md` Phase 3A.

## What was built

- **Shared components** (`resources/views/components/`): `product-card` (§10.5 anatomy, M30 hover lift/image scale), `price` (unit price + struck regular + per-unit Arabic label + "تبدأ من"), `breadcrumbs`, `trust-block` (why-us blocks from `why_us_blocks` setting, whitelist icons), `whatsapp-button` (`wa.me/{number}?text={urlencoded}`, hidden when the number is empty), `rating-stars`, `placeholder-image` (tinted background + category electric icon, never a gray box), `pagination` (RTL), `toast` (M06, mounted once in the layout), `free-shipping-strip` (M45, hidden when threshold = 0). E09 sale badge / E10 stock badge from Phase 1C are reused, not duplicated.
- **`App\Support\ProductDisplay`**: single place for available stock, stock state (`in/low/out` per §4.6), card pricing (cheapest active variant at min quantity for "starts from"), quantity formatting and per-unit labels.
- **Product page** (`App\Livewire\ProductPage`, class-based, route `products.show` `/products/{slug}`), mobile-first in the §6.2 order: breadcrumbs → Swiper gallery (Thumbs + Zoom + Pagination, `wire:ignore`, lazily loaded `resources/js/gallery.js`, Alpine-era init on `DOMContentLoaded` + `livewire:navigated`) → name/model/brand + authorized-dealer badge → price block (PricingService sale logic, variant-aware) + stock badge → variant chips updating price/sale/stock/SKU/image → technical attributes table (`CatalogAttributeService` inherited attributes + `product_attribute_values`, plus descriptive `specs`) → quantity-tier table with the active tier highlighted (M42) → unit-aware quantity stepper (QuantityService min/step/max, clamped to stock) → add-to-cart **disabled placeholder** (Phase 5A wires it; no fake success, with an explanatory note) → WhatsApp order button pre-filled with product/model/variant/SKU/quantity+unit/unit price/page URL (out-of-stock switches to "اسأل عن التوفر") → red free-shipping strip → delivery estimate from settings → description → why-us → approved reviews with average → related products (same category, same brand first, max 8) → mobile sticky buy bar (`sticky-bar.js`, M44).
- Primary gallery image is `loading="eager" fetchpriority="high"` with explicit dimensions; other images lazy. No `cost_price` (or any admin field) is rendered: product/variant models keep it `$hidden` and the page selects display fields only through `ProductDisplay`/PricingService outputs.
- Motion: M30 (card), M31 (E09 flicker on reveal), M32 (E10 states), M40 (gallery swipe/zoom), M41 (variant chips press + price fade), M42 (tier highlight), M44 (sticky bar), M45 (static strip) — all gated by the existing full/lite/off machinery; M43 deliberately NOT built (waits for the real cart in Phase 5A).
- Arabic strings added to `lang/ar/store.php`; code/comments English.

## Files created/changed

New: `app/Livewire/ProductPage.php`, `app/Support/ProductDisplay.php`, `resources/views/livewire/product-page.blade.php`, `resources/js/gallery.js`, components listed above, `tests/Feature/ProductPageTest.php`.
Changed: `routes/web.php` (product route), `lang/ar/store.php`, `resources/js/app.js` (lazy gallery import), `resources/views/layouts/app.blade.php` (toast mount).

## Tests — exact results

- `php artisan test --filter=ProductPageTest` → **12 passed** (49 assertions): variant price/SKU/stock switch, inactive variant rejected, valid vs expired sale display, fractional meter quantity + tier pricing + step rejection, stock clamping, out-of-stock WhatsApp availability message, WhatsApp hidden without a number, technical attributes + specs, approved-only reviews, related ordering, privacy (`cost_price` value and key absent from HTML).
- `php artisan test` (full suite) → **60 passed** (223 assertions).
- `vendor/bin/pint` → applied, clean.
- `npm run build` → succeeded; Swiper ships as a lazy `gallery-*.js` chunk (27.5 KB gzip) loaded only on pages with a gallery.

## NOT RUN

- Browser QA (360px/desktop visual check, Swiper swipe/zoom on a real device, Lighthouse, motion QA §11.10) — NOT RUN: no browser/device available in this environment; markup follows the 1C-verified patterns.
- Category/brand breadcrumb links use plain `/categories/{slug}` and `/brands/{slug}` URLs until Phase 3B registers the named routes.

## Assumptions

- Variant gallery image: when a variant has its own media image it becomes the first gallery slide.
- The disabled add-to-cart placeholder carries a short Arabic note directing customers to WhatsApp until Phase 5A.
