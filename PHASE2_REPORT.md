# Phase 2 Report — Filament Admin: Catalog Management

Spec: `docs/PROJECT_SPEC.md` §3, §4.11–4.12, §5, §7, §7.1 · Prompt: `docs/PHASE_PROMPTS.md` Phase 2
Date: 2026-11-23 · Environment: this VM (PHP 8.3.6, Filament 5.12, Livewire 4.4.9, SQLite local DB)

## What was built

- **Panel (`app/Providers/Filament/AdminPanelProvider.php`, generated with
  `make:filament-panel` so it uses exact Filament 5 APIs):** path `/admin`,
  login enabled, `brandName` resolved dynamically from
  `setting('store_name')`, Amber primary color (matches the store's
  safety-yellow token). Authorization is the existing
  `User::canAccessPanel()` = `is_admin` (verified by test: admin 200,
  non-admin 403, guest redirected to `/admin/login`). Arabic labels are set
  per resource (`modelLabel` / `pluralModelLabel` / navigation group
  «الكتالوج»); the app locale is `ar` so the panel renders RTL.
- **Brand resource:** name + auto slug, logo via
  `SpatieMediaLibraryFileUpload` (collection `logo`), authorized-dealer and
  active toggles, sort order; table with logo, product count, filters.
- **Category resource:** parent select lists **top-level categories only**
  (excluding the record itself), icon select from `config/icons.php` with a
  note that children inherit the parent's icon, single image upload, SEO
  fields. Max-2-levels is additionally enforced at the model level
  (`Category::saving`): self-parenting, parenting under a child, and moving
  a category that has children are all rejected with Arabic validation
  messages — so the rule holds for the importer and seeders too.
- **CategoryAttribute resource:** category select showing the inherited keys
  of the chosen category, `key` unique per category, data type
  (text/number/select/boolean), unit label, options repeater (visible for
  `select`), filterable/required/active toggles. Duplicate inherited keys
  are also rejected at the model level via `CatalogAttributeService`.
- **Product resource (full form):**
  - Identity: name, model code (auto-fills slug), unique slug, category,
    brand, short description, rich-text description.
  - Price/stock: internal `cost_price`, price, sale price + `sale_ends_at`,
    decimal stock, low-stock threshold, track/has-variants/active/featured.
    Stock quantity carries a server-side rule that calls
    `QuantityService::validate(stock, '0', quantity_step)`; the minimum
    quantity field validates min/step/max through the same service.
  - Unit & quantities: `unit_code` select with Arabic labels from
    `config/units.php` (changing it resets step/min to the unit defaults),
    decimal min/step/max.
  - Descriptive specs repeater (`{label, value}` pairs).
  - Structured technical attribute values via a relationship repeater:
    the attribute select is populated from `CatalogAttributeService`
    (inherited attributes included); value fields appear per data type.
    `ProductAttributeValue::saving` enforces exactly-one-value-column and
    type validation through the service.
  - Gallery: Spatie media upload, multiple + reorderable, WebP conversions
    (thumb/medium/large from 1B), max 4 MB per image.
  - SEO fields. **Variants** and **price tiers** are relation managers on
    the edit page: variants have SKU (unique), options key-value, price /
    sale price / variant-only `sale_ends_at`, internal cost, decimal stock,
    active, sort; tiers have decimal `min_qty` and `discount_percent` 0–90.
  - Table: image, name, model code, category, brand, price, stock badge
    (unit label + low-stock coloring), filters for category / brand /
    active / low-stock, bulk activate/deactivate, and a **quick stock
    adjustment** action (decimal change + mandatory reason note, runs
    `StockService::adjust` in a transaction, writes the movement, refuses
    negative stock).
- **Excel (§7.1) — `app/Filament/Pages/CatalogImport.php`:** template
  download and full export with the exact 16-column header
  (`ProductTemplateExport` / `ProductCatalogExport` via
  `ProductImportService::HEADERS`). Import: the uploaded workbook's header
  is verified against the template, the 5,000-row cap is enforced with an
  Arabic message, rows are cached and processed **at most 200 per request**
  by `ProductImportService::processChunk` (limit is clamped in the service
  itself), with a progress bar, created/updated/failed tallies in the page,
  and a downloadable failed-rows CSV. Row rules: upsert by
  `catalog_identity` (including the null-brand case), missing brand is
  auto-created **inactive**, missing category/subcategory, invalid
  `unit_code`, invalid quantity step/stock (via `QuantityService`) and
  unknown/invalid technical attributes reject the row with an Arabic
  reason; `specs` uses `label: value | …`, `attributes` uses
  `key=value | …`; stock differences write `stock_movements` with
  reason `import`. **Bulk image attach:** `<model_code>-<n>.<ext>` files
  are stored, then processed **at most 20 per request**
  (`BulkImageAttachService`), attached to the gallery in `n` order without
  overwriting existing images; unmatched and ambiguous files are reported
  by name in the page.
- **Cache invalidation (AppServiceProvider):** `Setting` save/delete
  forgets `setting.{key}`; Category/Brand/Product/Banner/Page save/delete
  forget `category_tree`, `brands_list` and `home_sections`.

## Commands run and exact results

| Command | Result |
|---|---|
| `php artisan make:filament-panel Admin` + `make:filament-resource` / `make:filament-relation-manager` | Generated Filament 5 scaffolding, then customized |
| `php artisan route:list` (admin) | Panel, dashboard, brands/categories/category-attributes/products resources and the catalog-import page all registered |
| `php artisan test --filter=AdminCatalogTest` | **12 passed (37 assertions)** |
| `php artisan migrate:fresh --seed` | OK — all 1B seeders still pass with the new model guards |
| `php artisan test` (full suite) | **48 passed (174 assertions)** |
| `vendor/bin/pint` then `vendor/bin/pint --test` | PASS — 133 files |
| `npm run build` | OK — Vite 8.3.8, CSS 54.31 kB / JS 5.48 kB |

New tests (`tests/Feature/AdminCatalogTest.php`) cover: panel access
(admin/non-admin/guest); creating a meter product with fractional stock
`850.5` through the actual Filament create form (Livewire test, no form
errors); piece-product fractional rejection through the same
`QuantityService` call the form rule uses (service-level assertion for the
rule's core, form-level creation verified for the valid path); max-2-level
categories; inherited attribute-key protection; importer
create/update/no-duplicate, null-brand identity, inactive brand
auto-create, attribute values + import stock movements, invalid
unit/category/attribute/fractional-piece-stock rejections, and the
200-row chunk clamp (201 rows → 200 + 1); exact template header; bulk
image attach + unmatched reporting.

## NOT RUN / notes

- **Browser QA of the admin panel is NOT RUN** — no browser in this
  environment; panel access and form submission are covered by HTTP and
  Livewire tests instead.
- Per-image alt text in the gallery upload: Filament's media component
  does not expose a per-file alt field in this setup, so alt text falls
  back to the product name at render time (the spec's allowed fallback);
  a dedicated alt editor can be added with the storefront phases.
- Orders, coupons, banners, settings and storefront pages were NOT built
  in this phase, per the phase prompt.
