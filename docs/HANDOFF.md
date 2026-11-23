# iShop — Session Handoff (2026-11-22)

Read this whole file before doing anything. It is the full context of the planning session that produced this codebase.

Full design doc (source of truth for the schema and rules): https://claude.ai/artifact/9CDi2CvB6QHgBPyCey2Y6r — "iShop — Database Design v2"

---

## 1. The project

- **iShop**: a production-grade e-commerce store, built as a portfolio/CV project by Muhammad Gamal (Laravel backend developer, Alexandria, Egypt). Goal: a real, secure, "live production" quality store — not a CRUD demo.
- README.md in the repo root is the user's own project README (architecture goals, security/performance strategy, roadmap). Keep it.
- The user talks in Egyptian Arabic. Answer in Arabic (Egyptian), code and docs in English.

### How the user wants to work

- **Discuss and agree before writing code.** For anything visual, show mockups first and get approval. Don't decide design details alone.
- **No `php artisan migrate` until the user approves.** Before the first migrate, editing migrations is fine. After it, every change is a NEW migration, never an edit to an old one.
- Use proven packages wherever possible; very high security bar; think through race conditions and idempotency everywhere — "don't let anything slip".
- Be honest: nothing is "100% secure" or "bug-free"; say what's verified and what isn't.

---

## 2. Environment

| Item        | Value                                                                                                                                                  |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Local path  | `C:\laragon\www\ishop` (Windows, Laragon), URL `http://ishop.test`                                                                                     |
| Shell       | Git Bash (MINGW64) — use `cp`, `rm -rf`, `export`, not `copy` / `set`                                                                                  |
| PHP         | 8.3.33 (ZTS x64). `zip` extension is OFF (optional)                                                                                                    |
| DB          | MariaDB 11.4, database `ishop`, user `root`, empty password                                                                                            |
| Node        | npm 11                                                                                                                                                 |
| Production  | **Shared hosting** (LiteSpeed, SSH/SFTP). No long-running processes → no Inertia SSR, no queue worker daemon, likely no Redis                          |
| Known issue | Windows Defender locks Composer temp zips → "Permission denied". Fix: `export COMPOSER_MAX_PARALLEL_HTTP=1` or add `C:\laragon` to Defender exclusions |

### Install history

1. First attempt used `composer create-project laravel/react-starter-kit` → got the **old v1.0.1 tag (Laravel 12, Feb 2025)**. The repo's tags are stale.
2. Reinstalled from **`dev-main`**: `composer create-project laravel/react-starter-kit ishop dev-main --no-scripts` → Laravel 13.17, Fortify 1.43 (2FA + passkeys), Inertia 3, React 19.2, TypeScript, Tailwind 4, Wayfinder. `npm install` = 0 vulnerabilities, `npm run build` OK.
3. `.env` was created from `.env.example` and `key:generate` was run. **`.env` has NOT been edited yet — it still uses SQLite** (laravel.log shows a "database.sqlite does not exist" error from opening the site). Required `.env` values:

```env
APP_NAME=iShop
APP_URL=http://ishop.test
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ishop
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
ISHOP_ADMIN_EMAIL=
ISHOP_ADMIN_PASSWORD=
ISHOP_ADMIN_REQUIRES_2FA=true
```

(Database `ishop` must be created in HeidiSQL: `CREATE DATABASE ishop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`)

### Current status at handoff

- `bash scripts/setup-packages.sh` **was running** (Composer resolving packages). Check `storage/logs/setup-packages.log` — it ends with `SETUP DONE` on success. If it failed, fix and re-run (the script is idempotent enough to re-run).
- Composer chose PHP-8.3-compatible versions automatically: translatable 6.14.x (6.15 needs 8.4), activitylog 4.12.x (5.x needs 8.4), sluggable 4.0.x (5/6 need 8.4). Permission 8.5, medialibrary 11.23, Filament 5.12, laravel-localization 2.4 all support 8.3 + L13.
- **Until the script finishes, the app errors**, because `User` already uses Filament + Spatie classes.

---

## 3. Decisions (all agreed with the user)

| Topic                         | Decision                                                                                                                                                                                                                                                           |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Currency                      | EGP only. Money stored as integer piastres (`UNSIGNED BIGINT`; 1,250.00 EGP = 125000)                                                                                                                                                                              |
| Languages                     | Arabic + English, switcher in the header. Arabic page is a full **RTL mirror** of the English one. URLs `/en/...` and `/ar/...` (mcamara/laravel-localization). Content in translatable JSON columns (spatie/laravel-translatable); admin must fill both languages |
| Checkout                      | **Login required to buy.** Guests can still use cart + wishlist (stored in DB by a guest token cookie) and both **merge into the account on login/registration**                                                                                                   |
| Payments                      | Cash on Delivery + **Paymob** (test mode in development)                                                                                                                                                                                                           |
| Shipping                      | Price per **area**: checkout has governorate dropdown → area dropdown → price shown in total. Managed in admin. Tables `governorates` + `shipping_areas` (no shipping methods)                                                                                     |
| Tax                           | Prices **include** tax (VAT 14%); tax is extracted for the invoice, never added                                                                                                                                                                                    |
| Stock                         | One warehouse                                                                                                                                                                                                                                                      |
| Collections                   | None. Categories only: `/en/all`, `/en/{category-slug}`; product pages `/en/products/{slug}` (stable even if categories change)                                                                                                                                    |
| Storefront stack              | **Laravel 13 + Inertia + React + TypeScript + Tailwind** (official React starter kit). Chosen for CV/portfolio value                                                                                                                                               |
| Admin                         | **Filament** (v5)                                                                                                                                                                                                                                                  |
| Auth                          | **Fortify** (from the starter kit); 2FA **required** for admin roles to enter Filament (skipped in `local`)                                                                                                                                                        |
| SSR                           | **Off** (shared hosting). SEO via server-rendered meta in the root Blade view: title, description, Open Graph, JSON-LD Product schema, canonical, hreflang; generated sitemap.xml. Write React components **SSR-safe** so SSR can be enabled after a VPS move      |
| Queue/cache on shared hosting | `database` drivers; cron runs `schedule:run` every minute, which works the queue with `--stop-when-empty`. Redis later via `.env` only                                                                                                                             |
| PHP version                   | Stayed on 8.3 (couldn't confirm the host has 8.4). Upgrading to 8.4 later = newer Spatie majors                                                                                                                                                                    |
| Tests                         | Pest, including concurrent checkout tests (need MariaDB, not SQLite, for real row locks)                                                                                                                                                                           |

### Packages

`spatie/laravel-permission`, `spatie/laravel-translatable`, `spatie/laravel-medialibrary`, `spatie/laravel-activitylog`, `spatie/laravel-sluggable`, `mcamara/laravel-localization`, `filament/filament`, plus `laravel/sanctum` later for the API layer. (Fortify, Inertia, Wayfinder come with the starter kit.)

---

## 4. Storefront UX (agreed)

- **Add to cart** on a product with no options → adds 1 immediately and **opens the cart drawer (sidebar)**. In the drawer: change quantity, remove line.
- **Product page**: each option (colour, size…) is a **dropdown**; out-of-stock combinations disabled.
- **Category pages**: a **filter drawer** (price, brand, attributes, in stock only) + sorting.
- **Toasts**: small toast bottom-right in English, mirrored (bottom-left) in Arabic, for: added to wishlist, coupon applied, address saved, errors like out of stock. Server messages via Inertia flash (starter kit already has `use-flash-toast.ts`).
- **Open question**: show a toast on add-to-cart too, or is the drawer enough? (Not answered yet.)
- **Design**: the user will send reference images. If it's their own design → match exactly. If it's another brand's site → take the style/layout, don't clone the brand. Make mockups for approval before coding the UI.

---

## 5. Concurrency & idempotency rules (must be implemented exactly)

| Risk                                      | Guard                                                                                                                                                                                                                                                                               |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Two customers buy the last unit           | `SELECT … FOR UPDATE` on each variant inside the order transaction; DB `CHECK (stock_quantity >= 0)` as last line of defence (already in migration)                                                                                                                                 |
| Deadlock between multi-item orders        | Always lock variants in ascending id order; `DB::transaction(..., attempts: 3)`                                                                                                                                                                                                     |
| Coupon over-use                           | Lock coupon row; count `coupon_redemptions` inside the same transaction                                                                                                                                                                                                             |
| Double click / resubmit                   | `orders.idempotency_key` UNIQUE (UUID generated when checkout page renders); repeat returns the existing order. Disabled button is UX only                                                                                                                                          |
| Checkout from two tabs                    | Lock the cart row and empty it in the order transaction; second request finds empty cart → redirect to the order                                                                                                                                                                    |
| Connection lost / page closed mid-payment | Order stays `pending`, stock reserved (decremented at placement). "Pay now" in My orders = new payment attempt on the SAME order. After `payment_expires_at` (30 min, `config('ishop.payment_timeout_minutes')`) a scheduled job cancels + restocks (`cancellation` stock movement) |
| Duplicate webhooks                        | `payment_webhook_events` UNIQUE (gateway, event_key); duplicates acknowledged and skipped                                                                                                                                                                                           |
| Forged webhook                            | Verify Paymob **HMAC** before processing; amount + currency must equal the order. Webhook route excluded from CSRF                                                                                                                                                                  |
| Webhook vs return page race               | Lock order row; `pending → paid` happens once. Return page only reads status / polls; never marks paid                                                                                                                                                                              |
| Delayed webhook                           | Return page queries Paymob's API for transaction status                                                                                                                                                                                                                             |
| Payment after expiry                      | Stock available → re-reserve and confirm; else flag for admin refund                                                                                                                                                                                                                |
| Price changed between cart and checkout   | Recalculate totals server-side at placement; show changes before confirm                                                                                                                                                                                                            |
| Duplicate emails/jobs                     | Notifications `afterCommit`; `ShouldBeUnique` jobs where needed                                                                                                                                                                                                                     |
| Guest cart merge                          | One transaction, lock both carts, quantities summed and capped at stock, guest rows deleted, cookie forgotten                                                                                                                                                                       |

Guest token: random UUID in an encrypted cookie (`HttpOnly`, `Secure`, `SameSite=Lax`, 30 days). Cart/wishlist row created only on first add (not on page view). Cart endpoints rate-limited. Daily job prunes expired guest carts/wishlists.

Other security: rate limits on login/register/cart/checkout/webhooks; policies on every model; security headers middleware (CSP, HSTS, X-Frame-Options); `$fillable` everywhere; upload validation; `APP_DEBUG=false` in production.

---

## 6. Caching, errors, logging

- **Cache is never used for money/stock decisions.** Checkout reads DB under locks.
- `php artisan optimize` on deploy; OPcache on host.
- Data cache (`database` driver now): category tree, governorates/areas, featured products, filter options, settings. Keys include locale. Cleared by model observers; product-related keys use a `catalog_version` counter (no cache tags without Redis). `Cache::flexible()` against stampedes.
- Browser: hashed Vite assets + WebP with 1-year `immutable` headers via `.htaccess`.
- LiteSpeed full-page cache: postponed (risk of leaking one user's cart/CSRF to another).
- **Errors**: central handling in `bootstrap/app.php`, not try/catch everywhere. Named domain exceptions (`OutOfStockException`, `InvalidCouponException`) → friendly localized message. try/catch only around outside services (Paymob) with timeouts/retries. Custom 403/404/419/429/500/503 pages in both languages. Daily logs (14 days) with request id, user id, order number. Error tracker (Sentry or Flare free tier).

---

## 7. What is already written (by the previous session)

All files are in the repo; PHP syntax-checked only (`php -l`), **never executed** (no vendor available there). Expect small fixes when first run.

- `scripts/setup-packages.sh` — installs packages, publishes package config/migrations, `filament:install --panels`, logs to `storage/logs/setup-packages.log`.
- `config/ishop.php` — reserved category slugs, currency, payment timeout, guest cart days, admin 2FA flag.
- `.env.example` — MariaDB defaults + `ISHOP_*` vars.
- `app/Enums/` — `ProductStatus`, `AttributeType`, `StockMovementType`, `CouponType`, `PaymentMethod` (cod, paymob), `PaymentStatus` (order level), `PaymentAttemptStatus` (per attempt), `OrderStatus` (with `allowedTransitions()` / `canTransitionTo()`).
- `database/migrations/`:
    - edited `0001_01_01_000000_create_users_table.php`: + `phone` (unique, nullable), `is_active`, `last_login_at`, soft deletes.
    - new `2026_11_22_000100…000900`: governorates, shipping_areas, categories, brands, attributes + attribute_values, products + category_product (FULLTEXT on `search_text` for MySQL/MariaDB), product_variants (+ stock columns + CHECK constraint) + attribute_value_product_variant, stock_movements, addresses, wishlists + wishlist_items, tax_rates, coupons, carts + cart_items, orders + order_items + order_addresses + order_status_histories, payments + payment_webhook_events + coupon_redemptions.
    - Package migrations (permission, media, activity_log) are published by the setup script.
- `app/Models/` (25): `User` (HasRoles, SoftDeletes, FilamentUser with `canAccessPanel` = active + admin role + confirmed 2FA outside local), `Governorate`, `ShippingArea`, `Category` (nested, sluggable, translatable, reserved-slug guard, media), `Brand`, `ProductAttribute` (table `attributes` — renamed class to avoid clashing with Eloquent's Attribute), `ProductAttributeValue` (table `attribute_values`), `Product` (translatable, sluggable, media gallery, `search_text` rebuilt on save, `published` scope), `ProductVariant` (`stock_quantity` deliberately NOT fillable), `StockMovement`, `Address`, `Wishlist`, `WishlistItem`, `Cart`, `CartItem`, `TaxRate` (`taxIncludedIn()` integer math), `Coupon` (code uppercased), `CouponRedemption`, `Order` (no fillable; route key `number`), `OrderItem`, `OrderAddress`, `OrderStatusHistory`, `Payment`, `PaymentWebhookEvent`; trait `Concerns/HasProductImages` (WebP thumb/card/large conversions).
- `database/seeders/` — `RolesAndPermissionsSeeder` (super-admin, admin, customer + permission list), `GovernorateSeeder` (27 governorates EN/AR), `TaxRateSeeder` (VAT 14% inclusive), `AdminUserSeeder` (from `ISHOP_ADMIN_*`; local fallback `admin@ishop.test` / `password`; refuses in production without env), `DatabaseSeeder` calls them all.
- `app/Providers/AppServiceProvider.php` — `Model::shouldBeStrict()` outside production; `Gate::before` gives super-admin everything. (Watch: strict mode may trip on lazy loading inside packages; relax to logging if needed.)

---

## 8. Next steps (in order)

1. Confirm `setup-packages.sh` finished (`SETUP DONE` in the log); fix anything it reports.
2. Edit `.env` (section 2), create the `ishop` database.
3. Review published package migrations/config; register `AdminPanelProvider` details (brand name, colors, locale).
4. Run `php artisan migrate --seed` **only after the user approves** — then fix whatever the first real run reveals.
5. Services layer: `Inventory` (lock + ledger), `CartService` (guest token, merge on login listener), `WishlistService`, `Pricing` (subtotal, coupon, shipping, inclusive tax), `Checkout` (idempotent order placement, all rules in section 5), `Orders` (status transitions + history), `Payments/Paymob` (intention, webhook with HMAC, inquiry, expiry job).
6. Localization: `/en` + `/ar` routes, locale middleware, RTL in the root view (`dir="rtl"`), language switcher, lang files.
7. Filament resources: categories, brands, attributes, products + variants + images, inventory adjustments, orders (status changes through the service only), coupons, governorates/areas, users/roles, activity log.
8. Storefront UI — **after the user sends design references and approves mockups**: header with language switch, home, category page with filter drawer, product page with option dropdowns, cart drawer, wishlist, checkout (governorate → area), account/orders with "Pay now", toasts.
9. SEO (meta, JSON-LD, hreflang, sitemap), caching layer, error pages, security headers, rate limiters, scheduler (expiry job, guest pruning).
10. Pest tests: cart merge, checkout, coupon limits, webhook idempotency, concurrent last-unit purchase (MariaDB).
11. Deployment to shared hosting: build assets locally, `composer install --no-dev --optimize-autoloader`, `php artisan optimize`, cron for `schedule:run`.

## 9. Open questions for the user

- Toast on add-to-cart as well as opening the drawer, or drawer only?
- Design reference images (pending).
- Does the host offer PHP 8.4? (If yes, upgrade and bump Spatie majors.)
