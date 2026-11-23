# Phase 1C Report — Electric Motion Foundation and Local UI Kit

Spec: `docs/PROJECT_SPEC.md` §10, §11, §13 · Prompt: `docs/PHASE_PROMPTS.md` Phase 1C
Date: 2026-11-23 · Environment: this VM (PHP 8.3.6 CLI, no browser available)

## What was built

- **Motion level (§11.3):** the layout (built in 1B) already rendered
  `<html data-motion>` from `setting('motion_level', 'full')` with the exact
  downgrade-only inline head script (reduced-motion → `off`; Save-Data / 2G /
  deviceMemory ≤ 2 / hardwareConcurrency ≤ 2 → `lite`; never upgrades).
  `resources/js/motion/level.js` now captures that effective level as a
  ceiling on first load and re-applies the same downgrade after
  `livewire:navigated`, so SPA navigation can never raise the level.
- **CSS (`resources/css/app.css`):** `motion-full` / `motion-any` custom
  variants (from 1B), reveal rules (visible without JS; hidden only when JS
  is on and motion is not `off`; lite = opacity-only 200 ms), `off`/`lite`
  safety nets and the reduced-motion net (from 1B), plus the §11.4 signature
  keyframes: `wire-pulse`, `spark-burst`, `bulb-flicker`, `spin-linear`,
  `gear-tick`, `hammer-swing`, `wifi-wave`, `rec-blink`, `led-chase`,
  `heat-rise`, `needle-sweep`, `node-pulse`. Only `transform`/`opacity` are
  animated (plus the documented small-SVG `stroke`/`fill` transitions on the
  bulb). Header scroll state fades a pre-drawn overlay via opacity only;
  the button sheen is a finite hover effect, never ambient.
- **JS modules (`resources/js/motion/`, imported by `app.js`):**
  `level.js` (above), `reveal.js` (`x-reveal` Alpine directive,
  IntersectionObserver threshold 0.15, once, stagger modifier with
  `--reveal-delay`, fallback reveal-all), `live.js` (`x-live` directive,
  global scheduler capping visible ambient loops at 3, pauses when the tab
  is hidden, exposes `window.motionDiagnostics()`), `fx.js` (`fx.spark` —
  6 particles, one burst at a time, full level only, `#fx-root`),
  `fly-to-cart.js` (listens for the server-confirmed `cart-added` event;
  full = WAAPI arc flight ~600 ms + spark; lite = badge pop only; off =
  nothing), `sticky-bar.js` (IntersectionObserver on the main buy button),
  `header.js` (scroll class after 8 px, registered once), `switch.js`
  (spark on the `switch:on` event). All modules guard missing elements and
  never throw.
- **Blade components:** E01 `electric/wire`, E02 `electric/circuit`,
  E03 `electric/bulb`, E04 `electric/plug-socket` (interactive mode renders
  a real `<button>`), E05 `electric/switch` (`role="switch"`,
  `aria-checked`, Alpine state, ≥44 px tap area), E06
  `electric/bolt-loader` (`role="status"`), E07 `fx.spark` (JS),
  E08 `section-title`, E09 `sale-badge`, E10 `stock-badge`,
  E11 `free-shipping-bar` (cable track, `scaleX` fill with `origin-right`,
  bulb lights at 100 %, `role="progressbar"`, hidden when threshold = 0),
  E12 `electric/tool-icon`, E13 `btn` (primary/secondary/outline/whatsapp,
  loading state), E14 `cart-icon` (`data-cart-target`, `aria-live` badge),
  E15 navigate-progress CSS (`#nprogress` wire + spark head),
  E16 `electric/scene` (404 / 500 / empty-cart / empty-search /
  empty-wishlist / success with static final states).
- **Icons:** all 24 whitelist keys in `config/icons.php` now have inline-SVG
  Blade components in `resources/views/components/icons/` (24 px grid,
  1.75 stroke, `currentColor`, Lucide-style; custom `breaker`,
  `power-strip`, `plug-socket` drawn in the same style) with named
  sub-parts (`drill-bit`, `fan-blades`, `gear-part`, `gauge-needle`,
  `breaker-lever`, `rec-dot`, `strip-led`, `heat-line`, `wifi-arc`, …) and
  the §11.7 hover/touch animation rules in CSS.
- **`/_ui` (local only):** route aborts 404 unless
  `app()->environment('local')`. The page shows design-token swatches,
  E01–E16 in meaningful states (the E11 demo uses a static 40 % bar next to
  a range slider), all 24 tool icons, a client-side full/lite/off preview
  switcher, and a diagnostics panel (active level, reduced-motion status,
  live ambient-loop count via `motionDiagnostics()`). No real store data.
- Layout now persists `#fx-root` across `wire:navigate` via `@persist`.
- New Arabic strings added to `lang/ar/store.php` (cart, loading, retry,
  free-shipping texts, motion level labels).

## Commands run and exact results

| Command | Result |
|---|---|
| `npm run build` | OK — Vite 8.3.8; CSS 53.45 kB (gzip 12.56 kB), JS 5.48 kB (gzip 2.07 kB) |
| `php artisan test` | **36 passed (137 assertions)** — 30 from 1B + 6 new |
| `vendor/bin/pint` then `vendor/bin/pint --test` | PASS — 99 files |

New tests (`tests/Feature/MotionFoundationTest.php`): `/_ui` returns 404 in
the testing environment; `/_ui` returns 200 with components + diagnostics
when the app env is forced to `local`; the layout renders
`data-motion="lite"` from the settings table and `data-motion="full"` by
default, including the downgrade script; every icon whitelist key has a
component file; all 8 motion modules and the required CSS rules exist.

## NOT RUN

- **§11.10 browser motion QA is NOT RUN** — no browser is available in this
  environment. Keyboard/touch interaction, CPU/Slow-4G throttling,
  Lighthouse scores, CLS measurement, repeated `wire:navigate` listener
  duplication checks, and the real-device downgrade behavior were NOT
  verified in a browser. The rules are implemented per spec and covered by
  the automated tests above where testable without a browser.
- Livewire SPA navigation behavior of the motion modules is NOT RUN in a
  real browser (same reason).

## Assumptions

- E11's `/_ui` demo shows a static 40 % bar; the slider is a labelled demo
  control (binding a Livewire-free Alpine value into the server-rendered
  bar would require a dedicated demo component, deferred as cosmetic).
- The 500 scene's breaker switch is rendered as a component state toggle;
  wiring it to actually reload the error page belongs to Phase 9B error
  pages.
