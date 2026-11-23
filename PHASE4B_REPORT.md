# Phase 4B Report — Homepage

Spec: §6.5, §10.6, §11.8 (M20, M22–M24, M26–M28; M21/M25 deferred to Phase 9B as instructed).

## What was built
- `HomeController` serves `/` (named route `home`) with data cached under the existing `home_sections` key (invalidated by the AppServiceProvider observers on Category/Brand/Product/Banner/Page changes) and defensive fallbacks while content tables do not exist yet.
- Sections in the exact spec order, each hidden when its data is empty:
  1. **Hero (M20)** — banners `position=hero`, active and inside their date window, max 5. First slide `loading="eager" fetchpriority="high"` with explicit dimensions and no reveal animation; later slides lazy. Swiper RTL inside `wire:ignore`, initialized from `resources/js/home.js` (re-hooked on `livewire:navigated`). Autoplay (5s) is enabled **only** when the effective motion level is `full`; a visible pause/play button is rendered (hidden when autoplay cannot run), autoplay stops on focus and on interaction (`disableOnInteraction`, `pauseOnMouseEnter`).
  2. **Promo tiles (M22)** — banners `position=promo`, max 3; desktop 3-column grid, mobile horizontal scroll-snap; reveal stagger via the `x-reveal.stagger` directive, hover lift 4px (transform only).
  3. **Category grid (M23)** — cached parent tree, E12 tool icons, 3 columns on mobile.
  4. **Brands strip** — active brands, manual swipe/scroll only (never auto-advances), logo grayscale → color on hover (desktop).
  5. **الأكثر مبيعاً (M26)** — products ordered by `sales_count` desc (the net-of-returns counter maintained by OrderService), Swiper carousel with ~2.2 cards visible on mobile, 3/4/5 at larger breakpoints.
  6. **وصل حديثاً** — newest active products grid (stable secondary sort by id).
  7. **لماذا تشتري منا؟ (M27)** — the existing settings-driven `trust-block` component (`why_us_blocks`).
  8. **Wholesale banner (M28)** — ink panel with E02 circuit (3 pulses max), primary CTA to `/wholesale` + WhatsApp button (hidden when no number).
- Section titles use the existing E08 component (M24). **No invented statistics, testimonials or counters anywhere** — a test asserts their absence.
- Note: the duplicate LiveSearch instance that the Phase 1B placeholder home view carried in the body was removed; the header (Phase 4A) now provides the single search instance on every page, including its mobile panel.

## Verification (exact results)
- `php artisan test` → **96 passed** (361 assertions), up from 91. New: `tests/Feature/HomePageTest.php` (5 tests) — full section rendering + best-seller ordering by `sales_count`, empty sections hidden with no invented content, hero date-window/active rules, settings-driven why-us (and its disappearance when emptied), inactive products excluded.
- `vendor/bin/pint --test` → PASS.
- `npm run build` → PASS (app.js 18.45 kB / gzip 6.61 kB; Swiper code split into lazy chunks loaded only on pages with a hero/carousel).

## NOT RUN / notes
- Real-device/browser verification of hero autoplay pausing, swipe direction and carousel breakpoints — NOT RUN (no browser in this environment); Swiper is configured per §11.9 (RTL container, `wire:ignore`, Alpine-era init via navigated hooks).
- M21 (hero ambient circuit overlay) and M25 (brands wire-rail pulse) deferred to Phase 9B as instructed.
- Lighthouse — NOT RUN.
