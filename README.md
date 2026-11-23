# iShop

**[English](#english)** · **[العربية](#العربية)**

---

<a id="english"></a>

## English

**iShop** is a production-oriented e-commerce platform built with Laravel, with a strong focus on clean architecture, security, data integrity, performance and maintainability. It is a portfolio project engineered like a real store, not a CRUD demo: every feature was designed first (database design, mockups, agreed business rules), then built and covered by automated tests.

The store sells in **Egyptian pounds**, in **Arabic and English** (the Arabic site is a full right-to-left mirror), with **cash on delivery** working end to end and **Paymob** card/wallet payments as the next step.

### Project status

| Area | Status |
| --- | --- |
| Database design & migrations | ✅ Done |
| Admin panel (Filament) | ✅ Catalog, inventory, shipping, coupons, orders |
| Storefront (Inertia + React, `/en` and `/ar`) | ✅ Home, categories, product, cart, wishlist, checkout, My account, sign-in |
| Cart, wishlist, checkout, orders | ✅ With row locks, idempotency and concurrency tests |
| Cash on delivery | ✅ |
| **Paymob (cards & mobile wallets)** | ⏳ Next |
| Announcement bar, reviews, info pages | ⏳ Planned (designs approved) |
| SEO (meta, JSON-LD, sitemap), custom error pages, security headers | ⏳ Planned |
| Production deployment | ⏳ Planned |

Automated tests: **157 passing**, plus **4 concurrency tests** that race real PHP processes against MySQL/MariaDB.

### Technology stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Fortify (2FA + passkeys) |
| Storefront | Inertia 3, React 19, TypeScript, Tailwind CSS 4, Vite |
| Admin panel | Filament 5 |
| Database | MariaDB 11.4 in production (MySQL 8.4 locally; migrations work on both) |
| Packages | spatie/laravel-permission, -translatable, -medialibrary, -activitylog, -sluggable; bezhansalleh/filament-language-switch |
| Fonts | Jost, Marcellus, IBM Plex Sans Arabic — self-hosted (no request to Google) |
| Hosting | Shared hosting (LiteSpeed, SSH/SFTP), so no long-running processes |

### Features

#### Storefront (customers)

- **Two languages**: `/en/...` and `/ar/...`, language switch in the header, full RTL for Arabic, choice remembered.
- **Home**: hero and best sellers in tabs per top-level category.
- **Category page**: filter drawer (sub-category, colour swatches, in stock only, price range), sorting, products per row, active-filter chips, pagination. Search from the header.
- **Product page**: gallery (photos per colour), colour as swatches and other options as dropdowns, sold-out combinations disabled, "Only 2 left" for low stock, wishlist, suggestions.
- **Quick add**: the "+" on a product card adds it straight away, or opens a small window to pick colour/size without leaving the page.
- **Cart drawer**: change quantities, remove, "Complete with" suggestion. Every add button shows a spinner until the item is in the cart.
- **Guests can shop**: cart and wishlist work without an account and move into the account at sign-in or registration.
- **Changed-cart warnings**: if a product was removed, a colour/size dropped, or stock fell below the quantity while the cart sat there, each line says why, and checkout stays disabled until the cart is fixed.
- **Checkout** (sign-in required): Information → Payment, saved or new address, governorate then area with its shipping price, discount code, order note, live totals including VAT.
- **My account**: orders with a tracking line (Placed → Confirmed → Preparing → Shipped → Delivered), cancel while awaiting confirmation, address book, wishlist, profile, password, two-factor authentication and passkeys.

#### Admin panel (`/admin`, staff only, 2FA required outside local)

- Arabic/English panel with RTL.
- **Categories** (nested, bilingual, images, SEO), **brands**, **product options** (colour with swatch colour, size…).
- **Products**: variants generated from chosen options, a general gallery plus one image group per colour, suggested products, publish scheduling.
- **Inventory**: stock is never typed directly; "Adjust stock" with a reason (restock, stock count, customer return, remove damaged) and a full stock history per variant.
- **Shipping**: 27 governorates, areas with their own price, "same price for all areas".
- **Coupons**: percentage (with cap), fixed amount, free shipping; minimum order, total and per-customer limits, start/end dates.
- **Orders**: tabs per status with a "to confirm" badge, order page with snapshots and history, status buttons (confirm → prepare → ship → deliver, cancel/return with a required reason).
- Roles and permissions, activity log.

### Architecture

Business rules live in services, not in controllers or Filament pages:

| Service | Responsibility |
| --- | --- |
| `App\Services\Inventory` | The **only** writer of stock: row lock, never below zero, one ledger row per change |
| `App\Services\Cart\CartService`, `WishlistService` | Guest/user cart and wishlist, limits, ownership |
| `App\Services\Cart\GuestBasketMerger` | Moves guest cart and wishlist into the account at sign-in |
| `App\Services\Cart\CartReview` | What changed on each cart line since it was added |
| `App\Services\Catalog\VariantGenerator` | Builds variants from option combinations |
| `App\Services\Checkout\Pricing` | Subtotal, coupon, shipping, VAT (integer piastres) |
| `App\Services\Checkout\PlaceOrder` | Turns a cart into an order under locks (see below) |
| `App\Services\Orders\OrderWorkflow` | The only way an order's status changes |

Other conventions:

- Thin controllers, Form Requests for validation, policies on every admin model.
- **Money is stored as integer piastres** (EGP 1,250.00 = `125000`) — no floats anywhere.
- Order lines, addresses and names are **snapshots**: editing a product later never changes a past invoice.
- `Model::shouldBeStrict()` outside production: lazy loading and silently dropped attributes throw during development.
- Domain exceptions (`CartException`, `CheckoutException`, `InsufficientStockException`…) carry translated, customer-safe messages.

### Data integrity and concurrency

Every rule below is enforced in code and covered by tests:

| Risk | Guard |
| --- | --- |
| Two customers buy the last unit | Variant rows locked `FOR UPDATE`; DB `CHECK (stock_quantity >= 0)` as the last line of defence |
| Deadlocks between multi-item orders | Rows always locked in ascending id order; transactions retried 3 times |
| A single-use coupon used twice | Coupon row locked; redemptions counted with a **locking read** (a plain read under REPEATABLE READ sees an old snapshot — found and fixed by the race tests) |
| Double click / resubmit | Unique idempotency key per checkout page; a repeat returns the same order |
| Checkout from two tabs | Cart row locked and emptied in the order transaction |
| Prices changed since the page opened | Totals recomputed from locked rows; nothing saved, new total shown |
| Cancelled order | Stock and coupon use given back automatically |

### Security

- Fortify authentication with **two-factor authentication** and **passkeys**; 2FA required for staff to enter the admin panel.
- Role/permission checks (spatie/laravel-permission) and policies; customers only ever reach their own orders and addresses (others get 404).
- Rate limits on cart, checkout and coupon attempts.
- Guest cart cookie: random UUID, encrypted, HttpOnly, SameSite=Lax, Secure on HTTPS.
- Mass-assignment protection (stock is deliberately not fillable), CSRF, escaped output, upload type/size validation, **no SVG uploads** (they can carry scripts).
- Deleted customer accounts are anonymised: the row stays for order history, personal data is removed, the email can register again.
- `.env` and secrets never committed.

### Testing

```bash
php artisan test                    # 157 feature tests (SQLite in memory)
php artisan test tests/Concurrency  # real races on MySQL/MariaDB (database: ishop_testing)
```

Run the concurrency tests against MariaDB as well before going live, since that is the production engine.

### Running it locally

Requirements: PHP 8.3 (`intl`, `gd`, `zip`, `pdo_mysql`, `bcmath`), Composer, Node 20+, MySQL 8.4 or MariaDB 11.4.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# set DB_CONNECTION / DB_DATABASE in .env, create the database (utf8mb4_unicode_ci)
php artisan migrate --seed                         # roles, governorates, VAT, first admin
php artisan db:seed --class=DemoCatalogSeeder      # optional: sample catalog + shipping areas
npm run build
```

- Store: `/en` or `/ar` · Admin: `/admin`
- Locally the first admin is `admin@ishop.test` (see `AdminUserSeeder`); in production set `ISHOP_ADMIN_EMAIL` and `ISHOP_ADMIN_PASSWORD`.
- Emails go to `storage/logs/laravel.log` locally.

### Deployment (shared hosting)

```bash
npm run build                                       # locally
composer install --no-dev --optimize-autoloader     # on the server
php artisan migrate --force
php artisan optimize
```

Cron: `* * * * * php artisan schedule:run` (prunes old guest carts daily; will also expire unpaid online orders). The production `.env` is kept on the server and never synced.

### What's next

1. Paymob: payment attempt, HMAC-verified webhook, return page, expiry of unpaid orders with restock.
2. Announcement bar with countdown, product reviews, info pages (shipping, returns, about).
3. SEO: meta tags, Open Graph, JSON-LD, hreflang, sitemap.
4. Custom error pages, security headers, caching layer.
5. Deployment and a MariaDB run of the concurrency tests.

---

<a id="العربية"></a>

## العربية

<div dir="rtl">

**iShop** متجر إلكتروني بمستوى إنتاج حقيقي مبني بـ Laravel، بتركيز على البنية النظيفة والأمان وسلامة البيانات والأداء وسهولة الصيانة. هو مشروع portfolio لكنه مصمم كمتجر حقيقي وليس مجرد CRUD: كل ميزة اتصممت الأول (تصميم الداتابيز، الـ mockups، قواعد العمل المتفق عليها)، وبعدين اتبنت واتغطت باختبارات تلقائية.

المتجر بيبيع **بالجنيه المصري**، **بالعربي والإنجليزي** (الموقع العربي نسخة كاملة من اليمين للشمال)، و**الدفع عند الاستلام** شغّال من أوله لآخره، و**Paymob** للبطاقات والمحافظ هو الخطوة الجاية.

### حالة المشروع

| الجزء | الحالة |
| --- | --- |
| تصميم الداتابيز والـ migrations | ✅ تم |
| لوحة التحكم (Filament) | ✅ الكتالوج، المخزون، الشحن، الكوبونات، الطلبات |
| واجهة المتجر (Inertia + React، `/en` و `/ar`) | ✅ الرئيسية، التصنيفات، المنتج، السلة، المفضلة، إتمام الطلب، حسابي، تسجيل الدخول |
| السلة، المفضلة، إتمام الطلب، الطلبات | ✅ مع الـ locks ومنع التكرار واختبارات التزامن |
| الدفع عند الاستلام | ✅ |
| **Paymob (البطاقات والمحافظ)** | ⏳ الخطوة الجاية |
| شريط الإعلان، التقييمات، صفحات المعلومات | ⏳ مخطط (التصميم متوافق عليه) |
| SEO (الـ meta، JSON-LD، الـ sitemap)، صفحات أخطاء مخصصة، security headers | ⏳ مخطط |
| النشر على السيرفر | ⏳ مخطط |

الاختبارات التلقائية: **157 ناجح**، بالإضافة لـ **4 اختبارات تزامن** بتشغّل عمليات PHP حقيقية في نفس اللحظة على MySQL/MariaDB.

### التقنيات المستخدمة

| الطبقة | التقنية |
| --- | --- |
| السيرفر | PHP 8.3، Laravel 13، Fortify (التحقق بخطوتين + passkeys) |
| واجهة المتجر | Inertia 3، React 19، TypeScript، Tailwind CSS 4، Vite |
| لوحة التحكم | Filament 5 |
| الداتابيز | MariaDB 11.4 على السيرفر (MySQL 8.4 محليًا، والـ migrations شغّالة على الاتنين) |
| الباكدجات | spatie/laravel-permission و -translatable و -medialibrary و -activitylog و -sluggable، و bezhansalleh/filament-language-switch |
| الخطوط | Jost و Marcellus و IBM Plex Sans Arabic، متحمّلة من سيرفرنا (من غير طلب لـ Google) |
| الاستضافة | استضافة مشتركة (LiteSpeed، SSH/SFTP)، فمفيش عمليات بتفضل شغّالة طول الوقت |

### المميزات

#### واجهة المتجر (العملاء)

- **لغتين**: `/en/...` و `/ar/...`، وزرار تبديل اللغة في الهيدر، والعربي من اليمين للشمال بالكامل، واختيار اللغة بيتحفظ.
- **الرئيسية**: صورة رئيسية و"الأكثر مبيعًا" بتابات لكل تصنيف رئيسي.
- **صفحة التصنيف**: فلاتر في درج جانبي (التصنيف الفرعي، الألوان مربعات، المتوفر فقط، نطاق السعر)، والترتيب، وعدد المنتجات في الصف، والفلاتر المختارة كأزرار تتشال، والتنقل بين الصفحات، والبحث من الهيدر.
- **صفحة المنتج**: معرض صور (صور لكل لون)، الألوان مربعات وباقي الخيارات dropdown، التركيبات اللي خلصت متعطّلة، "متبقي 2 فقط" لما الكمية تقل، المفضلة، ومنتجات مقترحة.
- **الإضافة السريعة**: زرار "+" على كارت المنتج بيضيفه على طول، أو بيفتح نافذة صغيرة تختار منها اللون والمقاس من غير ما تسيب الصفحة.
- **درج السلة**: تعديل الكمية، الحذف، و"أكمل إطلالتك". كل زرار إضافة بيظهر عليه loading لحد ما القطعة تتحط في السلة.
- **الزائر يقدر يتسوق**: السلة والمفضلة شغّالين من غير حساب، وبيتنقلوا للحساب أول ما يسجّل دخول أو يعمل حساب.
- **تنبيه المنتجات اللي اتغيّرت**: لو منتج اتشال، أو لون أو مقاس اتوقف، أو المخزون بقى أقل من الكمية وهو سايب السلة، كل سطر بيقول السبب، وإتمام الطلب بيفضل متعطّل لحد ما يصلّح السلة.
- **إتمام الطلب** (بتسجيل دخول): البيانات ← الدفع، عنوان محفوظ أو جديد، المحافظة ثم المنطقة بسعر شحنها، كود الخصم، ملاحظة للطلب، والإجمالي شامل الضريبة بيتحدث مباشرة.
- **حسابي**: الطلبات مع خط تتبّع (تم الطلب ← مؤكد ← قيد التجهيز ← تم الشحن ← تم التوصيل)، وإلغاء الطلب طول ما هو في انتظار التأكيد، ودفتر العناوين، والمفضلة، والملف الشخصي، وكلمة المرور، والتحقق بخطوتين، والـ passkeys.

#### لوحة التحكم (`/admin`، للفريق بس، والتحقق بخطوتين إجباري على السيرفر)

- اللوحة بالعربي والإنجليزي ومن اليمين للشمال.
- **التصنيفات** (متداخلة، باللغتين، بصور و SEO)، و**العلامات التجارية**، و**خيارات المنتجات** (اللون بكود لونه، المقاس…).
- **المنتجات**: الـ variants بتتعمل تلقائي من الخيارات، ومعرض صور عام ومجموعة صور لكل لون، ومنتجات مقترحة، وجدولة النشر.
- **المخزون**: الرقم عمره ما بيتكتب مباشرة؛ "تعديل المخزون" بسبب (توريد، جرد، مرتجع من عميل، خصم تالف) وسجل حركة كامل لكل variant.
- **الشحن**: 27 محافظة، ومناطق لكل منطقة سعرها، و"سعر واحد لكل المناطق".
- **الكوبونات**: نسبة (بحد أقصى)، مبلغ ثابت، شحن مجاني؛ حد أدنى للطلب، وحدود استخدام كلية ولكل عميل، وتاريخ بداية ونهاية.
- **الطلبات**: تابات لكل حالة وعدّاد للطلبات "بانتظار التأكيد"، وصفحة الطلب بالنسخ المحفوظة والسجل، وأزرار الحالة (تأكيد ← تجهيز ← شحن ← توصيل، والإلغاء والمرتجع بسبب إجباري).
- الأدوار والصلاحيات، وسجل النشاط.

### البنية

قواعد العمل موجودة في الـ services، مش في الـ controllers ولا صفحات Filament:

| الـ Service | المسؤولية |
| --- | --- |
| `App\Services\Inventory` | **الوحيد** اللي بيغيّر المخزون: lock على الصف، مينزلش تحت الصفر، وسطر في السجل لكل تغيير |
| `App\Services\Cart\CartService` و `WishlistService` | سلة ومفضلة الزائر والعميل، والحدود، والملكية |
| `App\Services\Cart\GuestBasketMerger` | نقل سلة ومفضلة الزائر للحساب عند تسجيل الدخول |
| `App\Services\Cart\CartReview` | إيه اللي اتغيّر في كل سطر في السلة من وقت ما اتضاف |
| `App\Services\Catalog\VariantGenerator` | إنشاء الـ variants من تركيبات الخيارات |
| `App\Services\Checkout\Pricing` | المجموع، الكوبون، الشحن، الضريبة (بالقرش وأرقام صحيحة) |
| `App\Services\Checkout\PlaceOrder` | تحويل السلة لطلب تحت الـ locks (تحت) |
| `App\Services\Orders\OrderWorkflow` | الطريقة الوحيدة لتغيير حالة الطلب |

اتفاقيات تانية:

- Controllers خفيفة، و Form Requests للتحقق، و policies على كل موديل في اللوحة.
- **الفلوس بتتخزن بالقرش كأرقام صحيحة** (1,250.00 جنيه = `125000`)، ومفيش أرقام عشرية في أي مكان.
- سطور الطلب والعنوان والأسماء **نسخ محفوظة**: تعديل المنتج بعدين عمره ما بيغيّر فاتورة قديمة.
- `Model::shouldBeStrict()` خارج الإنتاج: التحميل الكسول والبيانات اللي بتتجاهل بصمت بيعملوا خطأ أثناء التطوير.
- استثناءات خاصة بالمجال (`CartException` و `CheckoutException` و `InsufficientStockException`…) برسائل مترجمة وآمنة للعرض للعميل.

### سلامة البيانات والتزامن

كل قاعدة هنا متطبقة في الكود ومتغطية باختبارات:

| الخطر | الحماية |
| --- | --- |
| عميلين بيشتروا آخر قطعة | lock على صفوف الـ variants بـ `FOR UPDATE`، و `CHECK (stock_quantity >= 0)` في الداتابيز كخط دفاع أخير |
| deadlock بين طلبات فيها كذا منتج | الـ locks دايمًا بترتيب الـ id، والـ transaction بتتعاد 3 مرات |
| كوبون "مرة واحدة" يتستخدم مرتين | lock على صف الكوبون، وعدّ الاستخدامات بـ **قراءة فيها lock** (القراءة العادية في REPEATABLE READ بتشوف صورة قديمة، واختبارات السباق هي اللي لقت المشكلة دي واتصلحت) |
| الضغط مرتين أو إعادة الإرسال | مفتاح فريد لكل صفحة checkout؛ التكرار بيرجّع نفس الطلب |
| إتمام الطلب من تابين | lock على السلة وتفريغها جوه نفس الـ transaction |
| السعر اتغيّر بعد فتح الصفحة | الإجمالي بيتحسب من الصفوف المقفولة؛ مفيش حاجة بتتحفظ والإجمالي الجديد بيظهر |
| إلغاء الطلب | المخزون واستخدام الكوبون بيرجعوا تلقائي |

### الأمان

- تسجيل دخول بـ Fortify مع **التحقق بخطوتين** و **passkeys**، والتحقق بخطوتين إجباري للفريق علشان يدخل اللوحة.
- صلاحيات وأدوار (spatie/laravel-permission) و policies؛ العميل بيوصل لطلباته وعناوينه هو بس (أي حد تاني بياخد 404).
- حد أقصى لعدد الطلبات على السلة والـ checkout ومحاولات الكوبون.
- cookie سلة الزائر: UUID عشوائي، مشفّر، HttpOnly، و SameSite=Lax، و Secure على HTTPS.
- حماية من الـ mass assignment (المخزون مقصود إنه مش fillable)، و CSRF، وتأمين المخرجات، والتحقق من نوع وحجم الملفات المرفوعة، و**منع رفع SVG** (ممكن يكون فيه سكريبت).
- حذف حساب العميل بيمسح بياناته الشخصية: السطر بيفضل علشان سجل الطلبات، والبيانات الشخصية بتتشال، والإيميل يقدر يتسجّل تاني.
- ملف `.env` والأسرار عمرهم ما بيترفعوا على git.

### الاختبارات

```bash
php artisan test                    # 157 اختبار (SQLite في الذاكرة)
php artisan test tests/Concurrency  # سباقات حقيقية على MySQL/MariaDB (قاعدة ishop_testing)
```

لازم اختبارات التزامن تتشغل كمان على MariaDB قبل ما الموقع يشتغل، لأنها الداتابيز اللي على السيرفر.

### التشغيل على الجهاز

المتطلبات: PHP 8.3 (`intl` و `gd` و `zip` و `pdo_mysql` و `bcmath`)، و Composer، و Node 20 أو أحدث، و MySQL 8.4 أو MariaDB 11.4.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# ظبّط DB_CONNECTION و DB_DATABASE في .env، واعمل الداتابيز (utf8mb4_unicode_ci)
php artisan migrate --seed                         # الأدوار، المحافظات، الضريبة، أول أدمن
php artisan db:seed --class=DemoCatalogSeeder      # اختياري: منتجات تجريبية ومناطق شحن
npm run build
```

- المتجر: `/en` أو `/ar` · اللوحة: `/admin`
- على الجهاز أول أدمن هو `admin@ishop.test` (شوف `AdminUserSeeder`)، وعلى السيرفر لازم تحدد `ISHOP_ADMIN_EMAIL` و `ISHOP_ADMIN_PASSWORD`.
- الإيميلات على الجهاز بتتكتب في `storage/logs/laravel.log`.

### النشر (استضافة مشتركة)

```bash
npm run build                                       # على الجهاز
composer install --no-dev --optimize-autoloader     # على السيرفر
php artisan migrate --force
php artisan optimize
```

الـ Cron: `* * * * * php artisan schedule:run` (بيمسح سلات الزوار القديمة كل يوم، وهيلغي الطلبات الأونلاين اللي ما اتدفعتش). ملف `.env` بتاع السيرفر بيفضل على السيرفر وعمره ما بيتنقل.

### الخطوات الجاية

1. Paymob: محاولة الدفع، والـ webhook مع التحقق بالـ HMAC، وصفحة الرجوع، وإلغاء الطلبات اللي ما اتدفعتش وإرجاع المخزون.
2. شريط إعلان بعدّاد تنازلي، وتقييمات المنتجات، وصفحات المعلومات (الشحن، الاسترجاع، من نحن).
3. SEO: الـ meta tags و Open Graph و JSON-LD و hreflang والـ sitemap.
4. صفحات أخطاء مخصصة، و security headers، وطبقة الكاش.
5. النشر، وتشغيل اختبارات التزامن على MariaDB.

</div>

---

## Author · المطوّر

**Muhammad Gamal**

Software Engineer  
Laravel • PHP • E-commerce Development

## License · الترخيص

This project is currently developed as a portfolio and engineering project. Licensing terms will be defined before public distribution.

المشروع حاليًا مشروع portfolio وهندسي، وشروط الترخيص هتتحدد قبل النشر العام.
