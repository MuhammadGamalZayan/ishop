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
- Security headers on every response (nosniff, framing blocked, strict referrer, permissions policy); HTTPS forced in production with HSTS and secure session cookies.
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
php artisan storage:link                           # serve uploaded product photos
php artisan db:seed --class=DemoCatalogSeeder      # optional: sample catalog, photos, shipping areas
npm run build
```

- Store: `/en` or `/ar` · Admin: `/admin`
- Locally the first admin is `admin@ishop.test` (see `AdminUserSeeder`); in production set `ISHOP_ADMIN_EMAIL` and `ISHOP_ADMIN_PASSWORD`.
- Emails go to `storage/logs/laravel.log` locally.

### Deployment (shared hosting)

Step-by-step guide for FastComet / cPanel: **[deploy/DEPLOY.md](deploy/DEPLOY.md)**.

- `deploy/build-release.ps1` builds an upload-ready zip from the last commit (production packages, built frontend; never `.env`, logs or uploads).
- `deploy/env.production.example` lists every production setting.
- One cron entry (`schedule:run` every minute) runs the queue (photo conversions, mail) and the nightly clean-up; no long-running processes needed.

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

**iShop** متجر إلكتروني مُعَدّ لبيئة الإنتاج، مبني بإطار Laravel، مع تركيز على البنية النظيفة والأمان وسلامة البيانات والأداء وسهولة الصيانة. المشروع جزء من معرض أعمال (Portfolio)، لكنه مصمَّم كمتجر حقيقي لا كتطبيق CRUD بسيط: صُمِّمت كل ميزة أولًا (تصميم قاعدة البيانات، والنماذج الأولية للواجهات، وقواعد العمل المتفق عليها)، ثم نُفِّذت وغُطِّيت باختبارات آلية.

يبيع المتجر **بالجنيه المصري**، **باللغتين العربية والإنجليزية** (الموقع العربي نسخة كاملة باتجاه من اليمين إلى اليسار)، ويعمل **الدفع عند الاستلام** من البداية إلى النهاية، أما الدفع بالبطاقات والمحافظ الإلكترونية عبر **Paymob** فهو الخطوة التالية.

### حالة المشروع

| الجزء | الحالة |
| --- | --- |
| تصميم قاعدة البيانات وملفات الترحيل (Migrations) | ✅ مكتمل |
| لوحة التحكم (Filament) | ✅ الكتالوج، والمخزون، والشحن، والكوبونات، والطلبات |
| واجهة المتجر (Inertia + React، `/en` و `/ar`) | ✅ الرئيسية، والتصنيفات، والمنتج، والسلة، والمفضلة، وإتمام الطلب، وحسابي، وتسجيل الدخول |
| السلة والمفضلة وإتمام الطلب والطلبات | ✅ مع أقفال الصفوف، ومنع التكرار، واختبارات التزامن |
| الدفع عند الاستلام | ✅ |
| **Paymob (البطاقات والمحافظ الإلكترونية)** | ⏳ الخطوة التالية |
| شريط الإعلانات، والتقييمات، وصفحات المعلومات | ⏳ مخطط له (التصميم معتمد) |
| تحسين محركات البحث (الوسوم الوصفية، JSON-LD، خريطة الموقع)، وصفحات أخطاء مخصصة، وترويسات الأمان | ⏳ مخطط له |
| النشر على الخادم | ⏳ مخطط له |

الاختبارات الآلية: **157 اختبارًا ناجحًا**، إضافة إلى **4 اختبارات تزامن** تُشغِّل عمليات PHP حقيقية في اللحظة نفسها على MySQL/MariaDB.

### التقنيات المستخدمة

| الطبقة | التقنية |
| --- | --- |
| الخادم | PHP 8.3، و Laravel 13، و Fortify (التحقق بخطوتين ومفاتيح المرور Passkeys) |
| واجهة المتجر | Inertia 3، و React 19، و TypeScript، و Tailwind CSS 4، و Vite |
| لوحة التحكم | Filament 5 |
| قاعدة البيانات | MariaDB 11.4 على الخادم (و MySQL 8.4 محليًا، وملفات الترحيل تعمل على كليهما) |
| الحزم | spatie/laravel-permission و -translatable و -medialibrary و -activitylog و -sluggable، و bezhansalleh/filament-language-switch |
| الخطوط | Jost و Marcellus و IBM Plex Sans Arabic، تُحمَّل من خادم المتجر نفسه (دون أي طلب إلى Google) |
| الاستضافة | استضافة مشتركة (LiteSpeed، و SSH/SFTP)، لذا لا توجد عمليات تعمل باستمرار في الخلفية |

### المزايا

#### واجهة المتجر (العملاء)

- **لغتان**: `/en/...` و `/ar/...`، مع زر لتبديل اللغة في الترويسة، واتجاه كامل من اليمين إلى اليسار للعربية، وحفظ اختيار الزائر.
- **الصفحة الرئيسية**: صورة رئيسية، وقسم "الأكثر مبيعًا" بتبويب لكل تصنيف رئيسي.
- **صفحة التصنيف**: درج جانبي للتصفية (التصنيف الفرعي، والألوان كمربعات، والمتوفر فقط، ونطاق السعر)، والترتيب، وعدد المنتجات في الصف، وعرض عوامل التصفية النشطة مع إمكانية إزالتها، والتنقل بين الصفحات، والبحث من الترويسة.
- **صفحة المنتج**: معرض صور (مع صور خاصة بكل لون)، والألوان كمربعات وبقية الخيارات كقوائم منسدلة، وتعطيل التركيبات النافدة، وتنبيه "متبقٍّ قطعتان فقط" عند انخفاض المخزون، والإضافة إلى المفضلة، ومنتجات مقترحة.
- **الإضافة السريعة**: زر "+" على بطاقة المنتج يضيفه مباشرة، أو يفتح نافذة صغيرة لاختيار اللون والمقاس دون مغادرة الصفحة.
- **درج السلة**: تعديل الكميات، والحذف، واقتراح "أكمل إطلالتك". ويعرض كل زر إضافة مؤشر تحميل حتى تُضاف القطعة إلى السلة.
- **التسوق دون حساب**: تعمل السلة والمفضلة للزائر، وتنتقلان إلى حسابه عند تسجيل الدخول أو إنشاء حساب.
- **تنبيه المنتجات المتغيرة**: إذا حُذف منتج، أو أُوقف لون أو مقاس، أو قلّ المخزون عن الكمية المطلوبة أثناء بقاء المنتج في السلة، يوضّح كل سطر السبب، ويبقى إتمام الطلب معطَّلًا حتى تُعدَّل السلة.
- **إتمام الطلب** (يتطلب تسجيل الدخول): البيانات ← الدفع، مع عنوان محفوظ أو جديد، واختيار المحافظة ثم المنطقة بسعر الشحن الخاص بها، وكود الخصم، وملاحظة للطلب، وتحديث فوري للإجمالي شاملًا ضريبة القيمة المضافة.
- **حسابي**: الطلبات مع مسار تتبّع (تم الطلب ← مؤكد ← قيد التجهيز ← تم الشحن ← تم التوصيل)، وإمكانية إلغاء الطلب ما دام بانتظار التأكيد، ودفتر العناوين، والمفضلة، والملف الشخصي، وكلمة المرور، والتحقق بخطوتين، ومفاتيح المرور.

#### لوحة التحكم (`/admin`، لفريق العمل فقط، مع إلزامية التحقق بخطوتين على الخادم)

- لوحة باللغتين العربية والإنجليزية مع دعم الاتجاه من اليمين إلى اليسار.
- **التصنيفات** (متداخلة، ثنائية اللغة، مع الصور وبيانات تحسين محركات البحث)، و**العلامات التجارية**، و**خيارات المنتجات** (اللون مع رمزه اللوني، والمقاس…).
- **المنتجات**: إنشاء الأنواع (Variants) تلقائيًا من الخيارات المختارة، ومعرض صور عام مع مجموعة صور لكل لون، ومنتجات مقترحة، وجدولة النشر.
- **المخزون**: لا يُكتب الرقم مباشرة أبدًا؛ بل يُعدَّل عبر "تعديل المخزون" مع ذكر السبب (توريد، أو جرد، أو مرتجع من عميل، أو خصم تالف)، مع سجل حركة كامل لكل نوع.
- **الشحن**: 27 محافظة، ومناطق لكلٍّ منها سعر مستقل، وخيار "سعر واحد لكل المناطق".
- **الكوبونات**: نسبة مئوية (بحد أقصى)، أو مبلغ ثابت، أو شحن مجاني؛ مع حد أدنى للطلب، وحدود استخدام إجمالية ولكل عميل، وتاريخ بداية ونهاية.
- **الطلبات**: تبويبات حسب الحالة مع عدّاد للطلبات "بانتظار التأكيد"، وصفحة للطلب تعرض النسخ المحفوظة والسجل، وأزرار لتغيير الحالة (تأكيد ← تجهيز ← شحن ← توصيل، مع سبب إلزامي للإلغاء والإرجاع).
- الأدوار والصلاحيات، وسجل النشاط.

### البنية

تقع قواعد العمل في طبقة الخدمات (Services)، لا في وحدات التحكم (Controllers) ولا في صفحات Filament:

| الخدمة | المسؤولية |
| --- | --- |
| `App\Services\Inventory` | **المكان الوحيد** الذي يغيّر المخزون: قفل على الصف، ومنع النزول تحت الصفر، وسطر في السجل لكل تغيير |
| `App\Services\Cart\CartService` و `WishlistService` | سلة الزائر والعميل ومفضلتهما، والحدود، والتحقق من الملكية |
| `App\Services\Cart\GuestBasketMerger` | نقل سلة الزائر ومفضلته إلى الحساب عند تسجيل الدخول |
| `App\Services\Cart\CartReview` | ما تغيّر في كل سطر من السلة منذ إضافته |
| `App\Services\Catalog\VariantGenerator` | إنشاء الأنواع من تركيبات الخيارات |
| `App\Services\Checkout\Pricing` | المجموع، والكوبون، والشحن، والضريبة (بالقرش وبأعداد صحيحة) |
| `App\Services\Checkout\PlaceOrder` | تحويل السلة إلى طلب تحت أقفال الصفوف (انظر أدناه) |
| `App\Services\Orders\OrderWorkflow` | الطريقة الوحيدة لتغيير حالة الطلب |

اتفاقيات أخرى:

- وحدات تحكم خفيفة، و Form Requests للتحقق من المدخلات، وسياسات صلاحيات (Policies) لكل نموذج في لوحة التحكم.
- **تُخزَّن المبالغ بالقرش كأعداد صحيحة** (1,250.00 جنيه = `125000`)، دون أي أعداد عشرية.
- سطور الطلب والعنوان والأسماء **نسخ محفوظة**: تعديل المنتج لاحقًا لا يغيّر أي فاتورة سابقة.
- تفعيل `Model::shouldBeStrict()` خارج بيئة الإنتاج: التحميل الكسول للعلاقات والبيانات التي تُتجاهل بصمت يُطلقان خطأً أثناء التطوير.
- استثناءات خاصة بمجال العمل (`CartException` و `CheckoutException` و `InsufficientStockException`…) برسائل مترجمة وآمنة للعرض على العميل.

### سلامة البيانات والتزامن

كل قاعدة أدناه مطبَّقة في الشيفرة ومغطاة بالاختبارات:

| الخطر | الحماية |
| --- | --- |
| شراء عميلين لآخر قطعة | قفل صفوف الأنواع بـ `FOR UPDATE`، مع قيد `CHECK (stock_quantity >= 0)` في قاعدة البيانات كخط دفاع أخير |
| الجمود (Deadlock) بين طلبات متعددة المنتجات | قفل الصفوف دائمًا بترتيب تصاعدي للمعرّف، مع إعادة المعاملة حتى 3 مرات |
| استخدام كوبون "لمرة واحدة" مرتين | قفل صف الكوبون، وعدّ مرات الاستخدام عبر **قراءة مقفلة** (القراءة العادية في مستوى العزل REPEATABLE READ ترى لقطة قديمة؛ وقد كشفت اختبارات السباق هذه المشكلة وتم إصلاحها) |
| النقر المزدوج أو إعادة الإرسال | مفتاح فريد لكل صفحة إتمام طلب؛ وأي تكرار يُعيد الطلب نفسه |
| إتمام الطلب من تبويبين | قفل صف السلة وتفريغها داخل معاملة الطلب نفسها |
| تغيّر الأسعار بعد فتح الصفحة | إعادة حساب الإجمالي من الصفوف المقفلة؛ لا يُحفظ شيء ويُعرض الإجمالي الجديد |
| إلغاء الطلب | إعادة المخزون واستخدام الكوبون تلقائيًا |

### الأمان

- مصادقة عبر Fortify مع **التحقق بخطوتين** و**مفاتيح المرور**، والتحقق بخطوتين إلزامي لفريق العمل لدخول لوحة التحكم.
- أدوار وصلاحيات (spatie/laravel-permission) وسياسات صلاحيات؛ ولا يصل العميل إلا إلى طلباته وعناوينه (ويتلقى غيره استجابة 404).
- حدود لمعدل الطلبات على السلة وإتمام الطلب ومحاولات الكوبونات.
- ملف تعريف الارتباط لسلة الزائر: معرّف UUID عشوائي، مشفَّر، و HttpOnly، و SameSite=Lax، و Secure عند استخدام HTTPS.
- حماية من الإسناد الجماعي (Mass Assignment) (المخزون غير قابل للتعبئة عمدًا)، وحماية CSRF، وتأمين المخرجات، والتحقق من نوع الملفات المرفوعة وحجمها، و**منع رفع ملفات SVG** (لأنها قد تحتوي على شيفرات برمجية).
- إخفاء هوية الحسابات المحذوفة: يبقى السجل حفاظًا على تاريخ الطلبات، وتُحذف البيانات الشخصية، ويمكن استخدام البريد الإلكتروني للتسجيل مجددًا.
- ترويسات أمان في كل استجابة (منع تخمين نوع المحتوى، ومنع تضمين الموقع في إطار، وسياسة صارمة للمُحيل، وسياسة الأذونات)، وفرض HTTPS في الإنتاج مع HSTS وملفات جلسة آمنة.
- لا يُرفع ملف `.env` ولا أي أسرار إلى مستودع git.

### الاختبارات

```bash
php artisan test                    # 157 اختبارًا (SQLite في الذاكرة)
php artisan test tests/Concurrency  # سباقات حقيقية على MySQL/MariaDB (قاعدة بيانات ishop_testing)
```

يجب تشغيل اختبارات التزامن على MariaDB أيضًا قبل الإطلاق، لأنها قاعدة البيانات المستخدمة على الخادم.

### التشغيل محليًا

المتطلبات: PHP 8.3 (مع الامتدادات `intl` و `gd` و `zip` و `pdo_mysql` و `bcmath`)، و Composer، و Node 20 أو أحدث، و MySQL 8.4 أو MariaDB 11.4.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# اضبط DB_CONNECTION و DB_DATABASE في ملف .env، وأنشئ قاعدة البيانات (utf8mb4_unicode_ci)
php artisan migrate --seed                         # الأدوار، والمحافظات، والضريبة، وأول مدير
php artisan storage:link                           # لعرض صور المنتجات المرفوعة
php artisan db:seed --class=DemoCatalogSeeder      # اختياري: منتجات تجريبية بصورها ومناطق شحن
npm run build
```

- المتجر: `/en` أو `/ar` · لوحة التحكم: `/admin`
- محليًا، أول مدير هو `admin@ishop.test` (انظر `AdminUserSeeder`)؛ أما على الخادم فيجب تحديد `ISHOP_ADMIN_EMAIL` و `ISHOP_ADMIN_PASSWORD`.
- تُكتب رسائل البريد الإلكتروني محليًا في `storage/logs/laravel.log`.

### النشر (استضافة مشتركة)

دليل خطوة بخطوة لـ FastComet و cPanel: **[deploy/DEPLOY.md](deploy/DEPLOY.md)**.

- يُنشئ `deploy/build-release.ps1` ملفًا مضغوطًا جاهزًا للرفع من آخر commit (حزم الإنتاج والواجهة مبنيّة، ولا يحتوي أبدًا على `.env` أو السجلات أو الملفات المرفوعة).
- يضم `deploy/env.production.example` كل إعدادات الإنتاج.
- مهمة Cron واحدة (`schedule:run` كل دقيقة) تشغّل الأعمال الخلفية (تجهيز الصور والرسائل) والتنظيف الليلي، دون الحاجة إلى عمليات تعمل باستمرار.

### الخطوات التالية

1. Paymob: إنشاء محاولة الدفع، واستقبال الإشعارات (Webhook) مع التحقق عبر HMAC، وصفحة العودة، وإلغاء الطلبات غير المدفوعة مع إعادة المخزون.
2. شريط إعلانات بعدّاد تنازلي، وتقييمات المنتجات، وصفحات المعلومات (الشحن، والاسترجاع، ومن نحن).
3. تحسين محركات البحث: الوسوم الوصفية، و Open Graph، و JSON-LD، و hreflang، وخريطة الموقع.
4. صفحات أخطاء مخصصة، وترويسات الأمان، وطبقة التخزين المؤقت.
5. النشر، وتشغيل اختبارات التزامن على MariaDB.

</div>

---

## Author · المؤلف

**Muhammad Gamal**

Software Engineer  
Laravel • PHP • E-commerce Development

## License · الترخيص

This project is currently developed as a portfolio and engineering project. Licensing terms will be defined before public distribution.

يُطوَّر هذا المشروع حاليًا بوصفه مشروعًا هندسيًا ضمن معرض الأعمال، وستُحدَّد شروط الترخيص قبل نشره للعامة.
