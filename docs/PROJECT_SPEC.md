# Electrical Tools Store — Project Specification (v3)

> **Instructions to the AI (read first).**
> This file is the single source of truth for the project. Follow it exactly.
> - Read this complete file before starting a new phase, then read the exact phase prompt in `PHASE_PROMPTS.md` and the always-applied `.cursor/rules/project.mdc`.
> - Do not invent business rules. **V3 resolves the previously open decisions in §4.10**; use those defaults without asking again. If a genuinely new blocker appears, report it and stop only the affected task.
> - Never hardcode store identity, phone numbers, prices, shipping fees, thresholds, editable copy, analytics IDs, payment details or customer-visible business settings. Use the database (`settings`, `shipping_rates`, `pages`, `banners`) and translation files as specified.
> - Build only the phase requested. Do not start a later phase or refactor unrelated files.
> - All customer-facing text is **Arabic (RTL)**. Code, comments, identifiers, database keys, logs and commit messages are **English**.
> - **Version rule:** target **PHP 8.3+ / Laravel 13 / Filament 5 / Livewire 4 / Tailwind CSS 4.1+**. Never write APIs from memory. Inspect installed versions (`composer show`, `npm ls`) and use Laravel Boost plus the official documentation for those exact installed versions before writing version-sensitive code. Commit `composer.lock` and `package-lock.json` (or the chosen npm lockfile).
> - **Motion rule:** every animation follows §11 (Electric Motion System), the animation catalog, and the specific phase-to-motion mapping in `PHASE_PROMPTS.md`. Do not invent extra animation styles, libraries or effects.
> - **Honesty rule:** never claim a test, browser check, Lighthouse run, package-compatibility check or deployment succeeded unless it was actually run. Include the exact command and concise result in the phase report. Mark anything not run as NOT RUN.

**What changed in v3:** Laravel 13 is the fixed target; Tailwind CSS 4.1+ and its Vite plugin are explicit; selling units and fractional quantities are supported; category-specific technical attributes can drive filters; order returns and stock disposition are made safe; payment callbacks are strengthened; motion rules and stage mapping are reconciled; phase prompts and the always-on Cursor rule file are aligned with this specification.

---

## 1. Overview

An Arabic e-commerce website for an **electrical tools & supplies shop in Egypt** (wiring, breakers, switches & sockets, lighting, power tools, smart home, cameras, home appliances).

- Customers: homeowners, electricians, contractors. **Most traffic is mobile on weak connections.**
- Main goals: fast catalog browsing, easy ordering **without registration**, WhatsApp contact, and a simple admin panel the owner (non-programmer) uses daily.
- Structure inspired by large Egyptian electrical e-shops. **Do not copy any competitor's text, images or exact design.**
- **Experience goal (v2):** the store should feel *alive with electricity* — subtle, purposeful electrical motion layered on a fast, clean, trustworthy store. Motion supports clarity (feedback, state, attention); it never costs speed. On weak phones and slow networks the site automatically degrades to a lighter motion level (§11.3).

### Core user flows
1. Browse category → filter → open product → add to cart → checkout as guest (Cash on Delivery) → success page with order number.
2. Find product by name / model code / brand via search.
3. Contact via WhatsApp from any page or from a product page (pre-filled message).
4. Owner: sees new orders → confirms via WhatsApp → updates status → prints invoice → enters tracking number.

---

## 2. Tech stack (fixed — do not substitute without approval)

| Layer | Fixed choice |
|---|---|
| Language / framework | PHP **8.3+**, **Laravel 13.x** (`^13.0`) |
| Database | MySQL 8.0+ preferred; MariaDB 10.6+ compatibility target. Test the actual production engine for locking and decimal behavior. |
| Storefront | Blade + **Livewire 4.x** (`^4.0`) + Alpine.js supplied by Livewire + `wire:navigate`; use class-based Livewire components only. |
| CSS / build | **Tailwind CSS 4.1+** + `@tailwindcss/vite` + Vite; CSS-first `@theme` configuration. |
| Admin | **Filament 5.x** (`^5.0`), Arabic labels and RTL. |
| Media | `spatie/laravel-medialibrary` (Laravel 13-compatible release) + `filament/spatie-laravel-media-library-plugin:^5.0`; WebP conversions (`thumb` 400px, `medium` 800px, `large` 1200px). |
| Excel | `maatwebsite/excel:^4.0`; no queue worker assumed on shared hosting; process at most 200 rows per HTTP request. |
| Carousel / gallery | Swiper (npm); import only Autoplay, Pagination, Navigation, Thumbs, Zoom and EffectFade modules actually used. |
| List animation | `@formkit/auto-animate` (npm); use only for small lists with stable `wire:key`. |
| Icons | Lucide SVG artwork copied into inline-SVG Blade components + custom electric SVGs. **No icon package, font or emoji in the UI.** |
| Tests / style | Pest, Laravel Pint. Feature tests are mandatory for pricing, fractional quantities, stock, orders, coupons, payments and authorization. |
| Development only | `laravel/boost` (version-aware official docs search), `barryvdh/laravel-debugbar`. |

### 2.1 Installation and compatibility rules
- Laravel: create the project on Laravel 13 and use `^13.0`. Do not start on Laravel 12 and later upgrade as a shortcut.
- Filament: use `^5.0`; Livewire: use `^4.0`; Tailwind: `^4.1`; install/configure `@tailwindcss/vite` as the Tailwind Vite integration. Do not use Filament 3, Livewire 3 or Tailwind 3 APIs/examples.
- Filament's Spatie Media Library integration must be the v5-compatible package. Spatie Media Library itself must resolve to a release whose Composer constraints explicitly support Laravel 13.
- Laravel Excel must be v4.x; its required PHP extensions are listed in §2.2.
- Before installing packages, run `composer show` / inspect Packagist constraints and official package docs. If Composer cannot resolve the listed stack together, do not downgrade or silently substitute a dependency: report the exact conflict and stop that installation step.
- Use the newest compatible patch release within the fixed major constraints; record installed versions in the phase report and commit lockfiles. Do not pin old patch versions in prose.
- **No package outside this list without the owner's approval.** In particular, do not install GSAP, Lottie/dotLottie, AOS, jQuery, canvas-confetti, UI kits (Flux etc.), icon packages, Three.js/WebGL, Alpine separately, or an extra animation framework.
- Motion is implemented with CSS, the already-listed Swiper and Auto Animate, and browser-native APIs (`IntersectionObserver`, `ResizeObserver` only if needed, Web Animations API, `requestAnimationFrame` sparingly). Do not install a separate scroll/reveal/focus-trap library.
- Queue driver is `sync` by default. Sessions and cache use `file` or `database` drivers suitable for shared hosting. Timezone `Africa/Cairo`, locale `ar`, fallback `ar`, currency `EGP`.
- Customer authentication uses Laravel built-ins. No social login, OTP, SMS, WhatsApp API automation or customer account requirement for checkout in v1.
- Livewire components are class-based in `app/Livewire`; do not mix class-based and single-file components. Verify generator flags against Livewire 4 docs.

### 2.2 Hosting prerequisites
PHP 8.3+, Composer (or the ability to upload `vendor/`), Node.js 20+ for the Tailwind 4 build workflow, MySQL 8 / MariaDB 10.6+, `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `openssl`, `curl`, `ctype`, `dom`, `filter`, `hash`, `tokenizer`, `xml`, `zip`, **`bcmath`**, `gd` with WebP support, `iconv`, `simplexml`, `xmlreader`, `zlib`, `exif`, cron access and SSL. BCMath is required for overflow-safe decimal string calculations; do not replace it with PHP floats. Confirm PHP extensions in the actual CLI **and** web PHP configurations. If GD WebP is unavailable, verify Imagick WebP support before deployment.

### 2.3 Browser support
Tailwind CSS 4 targets modern browsers: Chrome 111+, Safari 16.4+, Firefox 128+ and current Chromium-based Edge. Do not promise support for Internet Explorer or older browsers outside that baseline. The storefront must still degrade gracefully: core links, prices, images, forms and ordering remain usable when motion JavaScript is unavailable. Avoid optional bleeding-edge CSS utilities if they are not needed.

---

## 3. Architecture & conventions

- Thin controllers / Livewire components. **Business logic lives in `app/Services`:**
  - `PricingService` — effective unit price, sale rules, quantity tiers and server-side line totals.
  - `QuantityService` — validate/normalize decimal quantities and step increments without binary floating-point arithmetic.
  - `CatalogAttributeService` — resolve inherited category attributes and filterable technical specifications.
  - `CartService` — session cart stores identifiers and quantities only.
  - `CouponService` — validate and compute one coupon per order.
  - `ShippingService` — shipping fee by active governorate and free-shipping rule.
  - `StockService` — lock, deduct, restore and write stock movements.
  - `OrderService` — create orders transactionally; the sole service allowed to change order status and resolve stock disposition.
  - `PaymentService` / provider adapter — create and reconcile payment attempts; only verified server callbacks can change online payment state.
- Statuses/types are **PHP backed enums** in `app/Enums` with a `label()` method returning Arabic text and a `color()` for badges.
- Validation in **Form Requests** (or Livewire form objects). Authorization via Policies / `canAccessPanel`.
- Eager-load relations; never N+1 on listing pages. Add DB indexes on: slugs, foreign keys, `is_active`, `price`, `created_at`, `order_number`, `phone`.
- Cache (with invalidation via model observers): settings, category tree, brands list, home sections.
- Money columns use `decimal(12,2)` and quantities use `decimal(12,3)`. Never use PHP/JS binary floats for authoritative money or stock arithmetic. Use decimal strings and PHP BCMath through tested `Money` / `QuantityService` wrappers; implement and test half-up rounding to 2 decimals at documented boundaries. Do not expose raw BCMath calls throughout controllers/components. Compute totals **only on the server**; never trust prices, discounts, stock or totals from the client. **Animations never block, delay or alter any money/stock/order operation** — the server result comes first, the animation reacts to it.
- **Lock order (v2 fix):** inside the order transaction, lock stock rows first (ordered by id), then the coupon row (`lockForUpdate`). Always the same order, to avoid deadlocks and to make coupon `usage_limit` race-safe.
- UI strings in `lang/ar.json` / `lang/ar/*.php` (not scattered literals), so the owner can edit wording.
- Folder layout:
  - `app/Services`, `app/Enums`, `app/Support` (helpers: `ArabicNormalizer`, `Phone`, `Money`), `app/Livewire`, `app/Filament`
  - `config/icons.php` — **whitelist of allowed icon keys** (key → Arabic label) used by admin selects (category icon, why-us icon) and by `<x-electric.tool-icon>`
  - `resources/views/{layouts,components,pages,...}`
  - `resources/views/components/electric/` — electric motion Blade components (§11.6)
  - `resources/views/components/icons/` — inline-SVG icons (Lucide copies + custom electric icons)
  - `resources/js/motion/` — motion modules (§11.5)
  - `resources/css/app.css` — Tailwind 4 `@theme` tokens and motion CSS
  - `config/units.php` — allowed sale-unit keys, Arabic labels, quantity precision/step rules
  - `app/Services/QuantityService.php` — decimal quantity validation and arithmetic
  - `app/Services/CatalogAttributeService.php` — dynamic technical-attribute resolution and filtering

---

## 4. Business rules (the heart of the system)

### 4.1 Configurable values (stored in `settings`, editable in admin; these are DEFAULTS)

| Key | Default | Meaning |
|---|---|---|
| `free_shipping_threshold` | 3000 | Free shipping when goods total ≥ this (EGP). `0` disables free shipping |
| `delivery_estimate_text` | "التوصيل خلال 2–5 أيام عمل" | Shown on product/cart/checkout |
| `return_policy_days` | 7 | Used in policy texts |
| `topbar_text` | "شحن مجاني فوق 3,000 ج.م — توصيل 2–5 أيام" | Moving top bar (one or more messages) |
| `whatsapp_number` | (empty) | International format, digits only, e.g. `201001234567` |
| `phones` | [] | List of phone numbers for header/footer |
| `bank_transfer_details` | (empty) | Account number / InstaPay text shown at checkout |
| `payment_cod_enabled` / `payment_bank_enabled` / `payment_card_enabled` | true / true / false | Enable/disable methods; card is additionally hidden until Paymob credentials and callback tests pass |
| `low_stock_default` | 5 | Default low-stock threshold |
| `why_us_blocks` | 4 blocks | "مميزات الشراء مننا": each block = icon key (from `config/icons.php`) + title + text. Defaults: أصلي 100% (`badge-check`) / فحص قبل الشحن (`gauge`) / ضمان (`shield-check`) / فاتورة رسمية (`receipt`) |
| `store_name`, `logo`, `address`, `map_embed`, `email`, `social_links`, `tax_number` | — | Store identity |
| `motion_level` | `full` | **v3.** `full` / `lite` / `off` — owner switch for electric motion (§11.3). The browser may only downgrade this setting, never upgrade it |
| `ga4_measurement_id` | (empty) | **v2 fix:** Google Analytics 4 ID (Phase 9A). Injected only when set |
| `search_console_verification` | (empty) | Google Search Console meta tag content (Phase 9A). Injected only when set |
| `store_tax_note` | (empty) | Optional owner-authored tax/pricing note; no automatic VAT calculation in v1 |
| `catalog_filter_max_options` | 12 | Maximum visible options per technical filter before search/more UI |

### 4.2 Effective unit price (PricingService)
1. `regular` = `variant.price ?? product.price`.
2. If the selected variant has its own `sale_price`, use its own `sale_ends_at`; otherwise inherit `product.sale_price` and `product.sale_ends_at`.
3. A sale price applies only when non-null, lower than `regular`, and its applicable end date is null or in the future. Ignore equal/higher/expired sale prices.
4. `base` = valid sale price or regular price.
5. Quantity tier: choose the tier with the greatest `min_qty <= normalized_quantity`; calculate the percentage discount on `base`, then round the unit price to 2 decimals using half-up rounding. A quantity tier may stack with a valid sale price; the active tier is determined by the line quantity.
6. `line_total = rounded_unit_price × quantity`, rounded to 2 decimals using the tested decimal helper. `subtotal = Σ line_total`.
7. Prices are for the product's configured sale unit (`unit_code`); every display includes the Arabic unit label where useful, e.g. `للمتر` / `للقطعة`.
8. For products with variants, show `تبدأ من` until a valid active variant is selected. Recalculate price, sale badge, unit price, SKU and availability after variant or quantity changes.
9. If a sale applies, show regular price struck through plus the correct discount percentage. Do not show a discount badge for an invalid sale price.

### 4.3 Coupons (one per order, never stacked)
- Types: `percent`, `fixed`, `free_shipping`.
- Valid only if active, within start/expiry dates, under usage limit (`null` = unlimited), and `subtotal >= min_order`.
- `percent`: percentage of subtotal, capped by `max_discount` if set. `fixed`: `min(value, subtotal)`. `free_shipping`: product discount is zero and shipping is zero.
- Code comparison is trimmed and case-insensitive. Normalize once and save the normalized coupon code snapshot on the order.
- Increment `used_count` at successful order creation, in the same DB transaction after locking the coupon row. Decrement exactly once only if the order is later cancelled (not returned after delivery); track this with `coupon_released_at`.
- Lock order is always stock rows ordered by id first, then coupon row. Lock all involved rows in deterministic order to reduce deadlocks. If the coupon is exhausted, abort the entire order transaction and save nothing.
- Coupon validation is repeated during final checkout/order creation. A coupon shown as valid in the cart is not reserved until the order transaction commits.

---

### 4.4 Shipping
- Fee = `shipping_rates.fee` for the chosen governorate (`is_active` only).
- **Free** if `(subtotal − coupon_discount) ≥ free_shipping_threshold` (and threshold > 0), or coupon is `free_shipping`.
- Cart before a governorate is chosen: show progress bar toward free shipping and "يُحسب الشحن عند إتمام الطلب". Product pages and pre-selection checkout use `delivery_estimate_text`; after a governorate is selected, display its `est_days_min`–`est_days_max` when available, otherwise fall back to the setting.
- Seed fees are **placeholders** the owner edits: القاهرة/الجيزة 50, الإسكندرية 60, Delta & Canal 65, Upper Egypt 75, Sinai/Red Sea/New Valley/Matrouh 90.

### 4.5 Totals and tax handling
`total = subtotal − coupon_discount + shipping_fee`. Persist subtotal, discount, shipping and total as immutable order snapshots.

V1 has **no separate automatic VAT/tax calculation line**. Product prices entered by the owner are the final selling prices shown to the customer; the owner is responsible for setting legally appropriate prices and any required tax treatment. `tax_number` is store identity data only. The printable document in §7.2 is a commercial order invoice/receipt, never an Egyptian Tax Authority e-invoice or a claim of tax compliance.

### 4.6 Stock and stock disposition
- Simple products track stock at product level; variant products track stock per active variant. `track_stock=false` means no automatic deduction, but movements may still be recorded for manual adjustments only if explicitly enabled by admin policy.
- Stock is deducted when the order is created, inside the order DB transaction, with `lockForUpdate`. Lock rows in ascending id order. If any line lacks stock, abort the whole transaction, identify the item in Arabic and save nothing.
- Stock quantities use the same base sale unit as the product and decimal precision up to 3 places. Do not convert units implicitly. A product sold by meter is stocked in meters; a product sold by roll is stocked in rolls. If both package and loose-unit selling are needed, create explicit separate SKUs/variants and do not assume a conversion ratio.
- Stock is restored immediately when an order is cancelled before it has reached `shipped` (`new`, `confirmed`, `processing`). Restoration is idempotent and uses `stock_restored_at` plus `stock_restoration_status`.
- If an order is cancelled after shipping, or returned after delivery, do **not** add stock automatically. Set `stock_restoration_status=pending_inspection`. An admin must record physical receipt and inspect the goods. `OrderService::resolveStockDisposition()` then sets either `restored` (increase stock once and write movement) or `written_off` (no stock increase). Require an admin note and acting user. Never put damaged/unknown goods back into available stock automatically.
- `stock_restoration_status` values: `not_required`, `restored`, `pending_inspection`, `written_off`. `stock_restored_at` is set only after an actual stock restoration.
- Every stock change writes `stock_movements` with decimal `change` and a reason: `order_placed`, `order_cancelled`, `order_returned`, `manual_adjustment`, `import`, or `return_written_off` (the last is an audit-only zero-stock disposition if used).
- Display: zero → `غير متوفر` (visible, cannot add to cart; WhatsApp CTA becomes `اسأل عن التوفر`); positive stock less than or equal to effective low-stock threshold → `الكمية محدودة`; otherwise `متوفر`.
- Cart quantity is clamped to available stock using `quantity_step` and `max_order_quantity` when configured. For untracked items, cap at 999 sale units unless an explicit lower cap exists. Quantity must meet `min_order_quantity` and be a multiple of `quantity_step`.
- A product marked inactive, a variant marked inactive, or a soft-deleted product cannot be added or purchased, even if a stale cart contains it.

---

### 4.7 Order lifecycle
Statuses (`OrderStatus` enum): `new` جديد → `confirmed` مؤكد → `processing` قيد التجهيز → `shipped` تم الشحن → `delivered` تم التوصيل; terminal statuses `cancelled` ملغي and `returned` مرتجع.

Allowed transitions, enforced only by `OrderService::changeStatus()`:
- `new` → `confirmed` | `cancelled`
- `confirmed` → `processing` | `cancelled`
- `processing` → `shipped` | `cancelled`
- `shipped` → `delivered` | `returned` | `cancelled`
- `delivered` → `returned`
- `cancelled`, `returned` → none

Every successful transition writes `order_status_histories` (from, to, acting user, optional note). Customers may cancel only their own `new` order. Admin-only transition permissions are enforced by policies and service guards.

- Cancel before shipping: restore tracked stock once and release the coupon use once.
- Cancel after shipping: mark stock disposition `pending_inspection`; do not restore until an admin confirms the physical goods are received and resalable. Release coupon use once because the order is cancelled.
- Return after delivery: mark stock disposition `pending_inspection`; do not automatically restock or release coupon usage. Admin inspection decides `restored` or `written_off`.
- On `delivered`, COD payment becomes `paid` and `products.sales_count` increments once for delivered quantities.
- On `returned` (whole-order return only in v1), reverse the related `sales_count` once using `sales_reversed_at`, never below zero. This keeps best-seller counts net of returned sales. Partial item returns are out of scope for v1.
- Status cannot be changed by direct model writes, Filament actions or controllers outside `OrderService::changeStatus()`.

---

### 4.8 Payment
- `cod` (Cash on Delivery): default; `payment_status=pending` until delivered, then paid.
- `bank_transfer`: checkout displays `bank_transfer_details`; the order starts `pending`. No auto-expiry in v1; stock remains reserved until an admin cancels the order. Dashboard must show aging pending transfers so they are not forgotten. Customer uploads a receipt in Phase 10; a receipt is not proof of settlement by itself.
- `card` (Paymob): disabled by default. V1 implementation target is the classic Paymob Accept iFrame/hosted checkout with HMAC-verified transaction callback, provided the merchant account supports it. Phase 10 must verify the account's enabled integration and current official Paymob documentation before coding/enabling. If that account cannot use this integration, stop the Paymob subtask and report the precise account/API blocker; do not silently switch to another integration.
- Card orders are created first with pending payment and stock deducted as normal; payment attempt is then created. Unpaid card orders older than 60 minutes are cancelled by the scheduler through `OrderService`, set to failed/expired payment state when no successful callback exists, and restore stock once if not shipped. A late valid successful callback must still be recorded truthfully (`payment_status=paid`) and flagged for admin reconciliation/refund; it must never silently revive a cancelled order or double-restore stock. If a second distinct attempt succeeds after another attempt already paid the order, record it and flag a duplicate-payment reconciliation/refund; do not change order totals or stock.
- Online payment state changes only after a verified server-to-server callback. Browser redirect/return URLs are display-only and must never mark a payment paid. If an already-paid order is later cancelled or returned, set `payment_reconciliation_required`; v1 does not send automatic refunds. Admin processes the full refund externally, then records the refund reference/note and updates the relevant transaction/order payment state. Partial refunds are out of scope. V1 refund processing is whole-order/full-amount only.
- Callback verification includes HMAC using the provider's documented canonical field order, constant-time comparison, expected merchant/order reference, amount in minor units compared to the locked payment attempt and order snapshot, currency, provider transaction identifier and provider success status. Store a unique provider transaction ID and process each callback idempotently. If the order is already paid by another transaction, preserve the second real payment record and flag manual refund/reconciliation.
- Payment secrets are stored only in environment variables. Logs must redact API keys, HMAC secrets, card data, tokens and sensitive payload fields.

---

### 4.9 Guest checkout
- Account is **never required**. Required: full name, Egyptian mobile, governorate, city, detailed address. Optional: email, second phone, notes.
- If the visitor is logged in, prefill from profile/default address.
- Guest can look up an order at `/track-order` using **order number + phone** (both must match).
- Success page `/order/success/{order_number}` is viewable only by the session that created it or the owning user.

### 4.10 Resolved owner decisions (V3 defaults — do not ask again)
These decisions are deliberately fixed for v1 implementation. The owner may change them later by editing this specification before the relevant phase.

1. **Restocking and returns:** orders cancelled before shipping restore stock immediately once. Orders cancelled after shipping and whole orders returned after delivery enter `pending_inspection`; one order-level admin disposition applies to all tracked items in that order: `restored` or `written_off` after physical receipt/inspection. No automatic restock of shipped/returned goods. Mixed per-item dispositions and partial-quantity returns are out of scope for v1.
2. **Bank transfer expiry:** no automatic expiry in v1. Bank-transfer orders keep stock reserved until an admin cancels them. Show pending-transfer age and status prominently in the admin dashboard.
3. **Mobile bottom navigation:** disabled in v1. Use the hamburger drawer and the mobile header/cart affordances defined in §10.
4. **Paymob:** target classic Paymob Accept iFrame/hosted integration with HMAC verification. Verify merchant account support and current provider docs in Phase 10. Keep card payment disabled until credentials and a successful test-mode callback are verified.
5. **Tax:** no separate automatic tax calculation in v1. Owner-entered product prices are the customer-facing final price. Do not label the order receipt a tax invoice; the owner must obtain any needed tax/legal advice outside this implementation.
6. **Product quantities:** support decimal sale quantities to 3 decimal places. The product defines one base sale/stock unit; no implicit meter-to-roll, pack-to-piece or other conversion. Use explicit SKUs when both forms are sold.

### 4.11 Sale units and decimal quantities
Each product has one `unit_code` from `config/units.php`, one Arabic display label, `min_order_quantity`, `quantity_step` and optional `max_order_quantity`. Initial allowed unit keys: `piece`, `meter`, `roll`, `pack`, `set`, `box`, `pair`, `kilogram`. Labels are Arabic and centrally defined (e.g. قطعة، متر، بكرة، عبوة، طقم، علبة، زوج، كجم).

- Default unit is `piece`, minimum quantity `1.000`, step `1.000`.
- Decimal quantities are supported to 3 places. `meter` and `kilogram` may use fractional steps (for example 0.1 or 0.01); admin can set a valid step per product. Other units default to whole-unit steps unless the owner explicitly configures an allowed fractional step.
- Prices are always per configured base unit. Stock is stored in that same unit. Do not convert a roll into meters or a box into pieces implicitly.
- Product, variant, cart line, checkout, order item, stock movement, Excel import/export, invoice, WhatsApp text and analytics must preserve the unit label and quantity precision.
- Every quantity is a decimal string in the application layer. Normalize to at most 3 fractional digits, enforce minimum/step/maximum, and use `QuantityService`; never compare decimal quantities using PHP float arithmetic.
- Quantity tiers use decimal `min_qty` and must align with the product's `quantity_step`.

### 4.12 Category-specific technical attributes
Electrical products need technical filters beyond brand and price. Define filterable attributes per category using `category_attributes`; save each product's structured values in `product_attribute_values`.

- A parent category's active attributes are inherited by its child categories. A child may add attributes but cannot redefine an inherited key. Keys are stable English slugs; labels and options are Arabic and editable through Filament.
- Attribute types: `text`, `number`, `select`, `boolean`. Numeric attributes may have a unit label (e.g. A, V, W, mm², IP). Select options are stored as JSON values/labels. Only active attributes with `is_filterable=true` appear in listing filters.
- The product page renders technical attributes in a clear spec table. `products.specs` remains for extra non-filtered descriptive specifications only; do not duplicate the same value in both sources.
- Filter UI is data-driven: show only relevant attributes with values in the current category/brand/product result set. Filter state is URL query-string state, shareable and back-button friendly. Validate allowed attribute keys/options server-side; never trust arbitrary query keys.
- Seed realistic examples: breakers (current_rating_a, poles, trip_curve, breaking_capacity_ka); cables/wires (cross_section_mm2, cores, conductor_material, insulation_type); lighting (wattage_w, lumen, color_temperature_k, base_type, ip_rating); power tools (power_w, voltage_v, chuck_mm); cameras (resolution_mp, connectivity, night_vision). Only seed values appropriate to demo products; do not invent real store stock or claims.

---

## 5. Database schema

Use `id` (bigint) PKs, `timestamps`, and `softDeletes` only where noted. Foreign keys with sensible `onDelete`. Index everything searched/filtered. Create migrations in FK dependency order: users/categories/brands/products and catalog tables first, then orders, then order_items/histories/stock movements/payment_transactions and remaining dependent tables. `payment_transactions.order_id` must reference an already-created orders table.

**users**: name, phone (unique, nullable), email (unique, nullable), password, is_admin (bool, default false), phone_verified_at (unused in v1). *At least one of phone/email required for customers.*

**categories**: parent_id (nullable, self FK; **max 2 levels** enforced), name, slug (unique), image (media), **icon (string, nullable — key from `config/icons.php`; subcategories inherit the parent's icon when null)**, sort_order, is_active, meta_title, meta_description.

**brands**: name, slug (unique), logo (media), is_authorized_dealer (bool), is_active, sort_order.

**products** (softDeletes): category_id, brand_id (nullable), name, slug (unique, editable, auto-generated from model_code/name), model_code (indexed), `catalog_identity` (unique 64-char deterministic hash of normalized model code + brand key, so Excel upsert stays unique even when brand is null), short_description, description (sanitized rich text), `specs` (json for descriptive, non-filtered `{label,value}` pairs), `unit_code` (allowed key from `config/units.php`, default `piece`), `min_order_quantity` decimal(12,3) default 1, `quantity_step` decimal(12,3) default 1, `max_order_quantity` decimal(12,3) nullable, `cost_price` decimal(12,2) internal only, `price` decimal(12,2), `sale_price` nullable, `sale_ends_at` nullable, `has_variants`, `stock_quantity` decimal(12,3) for simple products, `track_stock`, `low_stock_threshold` decimal(12,3) nullable, `is_active`, `is_featured`, `sales_count` decimal(12,3), `search_text` normalized text, `meta_title`, `meta_description`. Add indexes on category, brand, model_code, catalog_identity, active, price, created_at and composite listing filters. Images: `gallery` media collection (first = primary), explicit Arabic alt text or safe product-name fallback.

**product_variants**: product_id, name (auto from options), sku (unique), options (json, e.g. `{"اللون":"أبيض","الشكل":"مربع"}`), price decimal(12,2) nullable override, sale_price decimal(12,2) nullable, `sale_ends_at` nullable (applies only to variant sale price), cost_price decimal(12,2) nullable/internal, stock_quantity decimal(12,3), is_active, sort_order. Variant inherits product sale unit and quantity rules. Optional image (media).

**product_price_tiers**: product_id, `min_qty` decimal(12,3) aligned to product quantity_step (minimum is greater than the product's base minimum), `discount_percent` decimal(5,2) (0–90). Unique `(product_id, min_qty)`.

**category_attributes**: category_id, key (English stable key), name_ar, data_type (`text/number/select/boolean`), unit_label nullable, options JSON nullable, is_filterable, is_required, is_active, sort_order. Unique `(category_id, key)`; index category_id/is_active/is_filterable. Parent attributes are inherited; application validation prevents duplicate inherited keys in descendants.

**product_attribute_values**: product_id, category_attribute_id, value_text nullable, value_number decimal(12,3) nullable, value_boolean nullable, value_label_snapshot nullable only if required for stable display. Unique `(product_id, category_attribute_id)`; indexes `(category_attribute_id, value_text)` and `(category_attribute_id, value_number)`. Enforce exactly one value column according to attribute type in application validation.

**orders**: order_number unique nullable until assigned after insert (format `ORD-{YYYY}-{id padded to 5}`; never exposed while null), user_id nullable, customer_name, phone, phone_alt nullable, email nullable, governorate/city/address snapshots, notes nullable, payment_method enum, payment_status enum (`pending/paid/failed/refunded`), payment_proof nullable media/path, payment_provider nullable, provider_reference nullable, `payment_expires_at` nullable, `payment_verified_at` nullable, `payment_reconciliation_required` bool default false, status enum, subtotal/discount_total/shipping_fee/total decimal(12,2) snapshots, coupon_code nullable, `coupon_released_at` nullable, shipping_company/tracking_number nullable, admin_notes nullable/internal, `stock_restoration_status` enum (`not_required/restored/pending_inspection/written_off`), `stock_restored_at` nullable, `stock_disposition_note` nullable, `stock_disposition_by` nullable FK, `sales_reversed_at` nullable, placed_at. Index status, payment_status, created_at, phone, order_number, payment_expires_at.

**payment_transactions**: order_id, provider, `provider_transaction_id` nullable until the provider creates/returns one, unique `(provider, provider_transaction_id)` (multiple NULL attempts are allowed by MySQL/MariaDB), `merchant_reference` unique per payment attempt, `amount` decimal(12,2), currency (`EGP`), status enum (`pending/paid/failed/expired/refunded`), is_success, payload_redacted JSON nullable, processed_at nullable, failure_reason nullable, `refund_reference` nullable, `refunded_at` nullable, `refund_note` nullable, `reconciliation_status` enum (`not_required/pending/resolved`). No secrets or card data may be stored. Every callback is idempotent using provider transaction identity and a locked order/payment-attempt row. Refunds are not automatically sent to the provider in v1; an admin records the external refund/reference after handling it in the merchant dashboard.


**order_items**: order_id, product_id nullable (nullOnDelete), variant_id nullable, name, variant_name nullable, sku, `unit_code` and `unit_label_ar` snapshots, unit_price decimal(12,2), cost_price decimal(12,2) snapshot/internal, quantity decimal(12,3), line_total decimal(12,2). Names, variants, SKU, unit and all prices are immutable order snapshots; never read live product data for past orders.

**order_status_histories**: order_id, from_status (nullable), to_status, changed_by (nullable user), note (nullable), created_at.

**stock_movements**: product_id, variant_id nullable, `change` decimal(12,3) +/- in the product's base sale unit, reason enum, order_id nullable, user_id nullable, note. Store unit_code snapshot to make historical movement meaning unambiguous.

**coupons**: code (unique, application-normalized and case-insensitive), type enum (`percent/fixed/free_shipping`), `value` decimal(12,2), `min_order` decimal(12,2) default 0, `max_discount` decimal(12,2) nullable, `usage_limit` unsigned int nullable, `used_count` unsigned int default 0, `starts_at` / `expires_at` nullable datetimes, `is_active`. Add index on active/date windows. Percent `value` is a percentage (0–100); fixed/max/min values are EGP amounts.

**shipping_rates**: `name_ar` (the 27 governorates, unique), `fee` decimal(12,2), `est_days_min` unsigned small int, `est_days_max` unsigned small int nullable, `is_active`, `sort_order` unsigned int. Index `(is_active, sort_order)`. Seeded fees are placeholders only.

**banners**: title, image_desktop, image_mobile (nullable), link_url (nullable), position (enum: `hero`, `promo`), sort_order, is_active, starts_at, ends_at. (`hero` feeds the slider; **`promo` feeds the promo tiles row under the hero — §6.5**.)

**reviews**: product_id, user_id, rating (1–5), comment (nullable), status (pending/approved/rejected), is_verified_purchase (bool). Unique (product_id, user_id).

**wishlists**: user_id, product_id (unique pair).

**addresses**: user_id, label, recipient_name, phone, governorate, city, address_line, `is_default`. Index `user_id`; service transaction guarantees at most one default address per user (set the previous default false before setting another).

**contact_messages**: name, phone, email (nullable), message, is_read.

**wholesale_requests**: name, phone, company (nullable), monthly_volume (nullable), message, status (new/contacted/closed).

**pages** (static, editable): slug (unique), title, content (rich text), is_active, meta_title, meta_description. Seed: `about`, `shipping-policy`, `return-policy`, `faq`, `terms`, `privacy` with sensible Arabic placeholder text marked "قابل للتعديل".

**settings**: key (unique), value (json). Helper `setting('key', default)` with cache.

### Seed data (required)
- 27 governorates in `shipping_rates` (names below).
- Category tree (§5.1) **with `icon` keys** (§11.7), brands: ABB, شنايدر, بوش, سانشي, أوسا, سنجر, MK (ABB/شنايدر/بوش marked as authorized dealer for demo).
- Default settings (§4.1), 6 static pages, 1 admin user (`admin@example.com` / random password printed in console — **owner must change**), coupon `OPEN10` (10%, active).
- ~30 realistic demo products via factories, including 5 products with variants (switches/sockets in white/black/gold) and 3 with quantity tiers; include cable/wire examples sold by meter (with fractional quantity steps) and at least one roll-based product sold only by roll; random `sales_count` for demo only; some on sale, some low-stock, one out of stock.
- Seed `category_attributes` and `product_attribute_values` with believable demo values for breakers, cables, lighting, power tools and cameras; do not claim product certifications or technical ratings that are not present in seed data.
- **Placeholders (v2 fix):** demo products, categories and banners have **no real images**. Use the generated-placeholder component (tinted background + the category's electric icon), never plain gray boxes. Demo banners (3 hero + 3 promo) are generated locally (static files or GD) — **no external downloads**.

### 5.1 Category tree (seed)
1. تأسيس كهرباء → لوح قواطع، لوازم أسلاك، لوحات كهربائية، أسلاك وكابلات ومواسير
2. تشطيب كهرباء → مفاتيح وبرايز
3. إنارة وديكور → نجف وسبوتات، لمبات ليد، بروفايل ليد وإضاءة حدائق
4. كهرباء صناعية → برايز صاروخ، شفاط صناعي، فيوزات
5. سمارت هوم → حساسات حركة، تحكم إضاءة، إنذار
6. كاميرات وانتركم → كاميرات IP، انتركم مرئي وصوتي
7. مستلزمات → ترانسات، مشتركات، دوايات، استابليزر
8. عدد ومعدات → دريلات وصواريخ ومناشير، أقراص تقطيع وتجليخ وسنفرة، أجهزة قياس وليزر
9. أدوات منزلية → دفايات، شفاطات، صاعق ناموس

### 5.2 The 27 governorates
القاهرة، الجيزة، الإسكندرية، القليوبية، الشرقية، الدقهلية، البحيرة، المنوفية، الغربية، كفر الشيخ، دمياط، بورسعيد، الإسماعيلية، السويس، شمال سيناء، جنوب سيناء، الفيوم، بني سويف، المنيا، أسيوط، سوهاج، قنا، الأقصر، أسوان، البحر الأحمر، الوادي الجديد، مطروح.

---

## 6. Storefront specification

> Every page below has motion rows in **§11.8** (filter the table by the Page column). Motion is always secondary to function and follows §11.2 rules.

### 6.1 Routes
| URL | Page |
|---|---|
| `/` | Home |
| `/categories/{slug}` | Category (parent shows products of all its children) |
| `/brands/{slug}` | Brand page (same listing component) |
| `/products/{slug}` | Product |
| `/search?q=` | Search results (same listing component) |
| `/cart`, `/checkout`, `/order/success/{order_number}` | Purchase flow |
| `/track-order` | Guest order lookup |
| `/login`, `/register`, `/account/orders`, `/account/orders/{order_number}`, `/account/addresses`, `/account/wishlist`, `/account/profile` | Customer area |
| `/pages/{slug}` | Static pages |
| `/contact`, `/wholesale` | Forms |
| `/sitemap.xml`, `/robots.txt` | SEO |
| `/admin` | Filament |
| `/_ui` | **UI kit page (local environment only):** shows every component and electric animation with a motion-level switch (§11.11). Returns 404 in any other environment, is never in the sitemap |

Internal storefront links use `wire:navigate` (with hover prefetch). `/admin` and external links do not.

### 6.2 Product page (most important page — build first)
Top to bottom (mobile-first):
1. Breadcrumbs.
2. Gallery: large image + thumbnails (swipe on mobile), zoom/lightbox (Swiper Thumbs + Zoom).
3. Name, model code, brand (logo/link; "وكيل معتمد" badge if authorized).
4. Price block: current price per sale unit, struck-through old price + correct percentage badge when on sale; availability badge; "تبدأ من" for variant products until a variant is chosen.
5. Variant picker (chips) if `has_variants`: updates price, sale validity, stock, SKU and image live.
6. Technical attributes for this category, rendered from `category_attributes` and `product_attribute_values`; `products.specs` remains for additional non-filtered descriptive specs.
7. Quantity-tier table ("اشترِ أكثر ووفّر") if tiers exist; prices update as quantity crosses a tier.
8. Unit-aware quantity stepper respecting minimum, step, maximum and available stock; show unit label and unit price.
9. **"أضف للسلة"** + **"اطلب عبر واتساب"** (prefilled with product, model, variant, quantity + unit, unit price and URL-encoded page URL).
10. **Red strip**: "اشترِ بـ {threshold} ج.م واحصل على شحن مجاني" (hidden when threshold = 0).
11. Delivery estimate line from settings/shipping estimate.
12. Tabs/sections: full description, general descriptive specs and "مميزات الشراء مننا" block (from `why_us_blocks`).
13. Related products (same category, prefer same brand; max 8).
14. Reviews: average stars and approved reviews only (submission form arrives in the accounts phase).
15. **Mobile sticky bottom bar** with price + add to cart (slides up once the main buy button leaves the viewport).

Desktop layout: two columns — sticky gallery on the start side, buy box on the end side inside a white card.

### 6.3 Category / brand / search listing
- Product grid cards: image (lazy), name (2 lines max), brand, price (+ old price/badge), stock badge, add-to-cart button.
- Filters (sidebar on desktop, bottom-sheet drawer on mobile): **brand (multi-select), price range, in-stock only (breaker-style switch)**, category children chips, plus active filterable technical attributes defined for the selected category tree. Numeric attributes use range/chips where useful; select/boolean attributes use discrete options. Hide filters with no result values.
- Sort: الأحدث / الأرخص / الأغلى / الأكثر مبيعاً.
- Mobile: a bar under the header with two buttons "فلترة" and "ترتيب" (sticky).
- Pagination: **24 per page**. All filter/sort/page state lives in the URL query string (shareable, back-button friendly).
- Empty state (electric scene, §6.10) with suggestion + WhatsApp link.
- Filtered/searched URLs are `noindex` and canonicalize to the base listing URL.

### 6.4 Search
- Searches `search_text` (name + model code + brand + variant SKUs, normalized). Multi-word: every token must match.
- Header live-search dropdown: debounce 300ms, top 6 results with thumbnail + price, "عرض كل النتائج".
- Query is normalized with `ArabicNormalizer` (§8.4) before matching. Exact model-code/SKU matches rank first, then full-token matches, then partial matches. Do not add an external search engine in v1; measure query time and report if the catalog outgrows this approach.
- The search field placeholder teaches the model-code habit: "ابحث بالاسم أو كود الموديل — مثال: S201C16".

### 6.5 Home page (order)
Topbar (moving) → Header (logo, search, cart count, account, phone) → category dropdown/mega menu → hero slider (banners `hero`, 3–5) → **promo tiles (banners `promo`, 2–3; desktop 3 columns, mobile horizontal scroll-snap)** → category grid with images/icons (**3×3 on mobile**) → brands strip → "الأكثر مبيعاً" → "وصل حديثاً" → "لماذا تشتري منا؟" (`why_us_blocks`) → wholesale banner → footer (links, policies, contact, social, payment icons). Empty sections are hidden automatically. **Never invent statistics, testimonials or counters** — everything comes from the database.

### 6.6 Cart
Lines with image, name, variant, unit price, quantity stepper, remove; coupon field with clear Arabic errors; summary (subtotal, discount, shipping note, total); **cable-style free-shipping progress bar** "فاضل X ج.م على الشحن المجاني" (§11.6 E11); CTA to checkout. Prices are recomputed from the DB on every render; changed prices/out-of-stock items show a warning.

### 6.7 Checkout (single page)
Customer data → address (governorate select of 27 → shipping fee updates live) → payment method radios → order summary → **"تأكيد الطلب"**. Double-submit protection, server re-validates everything (stock, coupon, prices) inside the transaction. On success: clear cart, redirect to success page (order number with copy button, summary, "هنتواصل معاك للتأكيد", WhatsApp link, the "power-on" success scene §11.8 M63).

### 6.8 Customer account
Register/login with **phone or email** (single "identifier" field), orders list + details with **status timeline drawn as a circuit** (§11.8 M64), saved addresses, wishlist, profile & password change, reviews (only for products in a delivered order → `is_verified_purchase`; others allowed but unverified; all moderated).

### 6.9 Static pages & forms
Pages from `pages` table. Contact form → `contact_messages`; Wholesale form → `wholesale_requests`. Both: validation, honeypot, throttle, success message.

### 6.10 Error and empty states (electric scenes)
All are real content first (text + links + search/CTA); the animation is a bonus and never required to understand the page.
- **404:** unplugged plug + dim bulb. Tapping/pressing the plug (a real `<button>`) plays the plug-in sequence and lights the bulb. Text: "الصفحة دي مش موجودة" / "يبدو إن الفيشة اتشالت — ارجع للرئيسية أو دوّر على اللي محتاجه", with search box and category links.
- **500:** tripped breaker. The lever is a `<button>`; pressing it flips ON and reloads the page. Text: "حصل عطل مؤقت" / "الفيوز فصل — جرّب تاني بعد لحظات". No technical details shown.
- **Empty cart:** empty socket + CTA "تصفح المنتجات".
- **Empty search/listing:** switched-off bulb + "مفيش نتايج" + "جرّب كود الموديل أو اسم الماركة" + WhatsApp link.
- **Empty wishlist:** same plug/socket scene as empty cart with its own text.
- Skeletons (not spinners) while listings/search/carousels load; bolt-loader only inside buttons and small areas.
- Form errors: inline text + icon (never color alone).

---

## 7. Admin panel (Filament 5, path `/admin`, only `is_admin` users)

- **Dashboard**: today's orders & revenue, count of `new` orders, low-stock list, top 5 selling products (30 days, net of returns), and aging bank-transfer orders awaiting manual confirmation.
- **Orders** (most-used screen): table with status filter/badges, search by number/name/phone, date filter; navigation badge with `new` count. Row/page actions: **change status** (only allowed transitions, optional note), **WhatsApp button** (opens `wa.me` with normalized phone + pre-filled Arabic confirmation message), **print invoice**, edit shipping company/tracking number/admin notes, manually mark a bank-transfer payment paid only after receipt verification (with audit); COD is paid only on delivered and Paymob is paid only by verified callback. Resolve pending stock disposition only after physical receipt/inspection (restock or write-off, mandatory note). View page shows item/unit snapshots, totals, customer, payment attempts/refunds, stock disposition and status history.
- **Products**: full form (all fields, sale unit, min/step/max quantity, descriptive specs, structured category-attribute values, gallery upload/reorder/alt text, variants relation manager, price tiers repeater, SEO fields); duplicate action; bulk activate/deactivate; **Excel import/export** (§7.1); quick stock edit inline using product unit; filters (category, brand, low stock, inactive).
- **Categories** (tree-style, max 2 levels; icon select from `config/icons.php` with preview; manage child-inherited technical attribute definitions and filter visibility), **Brands**.
- **Coupons**, **Banners** (desktop + mobile image preview; position hero/promo), **Reviews** (approve/reject, bulk), **Contact messages**, **Wholesale requests**, **Pages** (rich-text editor), **Shipping rates** (editable table of 27 rows + active toggle), **Customers** (read-only list + their orders).
- **Settings page**: all keys in §4.1, grouped in tabs (Store, Contact, Shipping, Payment, Homepage blocks (why-us icon = select from whitelist), Social, **Appearance (`motion_level`: full / lite / off, with a one-line Arabic explanation of each)**, Analytics).
- Arabic labels everywhere. Never show `cost_price` outside admin.
- Sidebar order: Orders, Catalog, Marketing, Content, Settings.

### 7.1 Excel import/export
- "Download template" button generates the exact header row below. Export uses the same columns.
- Required columns: `name`, `model_code`, `brand`, `category`, `subcategory`, `cost_price`, `price`, `sale_price`, `stock_quantity`, `unit_code`, `min_order_quantity`, `quantity_step`, `max_order_quantity`, `specs`, `attributes`, `short_description`. Export uses the same order and documents allowed `unit_code` values.
- `specs` format: `ملاحظات فنية: ... | النوع: ...`; these are descriptive display-only specs. `attributes` format: `current_rating_a=16 | poles=2 | trip_curve=C`; keys must already exist and be active for the category tree. Invalid keys/options reject the row with a clear reason.
- **Upsert by deterministic `catalog_identity` generated from normalized `model_code + brand_id`** (not by a nullable composite alone). Brand is created inactive if missing; missing category/subcategory or invalid unit/quantity step rejects the row. Never overwrite an existing brand identity silently.
- Stock changes from import write `stock_movements` (`import`).
- Result screen: X created / Y updated / Z failed, with downloadable list of failed rows and reasons.
- **v2 fix (shared-hosting timeouts):** one HTTP request must never process a whole large file. The UI uploads the file, then a Livewire method processes **200 rows per request** in a loop with a progress bar until done; progress/results live in cache or session and the failed-rows file in `storage`. Hard cap **5,000 rows per file** with a clear Arabic message.
- **Bulk image attach**: upload many images at once; filename pattern `<model_code>-<n>.<ext>` attaches to the matching product in order `n`; report unmatched files. Process **at most 20 files per request** with the same progress approach.

### 7.2 Invoice
Printable HTML page (`@media print`, A4, RTL) with store identity, order number/date, customer, item/variant, SKU, sale quantity + Arabic unit, unit price, line total, coupon/discount, shipping, grand total, payment method/status and "شكراً لثقتكم". All values come from immutable order snapshots, not current product data. This is an **order invoice/receipt, not an Egyptian Tax Authority e-invoice** — do not claim otherwise. No animation on the invoice.

---

## 8. Egypt-specific rules

### 8.1 Phone numbers
- Accept Arabic-Indic digits (٠-٩) → convert to Western. Strip spaces, dashes, `+2`, `002` prefixes.
- Customer phone must match `^01[0125][0-9]{8}$` (Egyptian mobile). Landlines allowed only in `phone_alt`.
- `Phone::toWhatsApp($n)` → `20` + number without the leading zero (e.g. `01001234567` → `201001234567`).
- Store phones in normalized form; display in local format.

### 8.2 Money, quantities & digits
Currency label `ج.م`. Use **Western digits (0–9)** everywhere. Format `1,250 ج.م` (no decimals if whole, else 2). Format quantities to the precision configured by `quantity_step` (trim unnecessary trailing zeros) and always show the sale unit on product, cart, checkout, order and invoice lines. Keep amount/quantity values as decimal strings at boundaries; never use JS or PHP floats as the source of truth.

### 8.3 WhatsApp
- Floating button on every storefront page (bottom-left in RTL, above mobile sticky bars) using `whatsapp_number`.
- Always `https://wa.me/{number}?text={urlencoded}`. If number is empty, hide the buttons.

### 8.4 Arabic search normalization (`ArabicNormalizer`)
Lowercase Latin; remove tashkeel and tatweel; `أ إ آ → ا`; `ة → ه`; `ى → ي`; convert Arabic-Indic digits to Western; collapse whitespace. Build `products.search_text` from normalized name + model code + brand name + variant SKUs; rebuild on product/variant save.

---

## 9. SEO, performance, security

### SEO
- `<html lang="ar" dir="rtl">`; unique `<title>` + meta description per page (fallback templates); canonical URLs; Open Graph/Twitter tags.
- JSON-LD: `Product` (name, image, sku, brand, offers with `priceCurrency=EGP` and availability, aggregateRating when reviews exist), `BreadcrumbList`, `Organization`/`LocalBusiness` on home.
- `/sitemap.xml` (categories, brands, products, pages), `/robots.txt` (disallow `/admin`, `/cart`, `/checkout`, `/account`; search & filter query URLs get `noindex` meta, so they must NOT be blocked in robots.txt), `/_ui` never indexed.
- Clean slugs, proper Arabic 404 page, 301 on slug change is optional (note as future).
- Because storefront navigation uses `wire:navigate`, analytics `page_view` is sent on `livewire:navigated` (no full page load happens).

### Performance (mobile / weak network)
- Serve WebP conversions with `srcset`; `loading="lazy"` below the fold; explicit width/height (no layout shift).
- **LCP rule:** the first hero slide image (and on product pages the main gallery image) is `loading="eager"` with `fetchpriority="high"`, is never animated in, and never starts at `opacity: 0`.
- Self-host the Arabic font (Cairo, 400/600/800, subset, `font-display: swap`; preload the main weight).
- Minimal JS; no jQuery; Livewire only where interactive. Cache heavy queries.
- **Motion budget (§11.2):** extra JS for motion ≤ 20 KB gzip (excluding Swiper and auto-animate); decorative SVG ≤ 3 KB each and ≤ 15 KB per page; ≤ 3 infinite animations running in the viewport at once; no animation may cause layout shift.
- Target Lighthouse mobile Performance ≥ 80 on home/product/category **with motion at `full`**; CLS < 0.1. If an animation costs the score, remove or simplify the animation — never lower the target.

### Security
- CSRF everywhere; mass-assignment protection; Form Request validation; escape all output (only admin-authored rich text rendered unescaped, sanitized).
- **Rate limits**: login, register, checkout, contact, wholesale, reviews, track-order, coupon apply.
- Honeypot field on public forms (hand-written; no extra package).
- Upload rules: images only, ≤ 4 MB, MIME and content validated; random server-side filenames; private payment proofs are not publicly addressable and are served only after order ownership/admin authorization.
- `cost_price`, `admin_notes`, payment secrets, provider payload secrets and other admin-only fields are never selected/serialized on the storefront. Use explicit storefront DTO/resources/select lists, not only `$hidden` as the sole protection.
- Enforce ownership on every account order/address/review route. Guest order lookup requires exact order number + phone match, is rate-limited, returns a generic mismatch response and never exposes whether either field exists.
- Payment callback endpoint: verify provider HMAC, amount, currency, order reference and unique provider transaction ID; process inside a DB transaction with `lockForUpdate`; record idempotency in `payment_transactions`. Do not disable CSRF globally to make callbacks work; exempt only the exact server-to-server callback route with signature validation as the authentication mechanism.
- Admin gate: `is_admin` + `canAccessPanel`. Production: `APP_DEBUG=false`, secrets only in `.env`.
- `/_ui` is reachable only when `app()->environment('local')`.

---

## 10. Design system (updated in v3)

### 10.1 Direction: "Industrial Spark"
Trustworthy, technical, fast. Dark ink structure (topbar, desktop nav strip, hero overlays, wholesale banner, footer), clean white product surfaces on a light-gray canvas, and **safety yellow as the single action color**. The electricity motif is thin and consistent: wire lines as dividers and rails, small sparks on confirmed actions, bulbs for "on/success", plugs for "connection", breaker switches for toggles, and tool icons that move (§11). No stock photos of people, no gradients everywhere, no neon, no glow on large areas.

Product photos carry the look: square 1200×1200, white or light-gray background, product centered with ~8% margin, ≤ 4 MB. Consistent photos matter more than any CSS.

### 10.2 Tokens (Tailwind 4 `@theme`, easy to swap by the owner)
| Token | Value | Use |
|---|---|---|
| `--color-primary` | `#FFC107` | Primary buttons, active states, highlights, progress fill, current/spark |
| `--color-primary-600` | `#E0A800` | Hover / pressed |
| `--color-ink` | `#111827` | Topbar, nav strip, footer, headings, text on yellow |
| `--color-body` | `#1F2937` | Body text |
| `--color-muted` | `#6B7280` | Secondary text |
| `--color-surface` | `#FFFFFF` | Cards |
| `--color-canvas` | `#F5F6F8` | Page background |
| `--color-success` | `#16A34A` | In stock / success |
| `--color-danger` | `#DC2626` | Sale badge, free-shipping strip, errors |
| `--color-link` | `#1D4ED8` | Text links only |
| `--color-wire` | `#CBD5E1` | Wires on light backgrounds |
| `--color-wire-dark` | `#374151` | Wires on dark backgrounds |
| `--color-spark` | `#FFE066` | Brightest part of a pulse/spark |

**Color rules:** text on yellow is always `ink`. **Never** yellow text on white and never white text on yellow. Red only for sale/free-shipping strip/errors; green only for availability/success; blue only for links. Contrast ≥ 4.5:1 for body text.

### 10.3 Typography, layout, shape
- **Font:** Cairo 400/600/800, self-hosted subset (Arabic + Latin + digits). Scale mobile/desktop: H1 28/36, H2 22/28, H3 18/20, body 16, small 14, caption 12 (never smaller). Line-height 1.7 body, 1.35 headings. Western digits everywhere; prices use `tabular-nums`; model codes/SKUs are `dir="ltr"`.
- **Container** max 1280 px; side padding 16 px mobile / 24 px desktop; section spacing 40 px mobile / 64 px desktop.
- **Product grid:** 2 columns (< 768), 3 (≥ 768), 4 (≥ 1024), 5 (≥ 1280 only if a card stays ≥ 200 px wide).
- **Shape:** radius 12 px cards, 8 px inputs/buttons, full for chips. Shadows: two levels only (resting `sm`, hover `md`). Tap targets ≥ 44 px.
- RTL: use logical utilities (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`, `rtl:` variants) — not left/right. Mirror directional icons only (chevrons/arrows via `rtl:-scale-x-100`); never mirror phone, search, cart, zap, plug.

### 10.4 Icons
- **Inline SVG Blade components** in `resources/views/components/icons/`, 24 px grid, 1.75 stroke, `currentColor`. Lucide icons are copied in as files (ISC license, no package). Verify each Lucide name exists; if not, draw a simple matching SVG in the same style.
- **Custom electric icons** (drawn in the same style when Lucide lacks them): `breaker` (panel with 3 breaker levers), `power-strip` (strip with 4 sockets + LEDs), `plug-socket` (plug + socket pair).
- `config/icons.php` is the **whitelist**: key → Arabic label. Initial keys: `breaker`, `plug-socket`, `plug-zap`, `lightbulb`, `lamp-ceiling`, `cog`, `wifi`, `cctv`, `power-strip`, `drill`, `hammer`, `wrench`, `fan`, `heater`, `cable`, `zap`, `gauge`, `router`, `battery-charging`, `house`, `shield-check`, `badge-check`, `receipt`, `truck`.
- Animated icons carry `data-icon="{key}"` and named sub-parts (e.g. `.drill-bit`, `.fan-blades`) so CSS can animate parts; see §11.7.
- Decorative icons are `aria-hidden="true"`; meaning is always in adjacent text.

### 10.5 Component anatomy
- **product-card:** square image (`aspect-square`, `object-contain`, white, 12 px padding) · badges (top-start: sale badge; top-end: wishlist heart) · brand (muted) · name (2-line clamp, weight 600) · model code (small, ltr) · price row (current price weight 800 + old price struck, muted) · stock badge · add-to-cart (full-width yellow, ≥ 44 px). Whole card is a link except the buttons. Always declare image width/height.
- **header:** sticky, **constant height** (64 px mobile / 72 px desktop). Topbar is not sticky (scrolls away). Start side: logo. Desktop: central search field, then account, cart (socket icon + count badge), phone. Mobile: hamburger · logo · search icon · cart; the search icon opens a full-width panel under the header with live results; the home page also shows a large search field under the header on mobile.
- **desktop nav:** dark strip under the header with the 9 main categories (icon + name) + "كل الأقسام" opening the mega menu.
- **buttons:** primary (yellow, ink text, sheen on hover), secondary (ink, white text), outline, WhatsApp (green). Min height 44 px. Visible focus ring (2 px primary, 2 px offset).
- **Required Blade components:** `product-card`, `price`, `stock-badge`, `sale-badge`, `breadcrumbs`, `section-title`, `trust-block`, `free-shipping-bar`, `whatsapp-button`, `rating-stars`, `empty-state`, `toast`, `pagination` (RTL), `btn`, `placeholder-image`, plus the electric set in §11.6.

### 10.6 Home blueprint (extends §6.5)
1. Topbar (ink, 36 px, marquee with bolt separators)
2. Header (white, sticky)
3. Desktop nav strip (ink)
4. Hero slider (desktop 16:6; mobile uses `image_mobile`, ~4:3) with a pause/play button
5. Promo tiles
6. Category grid — animated tool icons (§11.7); 3×3 on mobile, 5–9 columns on desktop
7. Brands strip (grayscale, color on hover on desktop)
8. "الأكثر مبيعاً" carousel (~2.2 cards visible on mobile so the swipe is obvious)
9. "وصل حديثاً" grid
10. "لماذا تشتري منا؟" (2×2 mobile, 4 columns desktop) with animated icons
11. Wholesale banner (ink + circuit panel with pulses, yellow CTA + WhatsApp)
12. Footer (ink, with a top wire and back-to-top bolt button)

### 10.7 Arabic UI copy (use these exact strings; keep them in lang files)
**Original:** أضف للسلة · اطلب عبر واتساب · اسأل عن التوفر · غير متوفر · متوفر · الكمية محدودة · تأكيد الطلب · إتمام الشراء · السلة فارغة · كود الخصم · تطبيق · الإجمالي · الشحن · مجاني · يُحسب عند إتمام الطلب · المحافظة · المدينة · العنوان بالتفصيل · الدفع عند الاستلام · تحويل بنكي · بطاقة ائتمان · تتبع طلبك · وكيل معتمد · الأكثر مبيعاً · وصل حديثاً · عرض الكل · اشترِ أكثر ووفّر · منتجات مشابهة · تقييمات العملاء

**Additional UI copy (v3):** تمت الإضافة للسلة · عرض السلة · فاضل {amount} على الشحن المجاني · الشحن المجاني اتفعّل · جاري تأكيد الطلب… · الطلب اتسجّل · هنتواصل معاك للتأكيد · نسخ رقم الطلب · تم النسخ · ابحث بالاسم أو كود الموديل — مثال: S201C16 · فلترة · ترتيب · متوفر فقط · مفيش نتايج · جرّب كود الموديل أو اسم الماركة · الصفحة دي مش موجودة · يبدو إن الفيشة اتشالت — ارجع للرئيسية أو دوّر على اللي محتاجه · حصل عطل مؤقت · الفيوز فصل — جرّب تاني بعد لحظات · أعد المحاولة · إيقاف العرض · تشغيل العرض · ارجع لأعلى · جاري التحميل · الحركة (إعدادات) · كاملة / خفيفة / متوقفة

### 10.8 Accessibility
Alt text on images, visible focus states, sufficient contrast, labels on all inputs, keyboard-operable switches and menus, `aria-live="polite"` for toast and cart count, `prefers-reduced-motion` honored (§11.3), decorative SVG `aria-hidden`.

---

## 11. Electric Motion System (original v2 system, reconciled and extended in v3)

### 11.1 Concept: "the power runs through the store"
Every animation maps to one electrical idea. If a proposed animation does not map to one of these, **do not build it**.

| Idea | Visual | Meaning to the user |
|---|---|---|
| **Wire** | thin line (static) | structure: dividers, rails, progress tracks, timelines |
| **Current pulse** | small bright dash traveling along a wire | something is active / loading / in progress |
| **Spark** | 6 tiny yellow particles bursting out | an action was confirmed (added to cart, coupon applied, switch turned on) |
| **Bulb** | bulb turning on (glow + rays) | success, availability, "power on" |
| **Plug & socket** | plug sliding into a socket | connection: cart, order placed, reconnecting after an error |
| **Breaker switch** | lever flipping with a snap | toggles and filters |
| **Tool motion** | fan spins, drill turns, gear ticks, Wi-Fi waves, REC dot blinks, power-strip LEDs chase, hammer swings, heat rises | identity of each product category (§11.7) |

### 11.2 Hard rules (apply to every animation)
1. **Purposeful:** gives feedback, explains a state change, guides attention, or expresses category identity. Nothing that delays the user.
2. **Timing:** UI feedback ≤ 400 ms; signature moments (fly-to-cart, plug-in) ≤ 600 ms; **one-time showpieces** (order success, 404/500 scenes) ≤ 1,200 ms total and play once.
3. **Cheap properties only:** animate `transform` and `opacity`. Allowed exceptions, only on **icon-size SVG (≤ 64 px, ≤ 3 KB)**: `stroke-dashoffset`, and `stroke`/`fill` color transitions. Small content removal may fade/translate briefly, then leave normal document flow; do not animate its height. Use Livewire 4 `wire:transition` or Alpine transitions for opacity/transform only. **Never animate** `box-shadow`, `filter`, `width`, `height`, `top/left/right/bottom`, `margin`, `background-color` on large surfaces, or `blur`. Glows are pre-drawn elements (radial gradient) whose `opacity`/`transform` animate.
4. **Safe by default:** with `prefers-reduced-motion` or `motion_level = off`, every element shows its meaningful **final static state** (bulb lit when it means "on", bar filled to its value). With JS disabled, all content is visible. **Nothing above the fold fades or slides in** (header, topbar, first hero slide, the first screen of the product page).
5. **Photosensitivity:** nothing flashes more than 3 times per second; the bulb flicker is at most 2 dips within 700 ms, icon-size only, once per element per page view; no full-width or large-area flashing.
6. **Budgets:** extra JS for motion ≤ 20 KB gzip (excluding Swiper/auto-animate); each decorative SVG ≤ 3 KB and ≤ 15 KB per page; **≤ 3 infinite animations running inside the viewport at once**; no long tasks > 50 ms caused by motion; Lighthouse mobile ≥ 80; CLS < 0.1.
7. **Looping animations** (anything infinite) must be marked with the class `ambient` and run only while visible (`x-live`, §11.5). Allowed loops only: topbar marquee; wire/circuit pulses; free-shipping sweep while incomplete; bolt-loader while loading; skeleton opacity pulse while loading; category-icon loops only while hovered/focused; and hero carousel auto-advance at 5 seconds or longer, only in `full` with a visible pause/play control. Brands rail is user-driven swipe/scroll, not autoplay. A global scheduler must pause ambient loops beyond 3 simultaneously visible loops. No low-stock badge or order-timeline node loops infinitely.
8. **Pausable:** hero autoplay has a visible pause/play button; marquee pauses on hover/focus/touch.
9. **Not allowed in v1:** sound, splash screens / preloaders, autoplay video, Lottie, WebGL/3D, parallax, scroll-jacking, cursor-following effects, confetti, any animation > 600 ms except the one-time showpieces.
10. Decorative SVG is `aria-hidden="true"`; all meaning is also in text.

### 11.3 Motion levels and lite mode
`settings.motion_level` ∈ `full` (default) | `lite` | `off`. The server renders `<html data-motion="{level}">`. A tiny inline script in `<head>` may **downgrade** the level (never upgrade):

```html
<script>
(function(d){var e=d.documentElement,m=e.dataset.motion||'full',w=window,n=navigator,c=n.connection||{};
e.classList.add('js');
if(w.matchMedia&&w.matchMedia('(prefers-reduced-motion: reduce)').matches){m='off'}
else if(m==='full'&&(c.saveData||/2g$/.test(c.effectiveType||'')||(n.deviceMemory&&n.deviceMemory<=2)||(n.hardwareConcurrency&&n.hardwareConcurrency<=2))){m='lite'}
e.dataset.motion=m})(document);
</script>
```
Verify that `data-motion` and the `js` class survive `wire:navigate`; if not, re-apply them on `livewire:navigated`.

| Level | Behavior |
|---|---|
| `full` | Everything in the catalog (§11.8). |
| `lite` | Functional feedback only: toast, drawer, sheet, button press, switch flip, cart-badge pop, simple bulb fade on success. **No** ambient loops (wire/circuit pulses, marquee, carousel autoplay, sheen, rings, tool-icon loops), **no** sparks, **no** fly-to-cart flight, **no** reveal translate (opacity-only 200 ms). Carousels remain manually swipeable. |
| `off` | No animation/autoplay at all; instant state changes; loaders show a static bolt icon; carousels are manual. Same as `prefers-reduced-motion`. |

### 11.4 Tokens and CSS plumbing (`resources/css/app.css`)
```css
@import "tailwindcss";

/* motion-level variants: use as  motion-full:animate-fade-up  /  motion-any:transition-transform */
@custom-variant motion-full (&:where(html[data-motion="full"] *));
@custom-variant motion-any (&:where(html[data-motion="full"] *, html[data-motion="lite"] *));

@theme {
  --color-primary: #FFC107;  --color-primary-600: #E0A800;
  --color-ink: #111827;      --color-body: #1F2937;   --color-muted: #6B7280;
  --color-surface: #FFFFFF;  --color-canvas: #F5F6F8;
  --color-success: #16A34A;  --color-danger: #DC2626; --color-link: #1D4ED8;
  --color-wire: #CBD5E1;     --color-wire-dark: #374151; --color-spark: #FFE066;

  --font-sans: "Cairo", ui-sans-serif, system-ui, sans-serif;

  --ease-out-soft: cubic-bezier(0.22, 1, 0.36, 1);
  --ease-snap: cubic-bezier(0.34, 1.56, 0.64, 1);   /* switch / plug overshoot */

  --animate-fade-up: fade-up 400ms var(--ease-out-soft) both;
  --animate-fade-in: fade-in 250ms ease-out both;
  --animate-pop: pop 300ms var(--ease-out-soft) both;
  --animate-ring: ring 550ms ease-out 1;           /* one finite ring; M07/M14 */

  @keyframes fade-up { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
  @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
  @keyframes pop { 0% { transform: scale(.6); opacity: 0; } 60% { transform: scale(1.15); } 100% { transform: scale(1); opacity: 1; } }
  @keyframes ring { 0% { transform: scale(1); opacity: .6; } 80%, 100% { transform: scale(1.8); opacity: 0; } }
}
```

**Reveal (visible without JS; hidden only when JS is on and motion is not `off`):**
```css
html.js:not([data-motion="off"]) [data-reveal]:not(.is-visible) { opacity: 0; transform: translateY(16px); }
html[data-motion="lite"] [data-reveal]:not(.is-visible) { transform: none; }
[data-reveal] { transition: opacity 400ms var(--ease-out-soft), transform 400ms var(--ease-out-soft); transition-delay: var(--reveal-delay, 0ms); }
html[data-motion="lite"] [data-reveal] { transition-duration: 200ms; transition-delay: 0ms; }
```

**Levels (safety nets):**
```css
html[data-motion="off"] *, html[data-motion="off"] *::before, html[data-motion="off"] *::after {
  animation: none !important; transition: none !important; scroll-behavior: auto !important;
}
html[data-motion="lite"] .ambient, html[data-motion="lite"] .ambient * { animation: none !important; }
@media (prefers-reduced-motion: reduce) {            /* works even if JS fails */
  *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
  [data-reveal] { opacity: 1 !important; transform: none !important; }
}
```

**Signature keyframes (plain CSS; SVG parts use `transform-box: fill-box; transform-origin: center;` via a `.t-origin` helper):**
```css
/* Wire pulse. The <path> has pathLength="600" and is DRAWN right → left (the site is RTL). */
.wire-pulse { stroke-dasharray: 48 552; stroke-dashoffset: 48; animation: wire-pulse 2.8s linear infinite; animation-play-state: paused; }
.is-live .wire-pulse { animation-play-state: running; }
@keyframes wire-pulse { from { stroke-dashoffset: 48; } to { stroke-dashoffset: -552; } }

/* Spark particles are created by JS inside #fx-root (fixed, pointer-events: none, z-index 60) */
.spark-dot { position: absolute; inline-size: 4px; block-size: 4px; border-radius: 9999px; background: var(--color-primary); animation: spark-burst 450ms var(--ease-out-soft) forwards; }
@keyframes spark-burst { from { transform: translate(0, 0) scale(1); opacity: 1; } to { transform: translate(var(--dx), var(--dy)) scale(.2); opacity: 0; } }

/* Bulb: state via data-on; one-time flicker via data-flicker */
.bulb .bulb-rays > * { opacity: 0; transform: scale(.6); transition: opacity 300ms var(--ease-out-soft), transform 300ms var(--ease-out-soft); transition-delay: calc(var(--i, 0) * 40ms); }
.bulb .bulb-glow { opacity: 0; transform: scale(.6); transition: opacity 500ms var(--ease-out-soft), transform 500ms var(--ease-out-soft); }
.bulb .bulb-filament { transition: stroke 300ms; }
.bulb .bulb-glass { transition: fill 300ms; }
.bulb[data-on="true"] .bulb-rays > * { opacity: 1; transform: none; }
.bulb[data-on="true"] .bulb-glow { opacity: 1; transform: none; }
.bulb[data-on="true"] .bulb-filament { stroke: var(--color-primary); }
.bulb[data-on="true"] .bulb-glass { fill: color-mix(in oklab, var(--color-primary) 14%, transparent); }
.bulb[data-flicker="true"] { animation: bulb-flicker 700ms linear 1; }
@keyframes bulb-flicker { 0%,100% { opacity: 1; } 15% { opacity: .35; } 30% { opacity: 1; } 55% { opacity: .5; } 70% { opacity: 1; } }

/* Plug & socket: state via data-state="apart|connected" */
.plug-socket .plug { transform: translateX(var(--plug-from, 24px)); transition: transform 500ms var(--ease-snap); }
.plug-socket[data-state="connected"] .plug { transform: none; }
.plug-socket .socket-led { opacity: .2; transition: opacity 150ms 450ms; }
.plug-socket[data-state="connected"] .socket-led { opacity: 1; }

/* Breaker switch knob: state via aria-checked */
.switch-knob { transition: transform 220ms var(--ease-snap); }
[role="switch"][aria-checked="true"] .switch-knob { transform: translateX(var(--knob-travel, -22px)); }  /* RTL: ON moves toward inline-end (left) */

/* Tool-icon parts (played only on hover-capable devices while hovered/focused, or ~once on reveal on touch; see §11.7) */
@keyframes spin-linear { to { transform: rotate(360deg); } }
@keyframes gear-tick { to { transform: rotate(90deg); } }
@keyframes hammer-swing { 0%,100% { transform: rotate(0); } 40% { transform: rotate(-28deg); } 60% { transform: rotate(6deg); } }
@keyframes wifi-wave { 0%,100% { opacity: .25; } 50% { opacity: 1; } }
@keyframes rec-blink { 0%,60% { opacity: 1; } 61%,100% { opacity: .15; } }
@keyframes led-chase { 0%,100% { opacity: .2; } 25% { opacity: 1; } }
@keyframes heat-rise { 0% { transform: translateY(2px); opacity: 0; } 50% { opacity: 1; } 100% { transform: translateY(-4px); opacity: 0; } }
@keyframes needle-sweep { 0%,100% { transform: rotate(-35deg); } 50% { transform: rotate(35deg); } }
@keyframes node-pulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(2.2); opacity: 0; } }
```
The AI may refine values while keeping names, timings and rules.

### 11.5 JS modules (`resources/js/motion/`, imported by `app.js`; no inline script blobs in Blade)
| File | Purpose |
|---|---|
| `level.js` | Reads/re-applies `data-motion` and `js` class after `livewire:navigated`; exports `motionLevel()` (`full` / `lite` / `off`) used by every module below |
| `reveal.js` | Alpine directive `x-reveal` (register in `alpine:init`): adds `data-reveal`, IntersectionObserver (threshold 0.15, once) adds `.is-visible`. If IntersectionObserver is unavailable or setup fails, immediately reveal everything. `x-reveal.stagger` on a container reveals direct children with `--reveal-delay: min(index, 7) * 60ms`. At level `off` everything is visible immediately |
| `live.js` | Alpine directive `x-live`: toggles `.is-live` while visible (rootMargin 100px) and the tab is visible; manages a page-wide queue that allows no more than 3 visible ambient loops to run at once. Paused loops resume only if still eligible and motion level is `full` |
| `fx.js` | `#fx-root` (inside `@persist`) + `fx.spark(target)` (6 particles, random angles, removed on `animationend`, one burst at a time, no-op unless level is `full`) |
| `fly-to-cart.js` | Listens to the browser event `cart-added` (dispatched by Livewire **after** the server confirmed the add). Source image = closest `[data-fly-source]` of the clicked button; target = `[data-cart-target]` in the header. `full`: Web Animations API arc (~600 ms) on a fixed-position clone with a short dashed "current trail" (SVG path drawn with `stroke-dashoffset`, faded out), then `fx.spark` on the cart icon. `lite`: no flight, badge pop only. `off`: nothing. Always followed by the toast |
| `sticky-bar.js` | Mobile buy bar: IntersectionObserver on the main buy button; slides the bar in/out |
| `header.js` | Adds a scroll-state class after 8 px of scroll; a pre-drawn border/overlay fades in using opacity only. **No box-shadow animation and no header-height change** |
| `switch.js` | Breaker switch behavior: updates the controlled Livewire/Alpine state and `aria-checked`; calls `fx.spark` on turn-on only when level is `full`. No vibration/haptic effects |

Rules for all modules: respect `motionLevel()`, never throw if an element is missing, never block the main thread (no loops over many nodes per frame), clean up observers and listeners (they must survive `wire:navigate` and Livewire morphing).

### 11.6 Electric component library (`resources/views/components/electric/`)
Build these as reusable Blade components. Each has a static final state that works without animation. All are shown on the `/_ui` page (§11.11).

| ID | Component | Contract |
|---|---|---|
| **E01** | `<x-electric.wire>` | Decorative SVG wire. Props: `orientation` (h/v), `pulse` (bool), `dark` (bool). Base `<path>` in `wire`/`wire-dark` color; when `pulse`, a second `<path pathLength="600">` with `.wire-pulse` in `spark`/`primary`, drawn **right → left**. Wrapper has `ambient` + `x-live`. ≤ 1 KB |
| **E02** | `<x-electric.circuit>` | Decorative circuit-board panel (paths + nodes). Props: `variant` (`footer`, `banner`, `error`, `success`), `pulses` (max 3 pulsing paths). `ambient` + `x-live`, `aria-hidden`, `pointer-events: none`, ≤ 3 KB inline SVG (never an external image) |
| **E03** | `<x-electric.bulb>` | Bulb SVG with `.bulb-glass`, `.bulb-filament`, `.bulb-rays > *` (each with `--i` index), `.bulb-glow`. Props: `on`, `flicker`, `size`. Toggling `data-on` plays the 500 ms transition (§11.4). `flicker` runs once before turning on |
| **E04** | `<x-electric.plug-socket>` | Plug + socket SVG. Prop: `state` (`apart`/`connected`), `interactive` (renders the plug as a `<button>`). Changing to `connected` slides the plug in (500 ms, `ease-snap`); at ~450 ms it calls `fx.spark` at the contact point and the socket LED turns on |
| **E05** | `<x-electric.switch>` | Breaker-style toggle. `role="switch"`, `aria-checked`, works with `wire:model` / Alpine `x-model`; track 52×30 inside a ≥ 44 px tap area; knob uses `--ease-snap` 220 ms; ON track = `primary`; sparks on turn-on (`switch.js`). Used for "متوفر فقط" |
| **E06** | `<x-electric.bolt-loader>` | Zap icon with a dashed outline chasing around it + gentle opacity pulse, 900 ms loop. Mark as `ambient` + `x-live` only while its button/request is loading. Sizes 16/24/40. `role="status"` with visually hidden text "جاري التحميل". Used inside buttons and small areas (never full-page). At level `off`: static bolt |
| **E07** | `fx.spark()` (JS) | Not a Blade component: see §11.5 `fx.js` |
| **E08** | `<x-section-title>` | `<h2>` = zap icon (one-time flicker on reveal, max 2 dips) + title + a wire underline that draws (`scaleX` 0→1, 400 ms, `origin-right`) ending with a node dot that pulses once (`node-pulse`). Optional "عرض الكل" link on the end side |
| **E09** | `<x-sale-badge>` | Red badge with a bolt notch + "-{n}%". On first reveal it plays the one-time flicker (E03 rules). Never loops |
| **E10** | `<x-stock-badge>` | Bulb dot + text. متوفر = lit green dot; الكمية محدودة = amber dot with a single finite opacity emphasis on first reveal (≤ 400 ms, never ambient/looping); غير متوفر = gray unlit dot. Text always present |
| **E11** | `<x-free-shipping-bar>` | Cable-style progress. Start cap = plug icon (static). Track = dark cable (8 px, rounded, `overflow-hidden`). Fill = yellow, grows with `transform: scaleX(p)` and `origin-right`, 600 ms. While incomplete: a soft light sweep (`ambient`, `x-live`) travels along the fill. End cap = **bulb (E03)**: off until `p ≥ 1`, then it flickers once and lights; the fill turns green. Text below: "فاضل X ج.م على الشحن المجاني" / "الشحن المجاني اتفعّل". `role="progressbar"` + `aria-valuenow`. Hidden when threshold = 0. Used in cart and checkout summary |
| **E12** | `<x-electric.tool-icon>` | Animated category/tool icon. Props: `name` (key in `config/icons.php`), `animate` (`hover` \| `reveal` \| `none`). Renders `components/icons/{name}` with `data-icon` and named parts. Animation map in §11.7 |
| **E13** | `<x-btn>` | Button variants (primary, secondary, outline, whatsapp). Primary: yellow, ink text, ≥ 44 px, press scale .97 (150 ms), **finite sheen** on hover-capable devices (a skewed light band on a pseudo-element translating across once, ≤ 600 ms; never `ambient`/looping), `fx.spark` only after a primary action is confirmed successful by the server (add to cart, apply coupon, confirm order); never spark optimistically on pointer press. `loading` prop shows E06 and disables the button |
| **E14** | `<x-cart-icon>` | Header cart = **socket icon** + count badge. Has `data-cart-target`. "Powered" feedback only after the server confirms an add: ring (`animate-ring` once) + badge `animate-pop` + spark. `aria-live="polite"` for the count |
| **E15** | Navigation progress | CSS for Livewire's built-in `wire:navigate` progress bar: a primary-yellow wire whose head has a small spark-colored glow (verify the exact class names in the Livewire 4 docs; NProgress-style `#nprogress .bar` / `.peg`) |
| **E16** | `<x-electric.scene>` | Illustrations for states. Prop `name`: `404`, `500`, `empty-cart`, `empty-search`, `empty-wishlist`, `success`. Composed from E02–E05. Behavior in §6.10 and §11.8 (M63, M70–M73). Every scene is real content first |

### 11.7 Tool-icon animation map (identity per category)
Triggers: **hover-capable devices** → looping sub-part animations run only while the tile is hovered or focused (`@media (hover: hover)`); **touch devices** → finite animation plays once when the tile is first revealed (`@media (hover: none)`, `.is-visible`). Level `lite`/`off` → static icon. Only infinite sub-part loops are marked `ambient` + `x-live`; finite reveal motions are not.

| Main category | Icon key | Animation |
|---|---|---|
| تأسيس كهرباء | `breaker` | the middle breaker lever flips down/up (`--ease-snap`, 220 ms) and a small bolt above it flickers once |
| تشطيب كهرباء | `plug-socket` | plug slides into the socket, LED lights (same mechanics as E04) |
| إنارة وديكور | `lightbulb` | glow + rays fade in (same mechanics as E03), then hold |
| كهرباء صناعية | `cog` | gear rotates in 4 steps (`gear-tick`, `steps(4)`) |
| سمارت هوم | `wifi` | three arcs light in sequence (`wifi-wave`, delays 0/150/300 ms) |
| كاميرات وانتركم | `cctv` | red REC dot blinks (`rec-blink`, 1.2 s) while the lens ring pulses once |
| مستلزمات | `power-strip` | four LEDs chase left → right (`led-chase`, 150 ms stagger) |
| عدد ومعدات | `drill` | drill bit spins (`spin-linear`, 0.5 s) |
| أدوات منزلية | `fan` | blades spin (`spin-linear`, 0.9 s) |

Other whitelist keys: `lamp-ceiling` (glow like `lightbulb`), `hammer` (`hammer-swing`, pivot at the handle end), `wrench` (rotate −15° → 0 once), `heater` (3 wavy lines `heat-rise`, 0.5 s stagger), `cable` (wire pulse along the cable), `zap` (one-time flicker), `gauge` (`needle-sweep`), `router` (like `wifi`), `battery-charging` (inner bars light in sequence), `shield-check` / `badge-check` (stroke draw once), `receipt` (line strokes draw one by one), `truck` (nudge 3 px once), `house` / `plug-zap` (static). Subcategories use their own `icon` or inherit the parent's.

Default **why-us** icons: `badge-check` (أصلي 100%), `gauge` (فحص قبل الشحن), `shield-check` (ضمان), `receipt` (فاتورة رسمية) — each draws/sweeps once on reveal.

Hover CSS pattern (apply per icon):
```css
@media (hover: hover) {
  html[data-motion="full"] .group:is(:hover, :focus-visible) [data-icon="drill"] .drill-bit { animation: spin-linear .5s linear infinite; }
}
@media (hover: none) {
  html[data-motion="full"] .is-visible [data-icon="drill"] .drill-bit { animation: spin-linear .5s linear 4; }
}
```

### 11.8 Animation catalog
Priority: **P1** must ship with the phase · **P2** ship with the phase if it fits, otherwise Phase 9B · **P3** optional. "Phase" says where to build it (Phase labels as in PHASE_PROMPTS.md). The `ambient` class, `x-live` and level rules (§11.2–11.3) apply to every row.

| ID | Page | Element | Effect | Tech | P | Phase |
|---|---|---|---|---|---|---|
| M01 | Global | Navigation | SPA-like page changes with hover prefetch; progress bar = yellow wire with spark head (E15); `@persist` the WhatsApp float and `#fx-root` | `wire:navigate` | P1 | 4A |
| M02 | Global | Reveal | Sections/cards fade-up 400 ms; grids stagger the first 8 items | `x-reveal` | P1 | 1C (plumbing), applied in 4B+ |
| M03 | Global | Buttons | Press scale .97; primary sheen on hover-capable devices; spark only after confirmed primary-action success (E13) | CSS + `fx` | P1 | 1C |
| M04 | Global | Inputs | Focus = "energized" ring: a pre-drawn ring element fades in (opacity, 150 ms), never `box-shadow` animation | CSS | P1 | 1C |
| M05 | Global | Loading | Skeletons (`animate-pulse`, `ambient` + `x-live`, while visible and loading only) for content; bolt-loader (E06) inside buttons | `wire:loading` / `wire:target` | P1 | 1C |
| M06 | Global | Toast | Slides in (top on mobile, bottom-start on desktop), auto-dismiss 3.5 s, pauses on hover, spark on the success icon, action "عرض السلة" | Alpine `x-transition` | P1 | 1C (built), 5A (wired) |
| M07 | Global | WhatsApp float | One subtle one-time ring (total ≤ 600 ms) starting 5 s after load, then static; inside `@persist` so it does not replay per navigation | `animate-ring` | P2 | 4A |
| M08 | Global | Looping control | Every looping SVG is paused while off-screen or tab hidden | `x-live` | P1 | 1C |
| M10 | Header | Scroll state | After 8 px, a pre-drawn border/overlay fades in using opacity only; no animated shadow and no height change | `header.js` | P1 | 4A |
| M11 | Topbar | Marquee | Messages scroll with zap-icon separators; pauses on hover/focus/touch; static in lite/off. Direction: the one in which each message's first word enters first (verify on real RTL text; single CSS variable `--marquee-dir`). Mark `ambient` + `x-live`; pause when the topbar is not eligible | CSS keyframes, `ambient`, `x-live` | P1 | 4A |
| M12 | Header | Mega menu | Fade + 8 px slide, 150 ms, hover-intent 120 ms, keyboard accessible; category tool-icons animate on item hover | Alpine | P1 | 4A |
| M13 | Header | Mobile drawer | Slides from inline-start (right edge in RTL) 250 ms; backdrop fades; use native `<dialog>` where practical or verified native focus management with focus containment, Escape close, focus restoration and body scroll lock; category accordion changes state without height animation | Alpine `x-transition` (opacity/transform only) + native dialog APIs | P1 | 4A |
| M14 | Header | Cart icon (E14) | After add: ring once + badge pop + spark | `fly-to-cart.js` | P1 | 5A |
| M15 | Footer | Top wire + back-to-top | Wire with a slow pulse every ~8 s while visible; bolt back-to-top button | E01, `x-live` | P2 | 4A |
| M16 | Header | Search field | Focus ring (M04); dropdown fade + scale from .98 (150 ms); bolt-loader inside the field while loading | Alpine + `wire:loading` | P2 | 3C |
| M20 | Home | Hero slider | Slide/fade transition, autoplay 5 s, pause/play button, bullets with progress fill; **first slide eager + `fetchpriority="high"`, no entrance animation** | Swiper (RTL) | P1 | 4B |
| M21 | Home | Hero ambient | Optional decorative circuit overlay (E02), desktop only, starts after `load`, `aria-hidden` | E02 | P3 | 9B |
| M22 | Home | Promo tiles | Reveal stagger; hover lift 4 px | `x-reveal`, CSS | P2 | 4B |
| M23 | Home | Category grid | Tool-icon animations (§11.7) + tile hover lift; touch plays once on reveal | E12 | P1 | 4B |
| M24 | Global | Section titles | E08 on every section title | E08 | P1 | 1C (built), used everywhere |
| M25 | Home | Brands strip | Logos on a static thin wire rail; user-driven swipe/scroll; optional single wire pulse while visible | Swiper, E01 | P3 | 9B |
| M26 | Home | Product carousels | Reveal; swipe; ~2.2 cards visible on mobile | Swiper | P2 | 4B |
| M27 | Home | Why-us | Icons draw/sweep once on reveal (§11.7), node dot pulses once | SVG + `x-reveal` | P2 | 4B |
| M28 | Home | Wholesale banner | Circuit panel (E02, ≤ 3 pulsing paths) + primary button sheen | E02, E13 | P2 | 4B |
| M30 | Listing/Product | Product card | Hover (hover-capable only): lift 4 px + image scale 1.04; use a static resting shadow and an opacity-faded border overlay (never animate shadow); touch: `active:scale-[.98]`; below-fold images fade in on load (fixed aspect → no CLS) | Tailwind transitions | P1 | 3A |
| M31 | Listing/Product | Sale badge (E09) | One-time bolt flicker on first reveal | CSS | P2 | 3A |
| M32 | Listing/Product | Stock badge (E10) | Bulb dot states; low-stock gets one subtle opacity pulse on first reveal only, then static | CSS | P2 | 3A |
| M33 | Listing | Filters | Mobile bottom sheet slides up 250 ms with drag handle + backdrop fade; "متوفر فقط" uses the breaker switch (E05); chips animate in/out | Alpine, auto-animate | P1 | 3B |
| M34 | Listing | Grid refresh | During a Livewire request the grid dims to 50% and shows skeleton cards | `wire:loading` | P1 | 3B |
| M35 | Search | Dropdown rows | Skeleton rows while loading; row hover background | `wire:loading` | P2 | 3C |
| M40 | Product | Gallery | Swipe, active-thumb ring, double-tap/pinch zoom, lightbox fade + scale | Swiper Thumbs + Zoom | P2 | 3A |
| M41 | Product | Variant chips | Press scale; price and main image cross-fade (150–200 ms); stock badge swaps | Alpine + CSS | P2 | 3A |
| M42 | Product | Quantity tiers | Active tier row highlights (`bg-primary/15` + start border); price cross-fades | Alpine + CSS | P2 | 3A |
| M43 | Product/Cards | Add to cart | **Fly-to-cart with electric trail** + spark at the cart socket + toast (§11.5). Only after the server confirmed | `fly-to-cart.js` | P1 | 5A |
| M44 | Product | Mobile sticky buy bar | Slides up when the main buy button leaves the viewport | `sticky-bar.js` | P1 | 3A |
| M45 | Product | Free-shipping red strip | Static strip with a bolt icon (no loop) | — | P2 | 3A |
| M50 | Cart/Checkout | Free-shipping bar (E11) | Cable fill 600 ms, light sweep while incomplete, bulb lights + fill is rendered green at 100% (state change, not a large-surface color animation) | CSS | P1 | 5A |
| M51 | Cart | Lines | Remove: fade + translate toward inline-end, then remove naturally; quantity number cross-fades; totals use a pre-drawn yellow overlay that fades opacity for 600 ms (no background-color or height animation) | auto-animate, CSS | P2 | 5A |
| M52 | Cart | Coupon | Success: spark + check pop + discount line slides in. Error: shake ±4 px (300 ms) + message | CSS + `fx` | P2 | 5A |
| M53 | Cart | Empty cart | Scene `empty-cart` | E16 | P2 | 5A |
| M60 | Checkout | Confirm button | `btn` loading state: bolt-loader, disabled, "جاري تأكيد الطلب…" | E13 | P1 | 5B |
| M61 | Checkout | Field errors | Failed submit shakes the first invalid field | CSS | P2 | 5B |
| M62 | Checkout | Shipping amount | Cross-fade when the governorate changes | CSS | P2 | 5B |
| M63 | Success | Success scene | Once, ≤ 1,200 ms: plug slides in (0–500 ms) → spark + circuit pulse (450–850 ms) → bulb flickers and lights + check ring draws (850–1,200 ms). Then static. Order number with copy button and "تم النسخ" feedback | E16 `success` | P1 | 5B |
| M64 | Account/Track | Status timeline | Circuit path: completed steps = yellow nodes joined by lit wire; current step gets a one-time two-pulse opacity emphasis then remains statically outlined; upcoming = gray | E01 | P2 | 7 |
| M70 | Error | 404 scene | Unplugged plug + dim bulb; pressing the plug plays plug-in and lights the bulb | E16 | P2 | 9B |
| M71 | Error | 500 scene | Tripped breaker; pressing the lever flips it ON then reloads | E16 | P2 | 9B |
| M72 | Search/Listing | Empty results | Scene `empty-search` (unlit bulb) | E16 | P2 | 3B |
| M73 | Account | Empty wishlist | Scene `empty-wishlist` | E16 | P3 | 7 |
| M80 | Cards/Product | Wishlist heart | Pop (1 → 1.3 → 1) + fill color | CSS | P2 | 7 |

### 11.9 Integration rules (known traps)
- **Third-party DOM vs Livewire morphing:** wrap Swiper, lightbox and any library-managed DOM in `wire:ignore`; initialize from Alpine (`x-data` / `x-init`), **never** from `DOMContentLoaded` (it does not re-run after `wire:navigate`). Anything global (observers, analytics `page_view`) hooks `livewire:navigated`.
- `@persist` for the WhatsApp float and `#fx-root`, so they survive navigation and do not replay. Reconcile `data-motion`/`.js` after `livewire:navigated`; do not duplicate event listeners or observers.
- Lists animated with auto-animate need stable `wire:key`.
- **Livewire 4 `wire:transition`** uses the View Transitions API and has no modifiers: use it plain for small show/hide blocks; use Alpine `x-transition` when custom duration/origin is needed.
- Server first: `cart-added` (and similar events) are dispatched **after** the server action succeeded. Animations never run optimistically for money/stock/order state and never delay the request.
- Swiper RTL: `dir="rtl"` on the container; test swipe direction; mirror arrow icons. Autoplay is enabled only for hero in `full`; pause on reduced-motion, `lite`, `off`, user pause, keyboard focus, hover and touch interaction. Destroy/reconfigure instances safely on `wire:navigate`; the brands rail never autoplays.
- SVG sub-part transforms need `transform-box: fill-box; transform-origin: center` (or the explicit pivot, e.g. hammer handle).
- Hover effects only for hover-capable devices (Tailwind 4 `hover:` already behaves this way; custom `:hover` CSS must sit inside `@media (hover: hover)`).
- Tailwind variants `motion-full:` / `motion-any:` (§11.4) are the standard way to gate utilities by level.
- Loading indicators use `wire:loading` / `wire:target` (Livewire 4 also sets a `data-loading` attribute on elements that trigger requests).
- Glows = pre-drawn elements; animate their `opacity`/`transform` only (§11.2 rule 3).

### 11.10 Motion QA (every phase that adds motion)
1. DevTools: CPU 4× slowdown + Slow 4G; taps remain responsive; no animation delays a server action.
2. Test `full`, `lite`, `off`, OS `prefers-reduced-motion`, Save-Data/2G simulation and low-memory downgrade. The browser may only downgrade the owner setting, never upgrade it.
3. JavaScript disabled: all content and final states remain visible; no hidden reveal content.
4. Widths 360px, 390px, tablet and desktop, RTL: no overflow, no mirrored-icon/direction bugs; hover effects absent on touch.
5. Performance panel: no motion-created long task > 50 ms; global scheduler keeps visible infinite loops ≤ 3; hidden-tab/hidden-section loops pause.
6. Lighthouse mobile ≥ 80 (home, category, product) with `full`; CLS < 0.1. Record exact tested URL, run date, profile and result; never claim a score not measured.
7. Flash check: nothing flashes > 3 Hz; flicker is icon-sized, one-time and ≤ 2 dips.
8. Keyboard and screen-reader checks: switches, plug/breaker buttons, menus, dialogs, carousel controls are operable; focus visible; status/toasts announced politely.
9. Repeat after `wire:navigate`, Livewire morphs and browser back/forward; check event listeners/observers are not duplicated.
10. Confirm hero first image/LCP image is eager, has explicit dimensions, is never revealed from opacity 0 and does not wait for animation.

### 11.11 The `/_ui` UI-kit page (local only)
Route `/_ui` exists only in `local` (404 otherwise; noindex and never in sitemap). It shows all design tokens and reusable storefront components; E01–E16 in meaningful states with replay controls; all category tool-icons; toasts, skeletons, switches, drawers/sheets, cart feedback, product-card states, quantity/unit controls, technical filter controls, free-shipping bar with a demo slider; and a `full/lite/off` selector. It must expose a small diagnostic panel indicating active motion level, reduced-motion status, number of active ambient loops and whether the current device was downgraded. No real store/customer data appears. Every new electric component or catalog animation is added here in the same phase it is built.

---

## 12. Out of scope for v1 (do NOT build)

Multi-language/English UI, SMS/WhatsApp OTP or API automation, automatic courier API (Bosta/Mylerz/Aramex), wholesale customer accounts/price lists, product comparison, loyalty points, multi-vendor, mobile app, Egyptian Tax Authority e-invoicing, abandoned-cart emails, advanced analytics beyond configured GA4, **sound effects, splash screens/preloaders, Lottie, WebGL/3D, parallax, scroll-jacking, confetti, cursor-follow effects, dark mode, mobile bottom navigation bar, partial item returns, mixed per-item return dispositions, partial refunds**. Do not build unit conversions or mixed-unit inventory in v1; each SKU has one base sale unit (§4.11).

*v2 candidates (not now):* ready-made "تأسيس شقة" kits, quote request/BOQ for contractors, product comparison, dark mode.

---

## 13. Global Definition of Done (applies to every phase)

1. In a disposable local/test database only, `php artisan migrate:fresh --seed` works from scratch with no errors. **Never run `migrate:fresh` against staging or production data.**
2. `php artisan test` passes; new money/stock/order logic has Pest feature tests.
3. `vendor/bin/pint` applied; no unused code or debug leftovers.
4. Mobile layout checked at 360px width; RTL correct (no mirrored-icon or alignment bugs).
5. No N+1 queries on listing pages; no secrets/hardcoded store data.
6. At the end of each phase the AI replies with: what was built, files created/changed, how to run/test it manually, assumptions made, and open questions — and **stops** (does not start the next phase).
7. **Motion QA (§11.10) passes** for every phase that adds motion: all three motion levels, reduced motion, no-JS visibility, CLS < 0.1, no above-the-fold reveal.
8. Every new electric component or animation exists in the catalog (§11.8) or §11.6/§11.7, uses only allowed properties (§11.2), and is shown on `/_ui`.
9. No package outside §2 was added; none of the "not allowed" items (GSAP, Lottie, AOS, jQuery, confetti, UI kits, icon packages) appear.
10. Filament/Livewire/Tailwind code uses installed Laravel 13 / Filament 5 / Livewire 4 / Tailwind 4.1+ APIs; Alpine is not installed separately. Composer/npm lockfiles are committed and compatible dependencies are verified.
11. Decimal quantities, sale units and dynamic category attributes work across admin, storefront, cart, checkout, orders, Excel, invoice and tests; no integer-only quantity assumptions remain.
12. Order cancellation/return stock disposition and coupon release rules in §4.6–§4.8 are implemented idempotently and audited.
13. Paymob callback tests cover HMAC, reference, amount, currency, provider transaction uniqueness, duplicates, second successful payment attempt, late success and cancelled-order reconciliation.
14. The phase prompt's acceptance criteria and phase-specific motion IDs all pass. A test that was not run is explicitly marked NOT RUN; no assumed success.


---

## 14. V3 implementation notes and verified documentation references

- Laravel 13 requires PHP 8.3+; Laravel 13 is the fixed target for this new project. Official release/support matrix: https://laravel.com/docs/13.x/releases
- Filament 5 requires Tailwind CSS 4.1+ and supports the v5 Spatie Media Library plugin: https://filamentphp.com/docs/5.x/introduction/installation and https://filamentphp.com/plugins/filament-spatie-media-library
- Livewire 4 bundles/initializes Alpine; do not load another Alpine copy: https://livewire.laravel.com/docs/4.x/installation
- Laravel Excel 4 supports Laravel 12/13 and PHP 8.3+; verify the required extensions in §2.2: https://docs.laravel-excel.com/4.x/getting-started/installation.html
- Tailwind 4 browser baseline is documented here: https://tailwindcss.com/docs/compatibility
- These references were checked while preparing v3 on 2026-11-23. Re-check them during package installation because patch releases and constraints can change.
