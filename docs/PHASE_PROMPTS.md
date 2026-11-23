# Cursor Phase Prompts — Electrical Tools Store (v3)

This file contains the execution prompts. The single source of truth for business rules, schema, UI and motion is `docs/PROJECT_SPEC.md`. Always apply `.cursor/rules/project.mdc`.

## Files and placement

| File | Project location | Purpose |
|---|---|---|
| `PROJECT_SPEC.md` | `docs/PROJECT_SPEC.md` | Full product/business/technical specification — single source of truth |
| `project.mdc` | `.cursor/rules/project.mdc` | Always-applied implementation and safety rules |
| `PHASE_PROMPTS.md` | Keep at project root or `docs/PHASE_PROMPTS.md` | Copy one phase prompt at a time into Cursor Agent |

## Working method (every phase)

1. Use a **new Cursor Agent chat for each phase/sub-phase**. Keep the repository context and committed files; do not depend on the previous chat's memory.
2. Before a phase, ensure the previous phase is committed in Git. Create a branch or commit checkpoint before risky migrations/payment work.
3. Paste only the prompt for the requested phase. Never ask Cursor to build multiple phases in one run.
4. Cursor must first inspect the repository, installed versions and exact relevant spec sections. For Filament 5 / Livewire 4 / Tailwind 4 code it must consult current official docs through Laravel Boost or official docs; never copy older-major syntax from memory.
5. When the prompt contains a REVIEW/STOP gate, Cursor must stop at the gate and wait for the owner's explicit approval before destructive/schema-changing implementation.
6. Run the acceptance checklist manually. Run `php artisan test` and `vendor/bin/pint --test` (or apply Pint then test) before moving forward.
7. If a test cannot be run (for example credentials, browser, MySQL concurrency or external merchant sandbox unavailable), report **NOT RUN** with the reason. Never claim success based on code inspection alone.
8. For a bug, give Cursor the exact URL/action, expected behavior, actual behavior and full error/stack trace. Ask for root-cause fix plus a regression test; do not ask vaguely to "fix the site".
9. If genuinely missing business information appears, use V3 defaults in `PROJECT_SPEC.md`. If the missing information is not covered there and materially blocks safe implementation, state the blocker and stop only that part; do not invent policy.
10. After acceptance, commit with a clear English message containing the phase ID. Then start the next phase in a new chat.

> **Important:** AI output is not proof of correctness. Human acceptance, actual automated test results, mobile browser checks and deployment rehearsal are required. Storefront animations must never be allowed to change or delay money, stock, order or payment operations.

---

## Phase 0 — Local environment (owner task, not Cursor implementation)

- [ ] Install PHP 8.3+, Composer, Node.js 20+, MySQL 8 (or MariaDB 10.6+), Git and Cursor. On Windows, Laragon can be used if it provides the required PHP extensions.
- [ ] Create a fresh Laravel 13 project and open it in Cursor.
- [ ] Initialize Git and create the first commit.
- [ ] Copy `PROJECT_SPEC.md` to `docs/PROJECT_SPEC.md`.
- [ ] Copy `project.mdc` to `.cursor/rules/project.mdc`.
- [ ] Copy this file to the repository root or `docs/PHASE_PROMPTS.md`.
- [ ] Create an empty MySQL database and configure `.env`.
- [ ] Confirm PHP extensions in CLI and web PHP: see §2.2, including BCMath, WebP image support and Excel extensions.
- [ ] Confirm the intended host supports PHP 8.3+, cron, SSL, required extensions, document-root configuration and Composer/vendor upload.
- [ ] Prepare actual store identity, logo, phones, WhatsApp number, address, shipping rates, bank-transfer details and product data. These will be entered through admin settings, not hardcoded.

Acceptance: `php -v`, `composer --version`, `node -v`, `npm -v`, `php -m`, `mysql --version` (or MariaDB equivalent) show the required environment. Save the output privately; do not commit `.env`.

---

## Phase 1A — Repository, schema and compatibility review (REVIEW ONLY)

```text
Read the FULL `@docs/PROJECT_SPEC.md`, `.cursor/rules/project.mdc`, and this phase prompt.

PHASE 1A — Review only. Do not install packages, edit files, create migrations or write application code.

1. Inspect the repository and report whether it is empty, an existing Laravel app, or an incompatible project. Do not overwrite existing work.
2. Check `php -v`, `composer show` if a project exists, `node -v`, `npm ls` if applicable, and the database engine/version.
3. Verify the target stack from official current docs: Laravel 13 / PHP 8.3+, Filament 5, Livewire 4, Tailwind 4.1+ with `@tailwindcss/vite`, Laravel Excel 4, Spatie Media Library and the Filament Spatie plugin v5. List exact resolved constraints and any real conflicts.
4. Review every table and column in §5 plus the quantity/unit and category-attribute additions in §4.11–§4.12. Check FK delete behavior, unique constraints, decimal precision, nullable fields, indexes, enum values, soft deletes and historical snapshots.
5. Explicitly review: decimal stock quantities; `catalog_identity` for model_code + brand imports; dynamic category attributes; order stock disposition; coupon release; `sales_count` reversal; Paymob transaction idempotency; private payment proofs.
6. Check cross-section consistency: §4 business rules vs §5 schema vs §6 pages vs §7 admin vs §11 motion catalog vs §13 DoD vs the requested phase IDs.
7. Return: (a) a table-by-table schema proposal with columns/FKs/indexes; (b) compatibility matrix; (c) risks and missing implementation details; (d) any conflict that blocks safe implementation.
8. Do not ask questions already resolved in §4.10. If a real blocking decision not covered by the spec exists, list it explicitly.

STOP after the review and wait for approval. Do not implement Phase 1B.
```

Acceptance: owner reviews the schema, package matrix and migration plan before Phase 1B.

---

## Phase 1B — Foundation, database, seeders and domain services

```text
Read `@docs/PROJECT_SPEC.md` sections 2–5, 8 and 13, plus `.cursor/rules/project.mdc`.

PHASE 1B — Foundation and database.

STEP A — Before coding, summarize the exact packages/versions to install and migrations/models/services to create. Confirm Composer constraints resolve on Laravel 13 + Filament 5 + Livewire 4. STOP for owner approval if the actual constraints conflict; never downgrade or replace packages silently.

STEP B — After approval:
1. Install only the packages listed in §2. Include `@tailwindcss/vite`; configure Vite/Tailwind 4.1+ using the current official integration.
2. Configure Arabic locale/fallback, `Africa/Cairo`, EGP, session/cache/queue drivers and `.env.example`.
3. Create all migrations/models/relationships/casts/factories/enums exactly per §5. Include category_attributes, product_attribute_values and payment_transactions. Keep `cost_price`, `admin_notes`, private proof paths and provider payloads protected from public serialization.
4. Implement `Money` with BCMath-backed decimal-string arithmetic and tested half-up rounding, `QuantityService`, `Phone`, `ArabicNormalizer`, `setting()` and `config/units.php`. Require `ext-bcmath` in Composer platform requirements. Quantity calculations must not use float arithmetic; write unit tests for whole and fractional steps, min/max, large-value overflow boundaries, formatting and rounding.
5. Implement `PricingService` with separate variant/product sale end-date behavior and tier stacking rules. Write unit tests for regular/sale/invalid/expired sale, variant override, tier threshold, fractional quantity and half-up rounding.
6. Create seeders: 27 governorates with clearly documented placeholder rates; category tree and electric icons; technical attribute definitions; brands; default settings; six editable static pages; admin user with a random password printed once in console; OPEN10 demo coupon; around 30 demo products including fractional-meter cable and roll-only sample; variants, tiers, sale/low/out-of-stock examples. No external image downloads; use generated local placeholder SVG/GD assets.
7. Build only the minimal RTL base layout with Vite, Cairo font placeholders/configuration, color tokens and empty header/footer slots. No real storefront page yet.
8. Add model observers for `search_text`, `catalog_identity` and cache invalidation. Ensure soft-deleted/inactive items are excluded from sale flows.
9. Add a production seeder separate from demo seeders; production seeder must never create demo products or a known public admin password.
10. Run migrations, seeders, Pest and Pint; report exact commands/results.

Do not build Filament resources or storefront pages in this phase.

Finish with §13 phase report and STOP.
```

Acceptance checklist:
- [ ] In a disposable local/test database only, `php artisan migrate:fresh --seed` succeeds on the intended DB engine. Never run `migrate:fresh` on staging/production data.
- [ ] Tinker counts show expected governorates/categories/demo products/attribute definitions.
- [ ] At least one cable product stores fractional meter stock and validates `0.5`, `0.25` etc. only when its step allows.
- [ ] No money/quantity arithmetic path uses float as its source of truth.
- [ ] Unit, pricing, phone and ArabicNormalizer tests pass.
- [ ] `php artisan test` and Pint pass; admin password printed and changed/stored safely.

---

## Phase 1C — Electric Motion Foundation and local UI kit

```text
Read `@docs/PROJECT_SPEC.md` sections 10, 11 and 13, plus `.cursor/rules/project.mdc`.

PHASE 1C — Build the reusable motion foundation BEFORE animating real storefront pages.

1. Inspect installed Tailwind/Livewire/Alpine versions and current docs. Do not load Alpine twice; no separate Alpine plugins or animation packages may be installed.
2. Implement motion-level server output, safe client downgrade (`full` → `lite` for Save-Data/2G/low-memory; OS reduced motion → `off`), and reapplication after `livewire:navigated`. Never upgrade the owner-selected level.
3. Implement CSS tokens, `motion-full` / `motion-any` variants, final static states, no-JS visibility, reduced-motion safety and the allowed-property rules in §11.2–§11.4.
4. Implement `level.js`, `reveal.js`, `live.js` with global max-three ambient-loop scheduling, `fx.js`, `fly-to-cart.js` foundation, `sticky-bar.js`, `header.js` and `switch.js` as required by §11.5. Clean up observers/listeners on teardown and navigation.
5. Build reusable E01–E16 Blade components exactly as documented in §11.6. Each component works without animation and has accessible semantics/labels.
6. Build the local-only `/_ui` page per §11.11 with `full/lite/off`, reduced-motion and loop-count diagnostics. It must 404 outside local environment.
7. Do not animate box-shadow, filters, large backgrounds, width/height or layout. Do not add any unlisted animation. Keep no more than 3 visible ambient loops active.
8. Test keyboard and touch controls; ensure every switch/plug/breaker has real behavior, not a decorative click handler.

Run §11.10 motion QA and report the exact browsers/devices/emulations actually tested. Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] `/_ui` opens only in local environment and returns 404 elsewhere.
- [ ] Every E01–E16 component renders a useful static state with JS disabled/off.
- [ ] Full, lite, off and prefers-reduced-motion behave distinctly as specified.
- [ ] Auto downgrade only reduces motion; it never upgrades off/lite.
- [ ] More than three visible ambient effects never run simultaneously.
- [ ] No duplicate listeners/observers after repeated `wire:navigate`.
- [ ] No forbidden animated properties or extra packages.

---

## Phase 2 — Filament admin: catalog management

```text
Read `@docs/PROJECT_SPEC.md` sections 3, 4.11–4.12, 5, 7 and 7.1, plus `.cursor/rules/project.mdc`.

PHASE 2 — Build the admin catalog only.

1. Configure Filament 5 panel at `/admin`, Arabic RTL, branding from `setting('store_name')`, and strict `is_admin` / `canAccessPanel` authorization.
2. Build Brands, Categories (max two levels), Category Attributes, Products and Variants resources. Use only Filament 5 APIs from current docs.
3. Category attributes: parent definitions are inherited; child definitions extend them; prevent duplicate keys in ancestry; configure type/options/unit/required/filterable/active/sort order.
4. Product form: all fields, unit_code with Arabic label, min/step/max quantity, decimal stock/cost/price, general specs, structured technical attribute values, sale date, SEO fields, image gallery with reorder/alt text/WebP conversions, variants, and quantity tiers.
5. Validate each product's unit, quantity step and attribute values server-side. Variants inherit product unit and quantity settings; variant stock is decimal.
6. Catalog identity: upsert uniqueness is model_code + brand identity, including null brand. Use `catalog_identity`; do not rely on nullable multi-column unique behavior alone.
7. Excel template/import/export exactly per §7.1. Each HTTP request handles at most 200 rows; the UI triggers the next request until done. Cap 5,000 rows. Add importer tests for create/update/duplicate identity/missing category/brand auto-create/invalid unit/invalid attributes/fractional stock. Import stock differences create stock_movements.
8. Bulk image attach pattern `<model_code>-<n>.<ext>`, at most 20 files per request, report unmatched/ambiguous images. Do not overwrite existing image sets without an explicit admin action.
9. Add quick stock adjustment with reason/note/audit; do not allow arbitrary negative stock.
10. Build cache invalidation observers and prevent unauthorized admin actions.

Do not build Orders, Coupons, Banners, Settings or storefront pages.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Admin user can enter; ordinary account cannot access admin.
- [ ] Create category, inherited attributes, product and variants with images.
- [ ] Meter product can have fractional stock and order step; piece product rejects fractional quantity by default.
- [ ] Technical values save and render in admin.
- [ ] Excel template round-trips; repeated import updates, not duplicates; invalid rows have actionable errors.
- [ ] Bulk images attach correctly and unmatched files are reported.

---

## Phase 3A — Product cards and product detail page

```text
Read `@docs/PROJECT_SPEC.md` sections 4.2, 4.6, 4.11–4.12, 6.2, 8, 10, 11.6–11.8 (M30–M32, M40–M45) and 13, plus `.cursor/rules/project.mdc`.

PHASE 3A — Build reusable product cards and the product detail page.

1. Build the shared storefront shell/components needed for this phase: product-card, price, stock-badge, sale-badge, breadcrumbs, section-title, trust-block, free-shipping strip/bar, whatsapp-button, rating-stars, placeholder-image, pagination and toast. Use Arabic language files.
2. Build `/products/{slug}` mobile-first: gallery with Swiper thumbnails/zoom, product identity/model/brand/dealer badge, price and stock, variant picker, technical attribute/spec table, quantity tiers, unit-aware quantity stepper, add-to-cart placeholder until Phase 5A, WhatsApp prefill, related items, approved review display and mobile sticky buy bar.
3. Variant selection updates the price, sale end-date logic, stock, SKU, image and WhatsApp data; inactive variants cannot be selected.
4. Quantity stepper enforces min/step/max and displays the unit. Do not trust client pricing. Until Phase 5A, add-to-cart must be clearly disabled or a harmless non-action placeholder; do not fake success.
5. Use eager/fetchpriority high for the primary product image; below-fold images lazy-load with width/height. No LCP image reveal animation.
6. Implement M30, M31, M32, M40, M41, M42, M44, M45 in the catalog; M43 waits for actual server-side cart implementation in Phase 5A.
7. Respect full/lite/off and reduced motion. Do not add new motion IDs without updating §11.8 and `/_ui`.
8. Add Pest tests for PricingService/product page privacy where applicable; verify no cost_price/admin fields in rendered HTML/JSON.

Do not build category filters, live search, cart or checkout.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Variant changes price/stock/SKU/image.
- [ ] Valid sale and invalid/expired sale display correctly.
- [ ] Fractional unit and quantity tiers calculate correctly.
- [ ] Out-of-stock item cannot be added; WhatsApp says ask for availability.
- [ ] Product page is correct at 360px and desktop; no CLS from image or sticky bar.
- [ ] Reduced-motion and off state preserve content/static state.

---

## Phase 3B — Category/brand listings and technical filters

```text
Read `@docs/PROJECT_SPEC.md` sections 4.12, 6.3, 6.10, 8, 10, 11.6–11.8 (M33, M34, M72) and 13.

PHASE 3B — Build the reusable category/brand listing page and data-driven filters.

1. Use one reusable listing component for `/categories/{slug}` and `/brands/{slug}`. Parent category includes active products from its descendants.
2. Grid cards show primary image, name, brand, model code, unit-aware price, sale badge, stock state and functional add-to-cart placeholder until Phase 5A.
3. Build filters: multi-brand, price range, in-stock breaker switch, child category chips, and active filterable category attributes inherited from parent definitions. Numeric fields use sensible ranges/chips; select/boolean use validated options.
4. Filter options must reflect result set and not show empty values. Sanitize and whitelist attribute filter keys/options server-side.
5. Put all filters, sort and page in URL query string; preserve state on refresh, copied URL and browser back/forward. Pagination 24 items.
6. Desktop sidebar; mobile bottom-sheet drawer with keyboard focus management, escape close, background interaction handling and ≥44px controls. Use native dialog or a tested native focus-management approach; do not add focus-trap packages or assume Alpine x-trap exists.
7. Sort latest, lowest price, highest price, best sellers. Use stable secondary sorting to avoid item jumps.
8. M34 skeleton/loading state, M33 filter-sheet/breaker/chips, M72 empty-search scene. Empty state has useful text, clear-filters action and WhatsApp link if configured.
9. Filtered and search URLs noindex and canonical to base listing as specified; never block them in robots.txt.
10. Add tests for parent-child category inclusion, valid/invalid technical filters, query-string persistence, sorting, availability and unit pricing.

Do not implement global live search (Phase 3C) or cart behavior (Phase 5A).

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Parent category includes its child products.
- [ ] Filters work independently and together, including technical attributes.
- [ ] Copied filtered URL restores exact filters/sort/page.
- [ ] Mobile sheet is accessible, correct RTL and does not scroll the background unintentionally.
- [ ] 24 products per page; empty result can clear filters.
- [ ] No N+1 or unbounded technical attribute queries.

---

## Phase 3C — Arabic/model-code live search

```text
Read `@docs/PROJECT_SPEC.md` sections 6.4, 8.4, 9, 10, 11.8 (M16, M35) and 13.

PHASE 3C — Header live search and results page.

1. Build `/search?q=` using the existing listing component; preserve query string and noindex/canonical rules.
2. Build `search_text` using normalized product name, model code, brand and variant SKU. Rebuild on product/brand/variant changes without N+1.
3. Normalize Arabic-Indic digits, Alef variants, Teh Marbuta, Alef Maqsura, tashkeel, tatweel, whitespace and Latin case as §8.4. Do not transliterate brand/product terms without an explicitly tested mapping.
4. All tokens must match. Rank exact model code/SKU first, then exact tokens, then partial matches. Debounce 300ms; show top six with thumb/name/model/unit price, skeleton while loading and link to full results.
5. Keyboard support: arrows, Enter, Escape, visible focus, correct Arabic/Latin direction. On mobile, open a full-width search panel without losing the underlying page state.
6. Add tests for Arabic spelling variants, model code, brand, SKU, multiword AND behavior and inactive/soft-deleted products.
7. Implement M16 and M35; no ambient or decorative typing effects.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Searches for Arabic category terms, brand and exact model code return correct products.
- [ ] Arabic digit/model input normalizes correctly.
- [ ] Search is keyboard and touch accessible.
- [ ] Live search cancels/stales old requests safely; loading state does not flash.

---

## Phase 4A — Header, navigation, footer and static pages/forms

```text
Read `@docs/PROJECT_SPEC.md` sections 6.1, 6.5, 6.9, 10, 11.6–11.8 (M01–M13, M15–M16; M14 is Phase 5A) and 13.

PHASE 4A — Global layout and content pages.

1. Final header with topbar, logo, live search, cart count, account, phone(s), sticky constant height. Values from settings only.
2. Desktop category nav/mega menu from cached tree; mobile hamburger drawer and category accordions. Mobile bottom nav remains out of scope.
3. Topbar marquee must pause on hover/focus/touch and become static in lite/off/reduced motion. No header height changes. M10 scroll state animates opacity overlay/border only; never animate box-shadow.
4. Footer with policies, contact, social, payment marks from settings/pages; WhatsApp float hidden when number empty and positioned above sticky mobile buy/cart controls.
5. Static `/pages/{slug}`, `/contact`, `/wholesale`; validate, throttle, honeypot and store records. Render admin rich text only after sanitization.
6. Build corresponding Filament resources for Pages, ContactMessages and WholesaleRequests, with Arabic labels and authorization.
7. Implement the assigned header/footer/navigation motion rows (M01–M13, M15–M16). M14 cart-icon feedback is implemented only in Phase 5A. Run motion QA.

Do not build homepage content beyond the shell, nor cart/checkout.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Header/footer are data-driven; no hardcoded business identity.
- [ ] Desktop mega menu and mobile drawer work correctly in RTL.
- [ ] Topbar respects lite/off/reduced motion and pauses on interaction.
- [ ] Contact/wholesale messages appear in admin; throttle/honeypot works.
- [ ] No WhatsApp button appears when number is empty.

---

## Phase 4B — Homepage and homepage motion

```text
Read `@docs/PROJECT_SPEC.md` sections 4.1, 5, 6.5, 10.6, 11.6–11.8 (M20, M22–M24, M26–M28; defer P3 M21/M25 to Phase 9B) and 13.

PHASE 4B — Data-driven home page.

1. Build homepage sections in order: hero, promo tiles, category grid, brands strip, best sellers, latest arrivals, why-us, wholesale banner, footer.
2. Home banners come from active/date-valid database records and settings. Hero 3–5 slides; promo 2–3; hide empty sections. No fake customer counts, statistics or testimonials.
3. Hero Swiper RTL: first slide eager/fetchpriority high/no reveal. Autoplay only at full motion, ≥5 seconds, with visible pause/play; stop for lite/off/reduced motion, user pause, keyboard focus and interaction. Brand strip is manually swipeable and never auto-advances.
4. Product/category data is eager-loaded/cached with invalidation; best sellers use delivered sales net of returned orders; newest is ordered stably.
5. Add locally generated placeholder banners/images only (no external downloads); real images later uploaded in admin.
6. Implement M20, M22–M24 and M26–M28 according to §11.8. Defer P3 motions M21 and M25 to Phase 9B. No extra reveal effects above the fold.
7. Check LCP/CLS, mobile 360px, RTL, touch, keyboard and all motion levels.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] All homepage sections are data-driven and hide when empty.
- [ ] Hero pause/play works; autoplay obeys motion and accessibility rules.
- [ ] Category icons and why-us blocks use the approved icon whitelist.
- [ ] Updating banners/settings changes the home page after cache invalidation.
- [ ] No invented social proof or statistics.

---

## Phase 5A — Cart, pricing revalidation and cart animation

```text
Read `@docs/PROJECT_SPEC.md` sections 4.2–4.6, 4.11, 6.2, 6.6, 11.5–11.8 (M14, M43, M50–M53) and 13.

PHASE 5A — Session cart and pricing correctness.

1. CartService stores only product_id, variant_id and decimal quantity. Never store authoritative price/total in session.
2. Recompute price, active sale, quantity tier, unit, status and current stock from DB on every render and again at order creation.
3. Add-to-cart actions validate active product/variant, min/step/max, fractional precision, stock and unit. Return a clear Arabic error if anything changed; dispatch `cart-added` only after server success.
4. Build cart lines with unit label, price-per-unit, quantity, line total, update/remove, coupon apply/remove and Arabic errors. Handle stale/deactivated/deleted/out-of-stock lines safely.
5. CouponService and ShippingService follow §4.3–4.5. One coupon only; race-safe final validation occurs in order transaction in Phase 5B.
6. E11 cable free-shipping bar uses post-coupon product subtotal; hidden when threshold 0; no governorate means delivery fee not yet calculated.
7. Add toast and functional header cart count. Implement M14, M43, M50–M53. Fly-to-cart/spark run only after server success; never optimism for money or stock. Lite = badge/toast only; off = static state.
8. Add tests for fractional cart quantities, quantity steps, sale/tier recalculation, stock clamps, coupon rules, shipping threshold and stale cart lines.

Do not build checkout/order creation/payment here.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Add/update/remove cart items works for piece and meter products.
- [ ] Invalid fractional steps and over-stock are rejected server-side.
- [ ] Price and quantity tier always recompute from DB.
- [ ] Coupon and shipping preview are correct; final check reserved for Phase 5B.
- [ ] Fly-to-cart only happens after successful server response.

---

## Phase 5B — Checkout, transactional order creation and success scene

```text
Read `@docs/PROJECT_SPEC.md` sections 4.2–4.11, 6.7, 8, 9, 11.6–11.8 (M60–M63) and 13.

PHASE 5B — This is the highest-risk phase. Correctness and atomicity matter more than speed.

1. One-page guest checkout; account never required. Required name, Egyptian mobile, governorate, city, detailed address; optional email, second phone and notes. Prefill logged-in profile/default address.
2. Governorate selector uses active rates only; update shipping live. Payment methods come from settings. COD default; bank transfer details visible when selected; card remains hidden until Phase 10 enablement.
3. Validate phone and normalize Arabic digits/prefixes. Add CSRF, throttle, honeypot and double-submit/idempotency protection.
4. Implement StockService and OrderService::create in ONE DB transaction. Lock product/variant stock rows sorted by id, then coupon row; re-read active prices, variant, quantity step/min/max, stock, coupon and shipping. Abort all writes if any line fails.
5. Deduct decimal stock in the product's base sale unit; write stock movement. Save immutable order-item snapshots including unit and cost. Generate order number after initial insert in the same transaction. Save first status history. Increment coupon once. Do not increment sales_count until delivered.
6. Calculate all totals with decimal-safe helpers and store order snapshots. Clear cart and set success-session authorization only after transaction commits.
7. Success route requires the creating session or owning user; no order-number-only access. Copy order number and show scene M63 after order is committed. Animation must never delay redirect/confirmation.
8. Test concurrent stock purchase and `usage_limit=1` coupon on MySQL-compatible DB with two independent transactions/connections. Include fractional quantities, exact free-shipping boundary and one currency unit/minor-unit rounding cases.
9. Add Pest tests for guest checkout, invalid phone, prices/tier/discount/coupon/shipping, stock atomic rollback, duplicate submit, fractional unit and success-page authorization.

Do not build admin order management, customer account pages or Paymob.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Guest order succeeds end-to-end with COD.
- [ ] Checkout revalidates price, active status, variant, stock, coupon and shipping.
- [ ] One unavailable/invalid item causes full rollback; no partial order or stock deduction.
- [ ] Fractional quantity and unit snapshots survive the order.
- [ ] Duplicate confirmation does not create duplicate orders.
- [ ] Success page blocked for a different session/user.
- [ ] Pest and DB concurrency tests pass, or concurrency tests are clearly marked NOT RUN with reason.

---

## Phase 6 — Admin orders, stock disposition, invoices and dashboard

```text
Read `@docs/PROJECT_SPEC.md` sections 4.6–4.8, 5, 7, 7.2, 8 and 13.

PHASE 6 — Order operations.

1. Implement `OrderService::changeStatus()` as the only status-transition path. Enforce the exact transition graph. Write history records in the same transaction.
2. Cancel before shipping: restore stock once and release coupon use once. Cancel after shipping: stock disposition pending inspection. Return after delivery: pending inspection; never restore automatically.
3. Implement `OrderService::resolveStockDisposition()` for authorized admins: after received/inspected, choose `restored` or `written_off`, require note, set actor/time and write stock movement only on actual restock. Repeated action must be idempotently rejected/no-op.
4. On delivered COD set payment paid; increment sales_count once. On returned, reverse sales_count once only (never below zero). Record reversal timestamp.
5. Filament Orders: status/search/date/payment filters, new-count badge, detailed order with item snapshots/unit quantities, history, shipping/tracking/admin notes, WhatsApp prefill, invoice print, manual mark bank transfer paid only after independent receipt verification (with audit). COD is paid only on delivery; Paymob is paid only by verified callback. Add a pending-inspection action that applies one order-level restock/write-off decision to all tracked lines only after physical receipt, mandatory admin note and acting user. Partial/mixed item returns are out of scope. If a paid order is cancelled/returned, flag payment reconciliation; do not assume a refund happened.
6. Show bank-transfer orders awaiting payment and aging duration; no automatic bank-transfer expiry.
7. Dashboard: today's orders/revenue, new orders, pending transfers/aging, low stock and top five net-best-selling products for last 30 days.
8. Add notification on new order (Filament DB notification); mail is best-effort and cannot fail checkout.
9. Manual stock adjustment validates decimals and unit, requires reason/note and logs stock movement.
10. Printable invoice is A4 RTL commercial receipt, correct unit/quantity totals; no motion in print; never label it a tax invoice.
11. Add tests for every allowed/forbidden transition, cancellation timing, restock/written-off idempotency, coupon release, sales reversal, permissions and invoice snapshots.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Order status timeline and audit history are correct.
- [ ] Pre-shipment cancel restores stock once; shipped cancel/return await inspection.
- [ ] Restock and write-off actions require admin/note and cannot run twice.
- [ ] Delivered increments sales; returned reverses exactly once.
- [ ] Dashboard and printable invoice show correct decimal units and totals.

---

## Phase 7 — Customer account, wishlist, reviews and order tracking

```text
Read `@docs/PROJECT_SPEC.md` sections 4.7, 4.9, 6.8, 8, 9, 11.8 (M64, M73, M80) and 13.

PHASE 7 — Customer self-service.

1. Registration/login with one identifier accepting Egyptian phone or email and password; normalize phone; rate-limit. Password reset by email only if configured. No OTP in v1.
2. Build account orders/list/detail, profile/password, saved addresses/default address and wishlist. Prefill checkout from default address.
3. Customers can cancel only their own `new` order via OrderService. Never expose another user's data by changing URL IDs.
4. Reviews: one per user/product; pending moderation; verified purchase true only when the user has a delivered order item; aggregate rating from approved reviews only.
5. `/track-order`: order number + phone exact match, rate-limited, generic mismatch response, no data leakage.
6. Guest order does not silently attach to a later account just because phone matches. Account orders only show orders linked by user_id; guest uses tracking.
7. Render circuit status timeline M64 using static/one-time emphasis only (no infinite current-node pulse). Build the empty-wishlist scene M73. Wishlist pop M80 is finite and reduced-motion aware.
8. Add authorization/ownership tests, phone/email login tests, review rules, guest tracking mismatch, CSRF/throttle and account UI QA.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Login by phone and by email works.
- [ ] Customer cannot see another customer's order/address.
- [ ] Customer cancellation only works while status is new.
- [ ] Guest tracking requires both matching order number and phone.
- [ ] Review moderation and verified-purchase flag are accurate.

---

## Phase 8 — Coupons, shipping, settings and remaining admin

```text
Read `@docs/PROJECT_SPEC.md` sections 4.1, 4.3, 4.4, 4.10, 7 and 13.

PHASE 8 — Complete day-to-day owner controls.

1. Filament Coupons with dates, caps, usage counts and active toggle. Ensure used_count reflects cancellation/return rules.
2. Shipping Rates with 27 governorates, editable fees/active/estimated delivery days. Seed fees are placeholders; label them as demo until owner replaces them.
3. Settings page tabs: Store, Contact, Shipping, Payment, Homepage, Social, Appearance (`motion_level`), Analytics (`GA4`, Search Console), tax note. Every setting in §4.1 is editable and cache invalidation is correct.
4. `why_us_blocks` repeater uses whitelist icons and preview. Banner image upload has desktop/mobile previews. Customers resource read-only with linked orders.
5. Audit storefront for hardcoded store data, phone numbers, free-shipping threshold, delivery copy, bank details and tax claims. Use settings/pages/shipping tables.
6. Admin sidebar order: Orders, Catalog, Marketing, Content, Settings. Arabic labels, permission checks and accessible controls.
7. Ensure switching motion setting affects all storefront pages on next render/navigation and cannot override OS reduced-motion.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Editing free-shipping threshold updates product strip, cart progress and checkout total.
- [ ] Editing WhatsApp/phones/settings updates every relevant screen.
- [ ] Disabled governorate is unavailable in checkout.
- [ ] Motion full/lite/off is owner-configurable and browser can only downgrade.
- [ ] No hardcoded store values remain in storefront templates.

---

## Phase 9A — SEO, analytics, performance and accessibility pass

```text
Read `@docs/PROJECT_SPEC.md` sections 8–10, 11.2–11.5, 11.9–11.10 and 13.

PHASE 9A — Quality and discoverability.

1. SEO: unique titles/descriptions, canonical, OG/Twitter, Product/Offer/availability/aggregateRating JSON-LD, BreadcrumbList, Organization/LocalBusiness, sitemap and robots. Noindex search/filter/cart/checkout/account; `/_ui` never indexed.
2. Performance: WebP/srcset, explicit dimensions, lazy loading, eager LCP image, self-hosted Cairo with swap, defer noncritical JS, cache/invalidation, N+1 inspection, indexes.
3. Analytics: GA4 events `view_item`, `add_to_cart`, `begin_checkout`, `purchase`; never send cost price, phone, address, email, order-sensitive personal data or payment secrets. Purchase event fires once after successful order creation/confirmed transaction semantics; deduplicate across reload/back navigation.
4. `wire:navigate` page views are tracked on `livewire:navigated`; avoid duplicate events on full page plus SPA navigation.
5. Performance budgets: Lighthouse mobile ≥80 on home/category/product with full motion; CLS<0.1; motion JS ≤20 KB gzip excluding Swiper/Auto Animate; record real results and mark NOT RUN when unavailable.
6. Accessibility: keyboard, visible focus, contrast, image alt, form labels, aria-live, reduced motion, tap target and dialog/sheet checks.
7. Do not add packages beyond §2.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Sitemap, robots, canonical and structured data validate.
- [ ] GA4 ecommerce events fire once and exclude PII/secrets.
- [ ] Lighthouse results recorded for three page types or marked NOT RUN.
- [ ] 360px/mobile and desktop accessibility checks documented.

---

## Phase 9B — Error scenes and final motion integration QA

```text
Read `@docs/PROJECT_SPEC.md` sections 6.10, 9–11 and 13.

PHASE 9B — Complete error scenes and motion catalog integration.

1. Build Arabic custom 404/500 pages, empty search/listing/cart/wishlist states from §6.10. Buttons perform real actions: 404 plug plays finite animation; 500 breaker retries reload; links/search work even with JS off.
2. Implement deferred home-only P3 motions M21 and M25, plus error scenes M70–M71. Verify previously implemented M72 (Phase 3B) and M73 (Phase 7) rather than rebuilding them. Complete any deferred P2/P3 catalog row and document its final state. Do not add unlisted motion.
3. Resolve every page/phase mapping in §11.8 against PHASE_PROMPTS.md. Report any catalog row not built and reason; never silently omit it.
4. Run all motion QA with full/lite/off/reduced-motion, Save-Data/2G, low memory, JS disabled, repeated `wire:navigate`, back/forward, CPU throttle and Slow 4G.
5. Confirm global max-three ambient loops and the first screen/LCP images never animate in.
6. Check no animation modifies business operations or optimistic prices/stock/order/payment state.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] 404/500/empty scenes work without JavaScript and controls have useful behavior.
- [ ] Every E01–E16 component and M catalog row is accounted for.
- [ ] No unapproved looping animations or visual-property violations remain.
- [ ] Motion full/lite/off and reduced-motion are verified across navigation.

---

## Phase 10 — Paymob card payment and bank-transfer receipt

```text
Read `@docs/PROJECT_SPEC.md` sections 4.8, 5, 6.7, 7, 9 and 13.

PHASE 10 — Online payment. Keep card payment disabled until verified test-mode success.

1. Before coding, inspect the merchant's actual Paymob account/integration capability and the current official Paymob Accept docs. The intended default is classic Accept iFrame/hosted checkout with HMAC callback. If unavailable, stop and report; do not silently substitute Intention/Unified Checkout.
2. Bank transfer: allow authenticated owner/customer order-access policy to upload a receipt image <=4MB; store privately; customer can only upload/view proof for their own linked order or a guest session-authorized order using a separately protected flow. Admin sees proof and may manually mark **bank-transfer only** as paid after independent verification, with an audit note. COD becomes paid only on delivered; Paymob is paid only by callback. Receipt upload does not itself change payment status.
3. Card order: create pending order first, deduct stock in normal transaction, create payment attempt and redirect. Store provider reference/expiry and payment_transactions records.
4. Callback: exempt only the exact server-to-server route; follow the provider's documented HMAC canonical field order and use constant-time comparison. Validate merchant reference/order, amount against both the locked payment attempt and immutable order total, currency, success status and unique provider transaction ID. Lock order/attempt and process idempotently. Callback is the only source of Paymob paid/failed state. If another distinct attempt already paid the order, record the second successful payment and flag duplicate-payment refund/reconciliation without changing order totals or stock.
5. Browser return page only reads order payment status; never marks paid from query parameters or redirect result.
6. If callback is valid successful but order was already cancelled/stock restored, record the real payment (`payment_status=paid`) and flag `payment_reconciliation_required`; do not revive the order or deduct/restore stock again. If a second distinct successful attempt arrives after the first already paid the order, record it and flag duplicate-payment reconciliation. Any paid order later cancelled/returned also needs reconciliation. Admin processes a full refund externally through the merchant dashboard and records refund reference/note; no refund API or partial refunds are integrated in v1.
7. Expire only unpaid card orders older than 60 minutes using scheduler and OrderService; when no success callback exists, mark the attempt/order payment state failed/expired and cancel the order with stock restored exactly once if eligible. No automatic expiry for bank-transfer orders. Retry creates a distinct payment attempt without duplicating the order or stock movements; handle the race between expiry and callbacks under the same order/attempt locks.
8. Dedicated log channel; redact all secrets/card data/tokens. Add `.env.example` keys only, never real values.
9. Pest HTTP-mocked tests: valid/invalid HMAC, wrong amount/currency/reference, duplicate callback, second distinct successful attempt, failed callback, late success after cancel, retry, expiry/callback race, stock restore exactly once, external-refund record and private receipt ownership.
10. Enable `payment_card_enabled` only after test-mode verification and merchant credentials are configured.

Finish with §13 report and STOP.
```

Acceptance checklist:
- [ ] Test-mode successful callback marks paid exactly once.
- [ ] Invalid signature or mismatched amount/currency/reference is rejected.
- [ ] Duplicate callback cannot double-process.
- [ ] Late payment after expiry flags reconciliation and never revives/restocks order.
- [ ] Bank receipt is private and cannot be fetched by another customer.
- [ ] Payment remains disabled without credentials and verified account support.

---

## Phase 11 — Security hardening, full regression and deployment

```text
Read FULL `@docs/PROJECT_SPEC.md`, especially sections 2, 4–9, 11.10, 12–14, plus `.cursor/rules/project.mdc`.

PHASE 11 — Production readiness.

1. Security audit: auth/admin policies, account ownership, mass assignment, CSRF, rate limits, honeypot, validation, upload MIME/content, sanitized rich text, private receipts, SQL/query safety, secrets, error pages and information leakage.
2. Explicitly test no cost_price/admin_notes/provider secrets/payment proof URLs appear in storefront HTML, JSON, search, structured data or analytics payloads.
3. Run all Pest tests and Pint. Include decimal quantities and MySQL-compatible concurrent stock/coupon tests. Report test count/results and any unrun test.
4. Review database migrations/indexes for production engine. Test `php artisan migrate --force`, `php artisan optimize`, storage permissions, cache/session, scheduler and backup/restore.
5. Create `.env.production.example` with APP_ENV=production, APP_DEBUG=false, APP_URL, database, mail, cache/session/queue and Paymob blank variables. Never include real credentials.
6. Write `docs/DEPLOYMENT.md` in Arabic for a non-expert: cPanel/shared-hosting setup, document root to `/public` or safe folder restructure, Composer `--no-dev`, local asset build and `public/build` upload, migrations, production seeder only (essential settings/pages/governorates, no demo products), storage link, SSL, cron, permissions, backups, logs, admin password rotation and smoke test.
7. Add a command to purge demo data safely with explicit confirmation and environment guard; never purge production data accidentally.
8. Rehearse deployment on staging and complete one end-to-end order on a real phone. If no staging/real phone/Paymob account is available, mark that item NOT RUN; do not claim deployment complete.
9. Final audit all requirements in §13 and all motion IDs. Do not introduce new packages.

Finish with final report and STOP.
```

Acceptance checklist:
- [ ] `php artisan test` passes and Pint passes.
- [ ] Sensitive-field leakage test passes.
- [ ] Production seeder contains no demo products or default known password.
- [ ] Deployment guide complete and staging rehearsal documented.
- [ ] Real-device checkout test done or marked NOT RUN.
- [ ] Payment and inventory reconciliation behavior is documented.

---

## Ready-made prompts for common problems

### When an error occurs

```text
I performed [exact action] on [URL/page] and got this error:
[full error / stack trace]
Expected: [expected behavior]. Actual: [actual behavior].
Read the relevant PROJECT_SPEC section and reproduce the root cause. Fix only what is necessary, add a regression test for logic/security issues, run the relevant tests, and report exact results. Do not refactor unrelated files.
```

### When layout/RTL is wrong on mobile

```text
At viewport width [width]px, [component] on [page] has this issue: [describe]. Fix it for RTL/mobile without changing approved desktop behavior. Check equivalent shared components, keyboard use and 360px overflow. Follow the design tokens and motion rules; do not add a package.
```

### When Cursor deviates from the spec

```text
You deviated from @docs/PROJECT_SPEC.md section [X]: [specific deviation]. Explain the mismatch, revert only the unauthorized part and implement the spec. Do not invent a new business rule or dependency. Add/adjust a test when the deviation affects logic.
```

### Before moving to the next phase

```text
Audit the completed phase against its exact PHASE_PROMPTS.md acceptance checklist and PROJECT_SPEC.md §13. Do not edit code. List passed checks with evidence, failed checks, NOT RUN checks with reasons, motion IDs completed/missing, and any regression risks. STOP after the audit.
```

### Visual/motion regression

```text
The animation [catalog ID] on [page] fails in [full/lite/off/reduced-motion] after [wire:navigate/Livewire morph/browser back]. Reproduce it, preserve the final static state and business behavior, fix using only allowed transform/opacity (or documented small-SVG exceptions), update /_ui if needed, and run §11.10. Report exact tested conditions.
```
