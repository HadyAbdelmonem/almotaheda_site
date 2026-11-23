# Phase 3C Report — Arabic/model-code live search

Spec sections: §6.4, §8.4, §11.8 (M16, M35). Phase prompt: `docs/PHASE_PROMPTS.md` Phase 3C.

## What was built

- **`App\Services\ProductSearchService`** (introduced with the Phase 3B listing, completed here): the normalized query (via the existing `ArabicNormalizer`: Alef variants, ة→ه, ى→ي, tashkeel/tatweel removal, Arabic-Indic digits → Western, Latin lowercase) is split into tokens and **every token must match** `products.search_text` (AND behavior, LIKE-escaped). Ranking: exact model-code/SKU match first, then products containing every token as a whole word, then partial matches; ties break by `sales_count` then `id` for stability.
- **`/search?q=`** (route `search`) reuses the Phase 3B `ListingPage` component in `search` mode: full filter/sort/pagination machinery, relevance ordering by default, `noindex` + canonical to the base `/search` URL. The query lives in the `q` query-string property so it survives filter navigation.
- **Header live search** `App\Livewire\LiveSearch` + `livewire/live-search.blade.php`: `wire:model.live.debounce.300ms`, minimum 2 characters, top 6 results (thumbnail or placeholder, name, model code LTR, unit price via `ProductDisplay::cardPricing`), "عرض كل النتائج" link to `/search?q=`, bolt-loader and skeleton rows while loading (M35), dropdown fade-in (M16). Keyboard: ArrowUp/ArrowDown move the active row, Enter opens the active row (or the full results page), Escape closes; visible focus styles, `role="combobox/listbox/option"`, full-width panel on mobile. The component is mounted on the home page now; embedding it in the final site header lands with Phase 4A.
- **`search_text` maintenance verified and completed**: the Phase 1B product observer rebuilds it from name + model code + brand + variant SKUs; this phase adds rebuild triggers for the two change paths it missed — `ProductVariant::saved/deleted` (SKU changes) and `Brand::saved` when the name changed (renames) — each reloading variants and re-saving the product so the existing hook recomputes `search_text` without N+1 in listings.
- No external search engine, no new packages, no transliteration mappings (per spec).

## Tests — exact results

- `php artisan test --filter=SearchTest` → **10 passed** (31 assertions): exact model code match + live ranking, Arabic spelling variants (ة/ه, أ/إ/ا), Arabic-Indic digits in model codes, multiword AND, brand-name search, variant SKU search, inactive/soft-deleted exclusion, noindex + canonical, live search top results + minimum-length guard, `search_text` rebuild on SKU change and brand rename, no `cost_price` in results HTML.
- `php artisan test` (full suite) → **81 passed** (295 assertions).
- `vendor/bin/pint` → applied, clean. `npm run build` → succeeded.

## NOT RUN

- Browser QA (dropdown keyboard flow on a real browser, mobile panel behavior, §11.10 motion QA, search query timing on a production-size catalog) — NOT RUN: no browser/device in this environment. Query time on the seeded catalog is covered indirectly by the test suite only; the spec's "measure and report if the catalog outgrows this approach" item remains open for the deployment phase.
- MySQL full-text/engine comparison — NOT RUN (SQLite local only, per project setup).

## Assumptions

- Queries shorter than 2 characters show no live results (avoids full-catalog scans on a single letter); the full search page still accepts any query.
- Live-search ranking for partial queries prefers whole-word token matches over substring matches, per the spec's ranking order.
