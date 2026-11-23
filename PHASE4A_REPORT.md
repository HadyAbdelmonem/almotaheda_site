# Phase 4A Report — Header, navigation, footer, static pages & forms

Spec: §6.1, §6.5, §6.9, §10, §11.8 (M01–M13, M15, M16; M14 deferred to Phase 5A as instructed).

## What was built
- **Site header** (`components/site-header.blade.php`):
  - M11 moving topbar from `topbar_text` setting (string or list), bolt separators, pauses on hover/focus/touch, static in lite/off via the `ambient` safety nets.
  - Sticky main bar with constant height (64px mobile / 72px desktop); M10 scroll state fades a pre-drawn border overlay via opacity only (`header.js` + `.is-scrolled`).
  - Logo/store name, phones, account link and cart count all come from settings / `CartService` — no hardcoded identity. The single LiveSearch instance (Phase 3C) is embedded in the header; on mobile it becomes the full-width panel toggled by the search icon (one instance only, since the component uses fixed element IDs).
  - Desktop dark nav strip with cached category tree (`StorefrontNav`, `category_tree` cache key invalidated by existing observers) and an M12 mega menu (120ms hover intent, fade + 8px slide, Escape closes).
  - M13 mobile drawer as a native `<dialog>` (focus containment, Escape, focus restoration from the platform), category accordions via `<details>` (no height animation). **No mobile bottom navigation** (§4.10).
- **Footer** (`components/site-footer.blade.php`): E01 wire with pulse on top (M15), quick links, active static pages, contact data and social links from settings, payment marks driven by the `payment_*_enabled` settings, back-to-top bolt button.
- **WhatsApp float** (`components/whatsapp-float.blade.php`): inside `@persist`, hidden when `whatsapp_number` is empty, positioned above mobile sticky bars, one finite ring after 5s at full motion only (M07).
- **Static pages**: `/pages/{slug}` via `PageController`, content rendered through the new allow-list `HtmlSanitizer` (strips script/style/iframe elements *with contents*, event handlers, `javascript:` URLs and non-allow-listed attributes).
- **Forms**: `/contact` (Livewire `ContactForm`) and `/wholesale` (Livewire `WholesaleForm`) with validation, Egyptian-mobile check via `Phone`, hand-written honeypot (pretend-success, stores nothing) and `RateLimiter` throttle (5/min/IP). Records land in `contact_messages` / `wholesale_requests` (status `new`).
- **Filament resources** (Arabic labels, "المحتوى" group): Pages (RichEditor CRUD), ContactMessages (unread badge, view marks read, mark-read action, no create), WholesaleRequests (status workflow action new/contacted/closed, no create).
- Layout now renders `<x-site-header />` / `<x-site-footer />` and the persisted WhatsApp float on every page. New Arabic strings added to `lang/ar/store.php`. CSS additions: marquee keyframes, header overlay, drawer slide (transform only), static-page prose basics.

## Verification (exact results)
- `php artisan test` → **91 passed** (333 assertions), up from 81. New: `tests/Feature/LayoutAndFormsTest.php` (10 tests) — settings-driven header identity/phones/topbar, WhatsApp hidden/shown, nav tree active-only, footer pages, sanitized static page + inactive 404, contact store + phone normalization, invalid phone rejection, honeypot, wholesale store, throttle.
- `vendor/bin/pint --test` → PASS after applying Pint fixes.
- `npm run build` → PASS (app.css 83.00 kB / gzip 16.72 kB; app.js 18.05 kB / gzip 6.52 kB).
- `php artisan route:list` confirms `/pages/{slug}`, `/contact`, `/wholesale` and the three new admin resources.

## NOT RUN / notes
- Real-browser checks (drawer focus behaviour, mega-menu hover intent, marquee direction on real RTL text) — NOT RUN (no browser in this environment); implemented with native `<dialog>`/`<details>` and CSS so behaviour degrades safely.
- M14 (cart icon feedback) intentionally deferred to Phase 5A; the cart icon component already exists and the header shows the live session cart count.
- `/login` and `/track-order` links are present in the header/drawer; those pages themselves arrive in Phase 7 per the phase plan.
