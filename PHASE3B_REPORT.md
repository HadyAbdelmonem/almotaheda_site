# Phase 3B Report — Category/brand listings and technical filters

Spec sections: §4.12, §6.3, §6.10, §11.8 (M33, M34, M72). Phase prompt: `docs/PHASE_PROMPTS.md` Phase 3B.

## What was built

- **One reusable listing component** `App\Livewire\ListingPage` (class-based, `WithPagination`) serving `/categories/{slug}` (route `categories.show`) and `/brands/{slug}` (route `brands.show`); it also carries a `search` mode prepared for Phase 3C. A parent category includes active products from all its active children; a child-category chip narrows the scope.
- **Filters** (shared partial `livewire/partials/listing-filters.blade.php`, rendered in the desktop sidebar and the mobile sheet): brand multi-select (checkboxes), price range (min/max with the scope's real price bounds shown), in-stock only via a breaker-style switch (E05 mechanics: `role="switch"`, `aria-checked`, snap knob; covers untracked products, simple stock and variant stock), child category chips, and **data-driven technical filters** from `CatalogAttributeService::filterableForCategory` (inherited parent attributes included). Only attributes/values present in the current result set are offered (capped by `catalog_filter_max_options`); numeric values render as chips with their unit label, select/boolean as discrete options.
- **Server-side whitelist validation**: unknown attribute keys are dropped; select values validate against the attribute's defined options (a valid option with no products yields an empty result, not an unfiltered one); number/text/boolean values validate against values present in the scope. Brand ids are intersected with brands actually present.
- **URL state**: every filter, sort and page lives in the query string via `#[Url]` properties (`brands`, `min`, `max`, `stock`, `sort`, `child`, `attr`, `page`), so copied URLs restore the exact state and back/forward works. Filtered URLs render `noindex,follow` + canonical to the base listing URL (layout now supports `robots`/`canonical`).
- **Sort**: latest / price asc / price desc / best sellers, each with a stable secondary `id` sort. **24 products per page**; pagination goes through the `WithPagination` trait actions so filter state is preserved.
- **Mobile bottom-sheet**: native `<dialog>` (focus containment, Escape close, focus restoration, backdrop click close — no focus-trap package), drag-handle styling, ≥44px controls; sticky mobile filter/sort bar. Desktop sidebar at `lg`.
- **Motion**: M33 (sheet + breaker switch + chips, filter lists use `@formkit/auto-animate` with stable `wire:key`), M34 (grid dims to 50% + skeleton cards via `wire:loading`), M72 (empty-results `empty-search` scene with clear-filters action and WhatsApp link).
- `App\Services\ProductSearchService` was introduced here because the listing's search mode depends on it (tokenized AND matching over `search_text` + ranking); its dedicated tests land in Phase 3C.
- Product page breadcrumbs/brand links now use the named `categories.show` / `brands.show` routes.

## Tests — exact results

- `php artisan test --filter=ListingPageTest` → **11 passed** (41 assertions): parent includes children, brand scope, combined technical + brand + stock filters, inherited parent attributes on child categories, invalid filter keys/values ignored, URL state restore, stable sorting, 24-per-page pagination, price range + noindex/canonical, empty scene + WhatsApp, inactive products/categories excluded, no `cost_price` in listing HTML.
- `php artisan test` (full suite at this commit) → **71 passed** (264 assertions).
- `vendor/bin/pint` → applied, clean. `npm run build` → succeeded.

## NOT RUN

- Browser QA (mobile sheet behavior on a real device, 360px visual check, Lighthouse, §11.10 motion QA) — NOT RUN: no browser/device in this environment.
- MySQL concurrency/engine check for listing queries — NOT RUN (SQLite local only, per project setup).

## Environment note

Mid-phase the VM lost its system PHP install (workspace persisted); PHP 8.3.6 + extensions were reinstalled via apt and the suite re-verified green afterwards.

## Assumptions

- Numeric technical attributes are offered as discrete value chips (allowed by §6.3 "range/chips where useful") instead of range sliders.
- The search mode of the listing component is exercised and committed in Phase 3C together with its route and tests.
