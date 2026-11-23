# Phase 1B Report — Foundation, Database, Seeders and Domain Services

Spec: `docs/PROJECT_SPEC.md` §2–5, §8, §13 · Rules: `.cursor/rules/project.mdc`
Date: 2026-11-23 · Environment: this VM (Ubuntu 24.04, PHP 8.3.6 CLI)

## What was built

- Laravel **13.43.0** project scaffolded with `composer create-project laravel/laravel:^13.0`
  (Composer 2.11.2 phar in `~/workspace/bin/`; the apt Composer 2.7.1 was too old).
  The scaffold was created in a temp dir and moved into the project root,
  preserving the pre-existing `docs/` and `.cursor/rules/project.mdc`.
- Full §5 database schema (see Migrations), Eloquent models with relationships/casts,
  PHP backed enums with Arabic `label()` + `color()`, domain services and support classes,
  seeders (demo + production), Pest tests, and a minimal RTL base layout.
- Config: locale `ar`, fallback `ar`, timezone `Africa/Cairo`, currency `EGP`,
  session/cache `file`, queue `sync`; `ext-bcmath` required in composer.json;
  `app/helpers.php` autoloaded (provides `setting()` with cache and `unit_label()`).

## Packages installed (exact versions, from `composer show` / `npm ls --depth=0`)

Full output saved in `hidden_files/installed-versions.txt` (not committed).

| Package | Version |
|---|---|
| laravel/framework | 13.43.0 |
| filament/filament | 5.12.0 |
| livewire/livewire | 4.4.9 |
| filament/spatie-laravel-media-library-plugin | 5.12.0 |
| spatie/laravel-medialibrary | 11.23.13 |
| maatwebsite/excel | 4.0.7 |
| pestphp/pest (dev) | 4.7.8 |
| phpunit/phpunit (dev, via Pest) | 12.5.33 |
| laravel/boost (dev) | 2.10.4 |
| barryvdh/laravel-debugbar (dev) | 4.4.5 |
| tailwindcss / @tailwindcss/vite (npm) | 4.3.5 / 4.3.5 (constraint ^4.1) |
| swiper (npm) | 14.4.0 |
| @formkit/auto-animate (npm) | 0.11.0 |
| vite (npm) | 8.3.8 |

`composer.lock` and `package-lock.json` are committed.

### Composer conflict (recorded verbatim, package NOT installed)

`pestphp/pest-plugin-laravel` could not be installed on this stack:

- Combined attempt failed; key lines:
  - `pestphp/pest-plugin-laravel v4.1.0 requires pestphp/pest ^4.4.1` (satisfiable), but
    `pestphp/pest-plugin-laravel v4.0.0 requires laravel/framework ^11.45.2|^12.25.0` —
    conflicts with `laravel/framework ^13.17`.
  - `pestphp/pest-plugin-laravel[v5.0.0, ..., v5.0.1] require php ^8.4 -> your php version (8.3.6) does not satisfy that requirement.`
- Standalone retry: `Cannot use pestphp/pest-plugin-laravel's latest version v5.0.1 as it requires php ^8.4 which is not satisfied by your platform.`
- Resolution: core `pestphp/pest` 4.7.8 IS installed and all tests run on it
  (the plugin only adds Laravel assertion helpers; `tests/Pest.php` documents this).
  No package was downgraded or substituted; Laravel stays on ^13.

## Files created / changed (highlights)

- `config/units.php` (8 units with Arabic labels, §4.11), `config/icons.php` (24-key whitelist, §10.4)
- `app/Support/Money.php` — BCMath decimal strings, half-up rounding, `format()` → `1,250 ج.م`
- `app/Support/Phone.php` — Arabic-digit/prefix normalization, `^01[0125][0-9]{8}$`, `toWhatsApp()`
- `app/Support/ArabicNormalizer.php` — §8.4 normalization + `catalogIdentity()` (sha256)
- `app/Services/QuantityService.php` — decimal-string min/step/max validation, no floats
- `app/Services/PricingService.php` — §4.2 exactly (variant sale + own end date, tier stacking, half-up)
- `app/Services/CatalogAttributeService.php`, `CartService.php`, `CouponService.php`,
  `ShippingService.php`, `StockService.php`, `PaymentService.php` (attempt creation; Paymob is Phase 10)
- `app/Services/OrderService.php` — enforced transition graph (§4.7), cancellation/return
  side effects, idempotent `resolveStockDisposition()`; full cart→order creation arrives in Phase 5B
- `app/Enums/` — OrderStatus, PaymentStatus, PaymentMethod, CouponType, StockMovementReason,
  StockRestorationStatus, ReviewStatus, BannerPosition, PaymentTransactionStatus
- Migrations (FK order): catalog (categories, brands, products, product_variants,
  product_price_tiers), attributes (category_attributes, product_attribute_values),
  content (shipping_rates, settings, pages, banners, coupons),
  orders (orders, payment_transactions, order_items, order_status_histories, stock_movements),
  customer (reviews, wishlists, addresses, contact_messages, wholesale_requests),
  users table extended (phone unique nullable, is_admin, phone_verified_at),
  plus Spatie Media Library's media table (published).
  Money `decimal(12,2)`, quantities `decimal(12,3)`, `products.catalog_identity` unique (64-char hash).
- Models for all tables; Product/ProductVariant/Category/Brand use Media Library
  (WebP conversions thumb 400 / medium 800 / large 1200 on Product `gallery`).
  `cost_price`/`admin_notes`/`payment_proof` are `$hidden`; User implements Filament's
  `FilamentUser` (`canAccessPanel` = `is_admin`).
- Seeders: `ShippingRateSeeder` (27 governorates, placeholder fees §4.4), `CategorySeeder`
  (9 parents + 26 children with icon keys; 19 technical attributes for breakers/cables/
  lighting/power tools/cameras), `BrandSeeder` (7 brands), `SettingSeeder` (24 settings §4.1),
  `PageSeeder` (6 pages), `CouponSeeder` (OPEN10 10%), `AdminUserSeeder`
  (admin@example.com, random password printed once to console), `DemoProductSeeder`
  (35 products: 5 with 3 variants each = 15 variants, 3 with price tiers = 6 tiers,
  meter cable `CU-1.5-PVC` step 0.25 stock 850.5, roll-only `ROLL-2.5-100`,
  sale/low-stock/out-of-stock examples, 35 attribute values), `BannerSeeder` (3 hero + 3 promo,
  no external images), and `ProductionSeeder` (reference data only — verified 0 products / 0 users).
- Factories: Product (with onSale/lowStock/outOfStock/byMeter states), Category, Brand.
- Layout: `resources/views/layouts/app.blade.php` (RTL, `data-motion` + downgrade-only
  inline script §11.3, empty header/footer slots), `resources/views/home.blade.php`,
  `resources/css/app.css` (Tailwind 4 `@theme` tokens §10.2/§11.4 + motion variants/safety nets),
  `lang/ar/store.php` (§10.7 strings). Cairo font is a documented placeholder
  (local @font-face + family token) until real self-hosted files land with the storefront phases.
- Tests: `tests/Unit/MoneyTest.php`, `QuantityServiceTest.php`, `PricingServiceTest.php`,
  `PhoneAndNormalizerTest.php` + scaffold examples.

## Commands run and exact results

| Command | Result |
|---|---|
| `composer create-project laravel/laravel:^13.0` (temp dir) | OK — framework 13.43.0 |
| `composer require filament/filament:^5.0 livewire/livewire:^4.0` | OK |
| `composer require spatie/laravel-medialibrary filament/spatie-laravel-media-library-plugin:^5.0 maatwebsite/excel:^4.0` | OK |
| `composer require --dev pestphp/pest (-W)` | OK — pest 4.7.8, phpunit 12.5.33 |
| `composer require --dev pestphp/pest-plugin-laravel` | **FAILED — conflict recorded above; package not installed** |
| `composer require --dev laravel/boost barryvdh/laravel-debugbar` | OK |
| `npm install tailwindcss@^4.1 @tailwindcss/vite@^4.1 swiper @formkit/auto-animate` | OK |
| `php artisan migrate:fresh --seed` (SQLite) | OK — all migrations + 9 seeders DONE |
| `php artisan tinker` counts | governorates=27, categories=35 (9 parents), brands=7, products=35, variants=15, tiers=6, attributes=19, attribute_values=35, pages=6, settings=24, banners=6, OPEN10 present, admin=1 |
| `php artisan migrate:fresh --seeder=ProductionSeeder` | OK — products=0, users=0, rates=27, pages=6 (then demo seed restored) |
| `php artisan test` | **30 passed (87 assertions)** |
| `vendor/bin/pint` then `vendor/bin/pint --test` | Applied, then **PASS — 98 files**; tests re-run green after Pint |
| `npm run build` | OK — Vite 8.3.8, CSS 42.51 kB (gzip 10.38 kB) |

Two real bugs were found by the tests and fixed during this phase:
Money's half-up increment (0.05 instead of 0.005) and add/sub returning
6-scale strings; `DatabaseSeeder` originally used `WithoutModelEvents`,
which suppressed the Product saving hook that fills `catalog_identity`.

## Deviations

- **SQLite instead of MySQL**: no MySQL server exists in this VM. Local dev/tests use
  `database/database.sqlite` (tests use in-memory SQLite via phpunit.xml). Migrations use
  only portable schema constructs (no SQLite-only features), but MySQL-specific behavior
  (row locking, decimal storage, multiple-NULL unique semantics on
  `payment_transactions`) is **NOT RUN / not verified** — it must be re-tested on
  MySQL 8 / MariaDB 10.6 before production (spec §2, §13).
- Filament panel itself (resources, dashboard) is **not** built — that is Phase 2.
  Only the panel-access contract (`FilamentUser::canAccessPanel`) is in place.
- `OrderService::create` (cart → order transaction) is Phase 5B by design; the
  transition/disposition core is implemented and the rest is documented above.

## NOT RUN

- MySQL/MariaDB migration + concurrency tests — no MySQL server in this VM.
- Browser/mobile layout checks, Lighthouse — no storefront pages yet (Phase 3+).
- Motion QA §11.10 — motion foundation is Phase 1C; only the §11.4 CSS tokens
  and the downgrade script were included here.
- Paymob anything — Phase 10.

## How to test manually

```bash
cd ~/workspace/electrical-tools-store
php ~/workspace/bin/composer.phar install   # or apt composer for scripts only
touch database/database.sqlite              # already present locally
php artisan migrate:fresh --seed            # prints a random admin password once
php artisan test
vendor/bin/pint --test
npm run build
```
