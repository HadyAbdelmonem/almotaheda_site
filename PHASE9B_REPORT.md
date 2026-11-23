# Phase 9B Report — Error Scenes & Final Motion Integration

## What was built in this phase

### Error scenes (spec §6.10, M70/M71)
- `resources/views/errors/404.blade.php`: Arabic 404 with the E16 `404` scene, exact spec copy («الصفحة دي مش موجودة» / «يبدو إن الفيشة اتشالت — ارجع للرئيسية أو دوّر على اللي محتاجه»), a plain GET search form to `/search`, category links and a home button — all functional with JavaScript disabled.
- `resources/views/errors/500.blade.php`: Arabic 500 with the E16 `500` scene, exact spec copy («حصل عطل مؤقت» / «الفيوز فصل — جرّب تاني بعد لحظات»), no technical details, home link as the no-JS fallback.
- **M70 wiring**: `plug-socket` (E04) now dispatches a `plug-toggled` browser event when its real button is pressed; `bulb` (E03) accepts a `bind-on` Alpine binding; the `404` scene binds the bulb to the plug state, so pressing «وصّل الفيشة» plays the plug-in transition and lights the bulb. Static final states are unchanged without JS.
- **M71 wiring**: the 500 page listens for the breaker switch's `switch:on` event and reloads the page 400 ms after the flip (the switch component itself is unchanged and reusable).
- New Arabic strings added to `lang/ar/store.php` (no hardcoded copy in templates).

### Deferred home motions completed
- **M21**: hero circuit overlay (E02) added to the homepage hero — desktop only (`hidden md:block`), `aria-hidden` via the circuit component, `pointer-events: none`, starts only after the window `load` event (fades in via opacity in `home.js`); it never delays or covers the LCP slide (z-index below the hero controls).
- **M25**: brands strip now sits on an E01 wire rail with a pulse while visible (`ambient` + `x-live`); the strip remains user-driven swipe/scroll, never autoplay.

### Empty states — verified, not rebuilt
- Empty cart → E16 `empty-cart` scene (Phase 5A, `cart-page.blade.php`).
- Empty search/listing → E16 `empty-search` scene (Phase 3B, `listing-page.blade.php`).
- Empty wishlist → E16 `empty-wishlist` scene (Phase 7, `account/wishlist.blade.php`).
- All three are asserted by `ErrorPagesTest`.

### /_ui
- No new components were introduced; the `/_ui` kit already loops all six E16 scenes, and the 404 scene shown there is now the interactive M70 version (plug button + bound bulb). M21/M25 are page-level compositions of existing E01/E02 components, already shown in the kit.

## Motion catalog audit (§11.8) — every row

Audit method: each row cross-checked against its phase report; rows marked † were additionally spot-verified in code during this phase.

| ID | Status | Where |
|---|---|---|
| M01 | Built (4A) † | `wire:navigate` + E15 progress CSS in `app.css`; `@persist` WhatsApp float + `#fx-root` in layout |
| M02 | Built (1C plumbing, applied 4B+) | `x-reveal` in `motion/reveal.js`, used on home sections/cards |
| M03 | Built (1C) | `btn` component press scale/sheen; `fx.spark` only after confirmed success |
| M04 | Built (1C, reinforced 9A) | Input focus ring CSS; global `:focus-visible` added in 9A |
| M05 | Built (1C) | Skeletons (`animate-pulse` + `x-live`) and E06 bolt-loader in buttons |
| M06 | Built (1C), wired (5A) | `toast` component, Alpine transitions, «عرض السلة» action |
| M07 | Built (4A) † | WhatsApp float one-time ring with 5s delay, `motion-full:animate-ring`, inside `@persist` |
| M08 | Built (1C) | `live.js` global scheduler, max 3 ambient loops, pauses off-screen/hidden tab |
| M10 | Built (4A) | `header.js` scroll state, opacity-only |
| M11 | Built (4A) | Topbar marquee, pauses on hover/focus/touch, static in lite/off |
| M12 | Built (4A) | Mega menu Alpine fade/slide, keyboard accessible |
| M13 | Built (4A) | Mobile drawer on native `<dialog>` (focus containment, Escape, focus restore) |
| M14 | Built (5A) | `fly-to-cart.js` ring + badge pop + spark after server-confirmed add |
| M15 | Built (4A) † | Footer E01 wire pulse + back-to-top bolt button |
| M16 | Built (3C) † | Search focus ring, dropdown fade-in, bolt-loader while loading |
| M20 | Built (4B) | Hero Swiper RTL, autoplay ≥5s full-only, pause/play, first slide eager |
| M21 | **Built (9B, this phase)** | Hero circuit overlay, desktop-only, starts after `load` |
| M22 | Built (4B) | Promo tiles reveal stagger + hover lift |
| M23 | Built (4B) | Category grid E12 tool-icon animations |
| M24 | Built (1C), used everywhere | E08 `section-title` component |
| M25 | **Built (9B, this phase)** | Brands wire rail (E01) with pulse while visible |
| M26 | Built (4B) | Product carousels, ~2.2 cards on mobile |
| M27 | Built (4B) | Why-us `trust-block` icons draw/sweep once on reveal |
| M28 | Built (4B) † | Wholesale banner E02 circuit (3 pulses) + button sheen |
| M30 | Built (3A) | Product card hover lift / image scale, touch press |
| M31 | Built (3A) | E09 sale badge one-time flicker |
| M32 | Built (3A) | E10 stock badge bulb-dot states, finite low-stock emphasis |
| M33 | Built (3B) | Mobile filter bottom sheet, E05 breaker switch, chips |
| M34 | Built (3B) † | Listing grid dims + skeleton cards via `wire:loading` |
| M35 | Built (3C) † | Live-search skeleton rows via `wire:loading` |
| M40 | Built (3A) | Gallery Swiper Thumbs + Zoom in `wire:ignore` |
| M41 | Built (3A) | Variant chips press + price/image cross-fade |
| M42 | Built (3A) | Quantity-tier active row highlight |
| M43 | Built (5A) | Fly-to-cart with trail + spark, only after server success |
| M44 | Built (3A) | Mobile sticky buy bar via `sticky-bar.js` |
| M45 | Built (3A) | Free-shipping red strip, static with bolt icon |
| M50 | Built (5A) | E11 cable free-shipping bar in cart/checkout |
| M51 | Built (5A) † | Cart lines auto-animate (stable `wire:key`) |
| M52 | Built (5A) † | Coupon spark/check on success; shake + message on error (`coupon-shake`) |
| M53 | Built (5A) † | Empty-cart E16 scene (verified this phase) |
| M60 | Built (5B) | Checkout confirm button bolt-loader state |
| M61 | Built (5B) † | Failed checkout submit shakes the form (`coupon-shake` keyframe reused) |
| M62 | Built (5B) | Shipping amount cross-fade on governorate change |
| M63 | Built (5B) | Success scene (plug → spark → bulb), ≤1,200 ms, once |
| M64 | Built (7) | Circuit status timeline (`order-timeline`), finite emphasis only |
| M70 | **Built (9B, this phase)** † | 404 scene: plug button lights the bulb |
| M71 | **Built (9B, this phase)** † | 500 scene: breaker flip reloads the page |
| M72 | Built (3B) † | Empty-search E16 scene (verified this phase) |
| M73 | Built (7) † | Empty-wishlist E16 scene (verified this phase) |
| M80 | Built (7) † | Wishlist heart pop + fill |

No row is silently omitted; no unlisted motion was added. M09/M17–M19/M21 numbering gaps are the spec's own (no such rows exist in §11.8).

## E01–E16 component audit (§11.6)

| ID | Status | Notes |
|---|---|---|
| E01 wire | Built (1C) | Footer, brands rail (M25) |
| E02 circuit | Built (1C) | Promo tiles, wholesale banner, hero overlay (M21) |
| E03 bulb | Built (1C); extended 9B | Optional `bind-on` Alpine binding for M70 |
| E04 plug-socket | Built (1C); extended 9B | Dispatches `plug-toggled` when interactive |
| E05 switch | Built (1C) | Filters + 500 scene |
| E06 bolt-loader | Built (1C) | Buttons, search field |
| E07 fx.spark | Built (1C) | `motion/fx.js`, full level only, one burst at a time |
| E08 section-title | Built (1C) | Used across home/listing |
| E09 sale-badge | Built (1C/3A) | One-time flicker |
| E10 stock-badge | Built (1C/3A) | Bulb-dot states |
| E11 free-shipping-bar | Built (5A) | Cart + checkout summary |
| E12 tool-icon | Built (1C) | §11.7 animation map per category |
| E13 btn | Built (1C) | Variants, loading state (E06), sheen |
| E14 cart-icon | Built (1C), wired 5A | Socket icon + badge, `data-cart-target` |
| E15 nav progress | Built (1C) † | `#nprogress` wire styling in `app.css` |
| E16 scene | Built (1C), completed 9B | All six states in use; 404/500 now interactive per M70/M71 |

## Tests — exact results
- `php artisan test --filter=ErrorPages` → **4 passed (25 assertions)**: 404 route renders the Arabic scene with plug/bulb binding, working search form and category links, no framework leakage; 500 view renders the breaker scene with the reload listener and no technical details; empty cart/search/wishlist scenes verified; home contains the M21 overlay hook and M25 wire pulse.
- Full suite and build: see commit — `php artisan test` and `npm run build` run before commit; results recorded in the phase summary to the owner.

## NOT RUN
- **Motion QA in a real browser (§11.10 full checklist): NOT RUN** — no browser in this environment. Verified instead by: component contracts (transform/opacity only), CSS level gates (`full`/`lite`/`off`, reduced-motion nets from Phase 1C), and the automated tests above. No animation touches money/stock/order/payment flows (server-first rule unchanged).
- **Lighthouse: NOT RUN** (see Phase 9A report).

## Notes
- Store identity remains fully data-driven: `store_name` in the seeders is **provisional** («ALMOTAHEDA») pending the owner's Facebook-page identity unification (page: شركة الصفا للتوريدات الكهربائية); nothing is hardcoded in templates.
- The designed logo mark (`public/branding/logo.webp`) is wired as the default logo via the `store_logo_url()` fallback (configured `logo` setting wins when set); committed with this phase and covered by a test in `StoreIdentitySeederTest`.
