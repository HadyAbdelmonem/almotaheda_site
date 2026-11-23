# Phase 11 Report — Security Hardening, Full Regression & Deployment

## Owner data applied during Phases 9–11 (for the record)
- **Store identity was unified on the owner's Facebook page** («شركة الصفا للتوريدات الكهربائية») per the owner's instruction: `store_name`, address (التجمع الخامس، القاهرة), email, phone/WhatsApp `01033808633` / `201033808633`, InstaPay/Vodafone Cash transfer details, the Facebook page link, and the About-page bio («متخصصون في توريد جميع المستلزمات الكهربائية») all live in `SettingSeeder`/`PageSeeder` — and therefore in `ProductionSeeder` too. Nothing is hardcoded in templates. The earlier provisional Latin name (ALMOTAHEDA) is no longer used as the display name anywhere; it survives only as the GitHub repository name (`HadyAbdelmonem/almotaheda_site`).
- **Logo**: the designed mark at `public/branding/logo.webp` (yellow bolt+plug on ink, matching the §10.2 tokens) is the default logo via the `store_logo_url()` fallback in the header/footer; an owner-configured `logo` setting overrides it. The Facebook profile picture could not be extracted as a usable file, so the designed mark is the logo (owner approved).
- **Paymob**: owner confirmed there is **no merchant account** → card payment disabled; COD + bank transfer only (see Phase 10 report).

## Security audit — findings & fixes
| Area | Result |
|---|---|
| Admin gate | `is_admin` + `canAccessPanel` enforced (Phase 2/7). **Hardened this phase:** `is_admin` removed from `User`'s fillable list — it can no longer be mass-assigned from any request (asserted by test); `AdminUserSeeder` and the deployment guide set it via explicit `forceFill` in trusted contexts only. `RegisterPage` no longer passes it at all. |
| Mass assignment | All other models use `$guarded = ['id']`; all writes go through validated Livewire/FormRequest/service paths. |
| Ownership / policies | Account orders/addresses/wishlist/reviews are query-scoped to the authenticated user (Phase 7 tests); guest tracking needs exact order number + phone with a generic mismatch (Phase 7); receipt upload/view authorized per Phase 10. |
| Rate limits | Present on login, register, checkout, cart coupon apply, contact, wholesale, track-order — and **added this phase to review submission** (the one gap found). |
| Honeypots | Contact, wholesale and checkout forms (Phase 4A/5B). |
| CSRF | On everywhere; the single exemption is the exact Paymob callback route, authenticated by HMAC (Phase 10). |
| Uploads | Images only, ≤ 4 MB, MIME + image-content validation, UUID filenames (receipts, Phase 10); catalog media via Spatie/Filament with WebP conversions. Payment receipts are on the private disk and served only through the admin-authorized route — never publicly addressable (tested). |
| Rich text | Static pages render through the allow-list `HtmlSanitizer`; product descriptions are escaped (`e()` + `nl2br`), never raw. |
| Secrets | Only in `.env`; `.env.example` / `.env.production.example` carry blank keys. Dedicated `payments` log redacts PAN/tokens/keys before storage (Phase 10). |
| Leakage test | **New `SecurityLeakageTest` (4 tests):** product page, category listing, search results, home and the embedded JSON-LD contain no `cost_price` value or key; the order success page contains no `admin_notes`, no payment-proof path, no provider payload data and no Paymob secrets (with secrets configured); registration cannot mass-assign `is_admin`; `store:purge-demo` keeps structure. |
| Debug / info leakage | `.env.production.example` sets `APP_DEBUG=false`; custom Arabic 404/500 pages show no technical details (Phase 9B tests). `/_ui` remains local-only and out of the sitemap. |

## Deliverables in this phase
- `.env.production.example` — production template (APP_ENV=production, APP_DEBUG=false, MySQL, blank secrets incl. Paymob).
- `docs/DEPLOYMENT.md` — Arabic, non-expert deployment guide for cPanel/shared hosting: PHP extensions, docroot → `/public`, Composer `--no-dev` or vendor upload, local `npm run build` + `public/build` upload, `migrate --force`, **ProductionSeeder only**, `storage:link`, SSL, cron (`schedule:run` every minute), permissions, backups, log locations, admin password rotation, purge-demo usage, and a smoke-test checklist. It names the target repo **HadyAbdelmonem/almotaheda_site** (the parent agent performs the push after this phase) and carries the **mandatory pre-launch step: re-run the full suite + locking/decimal/concurrency verification on MySQL 8** (development used SQLite).
- `store:purge-demo` artisan command — deletes demo products/orders/coupons and their dependent rows (force-deleting products so media files are cleaned), keeps settings/rates/categories/brands/pages/users. Environment guard: free only in `local`/`testing`; elsewhere it needs `--force` **and** an interactive confirmation.

## §13 Global Definition of Done — final audit
| # | Item | Status |
|---|---|---|
| 1 | `migrate:fresh --seed` works from scratch (disposable local DB only) | ✅ Ran this phase: 27 governorates, 35 demo products, unified store identity, card disabled |
| 2 | `php artisan test` passes; money/stock/order logic has Pest feature tests | ✅ **209 passed (847 assertions)** — full suite, this phase |
| 3 | Pint applied; no debug leftovers | ✅ Pint applied to all touched files in every phase |
| 4 | Mobile layout at 360px; RTL correct | ⚠️ Code-level (responsive Tailwind, logical properties) — **browser check NOT RUN** (no browser in this environment) |
| 5 | No N+1 on listings; no secrets/hardcoded store data | ✅ Eager-loading in listing/home queries; identity in settings/seeders (audited Phase 8 + this phase) |
| 6 | Phase report + stop | ✅ This report (Phases 9A–11 delivered in one authorized run) |
| 7 | Motion QA §11.10 for motion phases | ⚠️ Partially — catalog fully audited in Phase 9B; **real-browser motion QA NOT RUN** |
| 8 | Every component/animation in the catalog and on `/_ui` | ✅ Phase 9B audit tables (E01–E16, M01–M80) |
| 9 | No package outside §2 | ✅ No packages added in Phases 9A–11 |
| 10 | Installed-version APIs (Laravel 13 / Filament 5 / Livewire 4 / Tailwind 4.1+); lockfiles committed | ✅ Versions verified at Phase 9A start (13.43 / 5.12 / 4.4.9 / 4.3.5); Alpine never installed separately |
| 11 | Decimal quantities, sale units, dynamic attributes across admin/storefront/cart/checkout/orders/Excel/invoice/tests | ✅ Phases 1B–8 test coverage, still green in the full suite |
| 12 | Cancellation/return stock disposition + coupon release idempotent and audited | ✅ Phase 6 tests + Phase 10 expiry/late-callback tests |
| 13 | Paymob callback tests: HMAC, reference, amount, currency, uniqueness, duplicates, second success, late success, reconciliation | ✅ Phase 10 — 13 mocked tests. Live test-mode verification: **NOT RUN (no merchant account — owner decision)** |
| 14 | Phase acceptance criteria + motion IDs; NOT RUN items explicit | ✅ See below |

## NOT RUN — explicit list (nothing below was faked or assumed)
- **MySQL 8 concurrency/decimal/locking re-verification: NOT RUN** — no MySQL server in this environment; the suite runs on SQLite. This is a **mandatory pre-launch step** and is documented as such in `docs/DEPLOYMENT.md`.
- **Lighthouse (home/category/product, mobile): NOT RUN** — no browser.
- **Browser QA (motion levels, RTL at 360px, keyboard/screen-reader walk-through): NOT RUN** — no browser; verified at code/template level only.
- **Staging deployment rehearsal: NOT RUN** — no staging server was provided.
- **Real-device checkout test: NOT RUN** — no device available.
- **Paymob live test-mode payment: NOT RUN** — the owner has no Paymob merchant account; card payment stays disabled by owner decision.

## Final verification — exact commands & results (this phase)
- `php artisan test` → **209 passed (847 assertions)**
- `vendor/bin/pint` → applied to all Phase 11 files (and every earlier phase)
- `npm run build` → success
- `php artisan migrate:fresh --seed` (disposable local SQLite) → success; tinker check: 27 governorates, 35 products, `store_name` = شركة الصفا للتوريدات الكهربائية, `payment_card_enabled` = false
- Git: one commit per phase (9A, 9B, 10, 11) plus the owner-data commits; **push to GitHub is performed by the parent agent after this phase** (target: `HadyAbdelmonem/almotaheda_site`, branch `main`).

## Known follow-ups for the owner
1. Run the MySQL re-verification + staging rehearsal before taking real orders (see DEPLOYMENT.md).
2. Replace the placeholder shipping fees and demo catalog with real products via the admin panel (or Excel import), then run `store:purge-demo` if a demo-seeded database is ever promoted.
3. If a Paymob account is opened later: fill `.env`, verify a test-mode callback, then enable card payment in admin settings — no code change needed.
4. Owner-requested extra (loading/splash page) is intentionally **not** part of Phases 9–11 and is handled as a separate step after Phase 11.
