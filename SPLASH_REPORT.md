# Splash Screen — Owner Extra Report

## Owner override note

Spec §11.2 rule 9 ("Not allowed in v1: … splash screens / preloaders") and §12
listed splash screens/preloaders as out of scope. The store owner explicitly
requested a loading page with the logo centered and a professional animation
on **2026-11-23**, after all build phases (1B–11) were complete. This extra is
therefore implemented as an **owner override of the spec preloader ban**, kept
as lightweight as possible so it cannot delay or trap content (see below).

## What was built

| File | Change |
|---|---|
| `resources/views/components/splash.blade.php` | New anonymous component: full-screen overlay, logo via `store_logo_url()` (fallback `public/branding/logo.webp`), store name from `setting('store_name')`, wire SVG with traveling current pulse (E01 mechanics, `x-live` + `.wire-pulse`), one-time glow + ring around the logo. `aria-hidden="true"`, no focusable elements. |
| `resources/views/layouts/app.blade.php` | `<x-splash />` included first inside `<body>` of the storefront layout only. |
| `resources/css/app.css` | New `.splash-*` section: ink background (`--color-ink`), fade-out transition, `splash-glow-in` / `splash-ring-out` one-time keyframes (transform/opacity only), and the `splash-autohide` failsafe keyframes. |
| `resources/js/motion/splash.js` | New module: hide logic (below), imported by `resources/js/app.js`. |
| `resources/js/app.js` | Imports `./motion/splash.js`. |
| `tests/Feature/SplashTest.php` | 3 new Pest tests (see Verification). |

## Behavior

- **When shown:** first full page load only (the markup lives in the layout,
  which the browser paints on hard loads). It is an overlay: the page renders
  normally beneath it and the LCP image stays eager — nothing is delayed.
- **Hide trigger:** window `load` event, with a ~300 ms minimum display to
  avoid flicker, then a 350 ms opacity fade and node removal.
- **Hard caps / never trapped:**
  - JS removes the node at **2.5 s** even if `load` hangs.
  - CSS `splash-autohide` animation fades it away (opacity 0 + visibility
    hidden + pointer-events none) at **2.6 s** even if the JS module fails.
  - With JS disabled it is **never displayed**: `.splash` is `display:none`
    unless the inline head script added `html.js`.
- **Soft navigations (`wire:navigate`):** the layout is not re-painted on SPA
  navigations; as a belt-and-braces guard, `splash.js` strips any splash node
  swapped in by a navigation synchronously in the `livewire:navigated` hook
  (guarded so an early event around the first load cannot kill the splash
  before its own hide logic ran). No flash, no reappearance.
- **No session skip:** per the owner's "shown until the page loads", it shows
  on every full load but stays fast (no minimum beyond the ~300 ms
  anti-flicker floor).

## Behavior per motion level

| Level | Splash behavior |
|---|---|
| `full` | Logo with one-time glow-in (900 ms) + one expanding ring (900 ms), wire current pulse running while visible (`ambient` + `x-live`), fade out on `load`. |
| `lite` | Static logo + name; no glow/ring (gated to `full`), wire pulse stopped by the existing `.ambient` lite safety net; fade out on `load`. |
| `off` / `prefers-reduced-motion` | Fully static; all animations killed by the global safety nets; `splash.js` removes the node immediately on `DOMContentLoaded` (no wait for `load`). |

Accessibility: decorative overlay is `aria-hidden`, contains no links,
buttons or focusable elements, and cannot trap focus; hiding never depends on
user interaction.

## Verification (actually run)

- `php artisan test --filter=Splash` → **3 passed (13 assertions)**:
  home HTML contains the splash markup with the logo URL
  (`branding/logo.webp` fallback) and the seeded store name; splash assets
  exist; `/admin/login` (Filament layout) contains no splash markup.
- `php artisan test` (full suite) → **212 passed (860 assertions)**
  (209 before this extra + 3 new).
- `vendor/bin/pint --test tests/Feature/SplashTest.php` → **PASS**.
  Repo-wide `vendor/bin/pint --test` still reports 2 pre-existing style
  issues in `routes/web.php` and `tests/Feature/StoreIdentitySeederTest.php`;
  verified pre-existing by running Pint on those files with this change
  stashed (they fail identically at HEAD `5388d37`) — not introduced here and
  left untouched.
- `npm run build` → **OK** (Vite build succeeded; built JS contains the
  `data-splash` hide logic and built CSS contains the `.splash` rules).

## NOT RUN

- Real-browser visual check of the splash (fade timing, soft-navigation
  non-flash, reduced-motion device behavior) — no browser available in this
  environment; behavior is covered by the layered JS/CSS fallbacks and the
  markup tests above.
- Lighthouse re-run with the splash — NOT RUN (same reason). The splash adds
  no render blocking: page HTML, CSS and the eager LCP image are unchanged,
  and the overlay hides on `load` at the latest by its 2.5 s cap.
