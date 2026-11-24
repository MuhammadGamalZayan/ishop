# Deploying iShop to FastComet (shared cPanel hosting)

[English](#english) · [العربية](#العربية)

---

## English

The store runs on a **subdomain** (e.g. `shop.example.com`) whose document root points at the project's `public` folder, so the application code is never reachable from the web. You upload one zip; the server needs SSH / Terminal only for a few `php artisan` commands. Payments at launch: **Cash on Delivery** (Paymob comes in a later release).

Throughout, replace `cpaneluser` with your cPanel username and `shop.example.com` with your subdomain.

### 1. Build the release (on your PC)

Commit your work first, then in the project folder:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
```

This creates `releases\ishop-<commit>.zip`: production PHP packages, built frontend, admin assets. It never contains `.env`, logs, uploads or development files.

### 2. Prepare cPanel (once)

1. **PHP:** *Select PHP Version* → **8.3**. Turn on these extensions: `bcmath`, `ctype`, `curl`, `dom`, `exif`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `sodium`, `tokenizer`, `xml`, `zip`. Under *Options*: `memory_limit` 256M, `upload_max_filesize` 10M, `post_max_size` 12M.
2. **Database:** *MySQL Databases* → create a database (e.g. `cpaneluser_ishop`), create a user with a strong password, and add the user to the database with **All Privileges**.
3. **Email:** *Email Accounts* → create `no-reply@example.com` (used for password resets and verification emails).
4. **Subdomain:** *Domains* → *Create A New Domain* → `shop.example.com`, untick "Share document root", set the document root to **`ishop/public`**.
5. **SSL:** *SSL/TLS Status* → *Run AutoSSL* so `https://shop.example.com` has a certificate.

### 3. Upload and install (first time)

1. *File Manager* → your home folder (`/home/cpaneluser`) → create the folder **`ishop`** → upload the zip into it → *Extract* → delete the zip.
2. Open *Terminal* (or SSH) and run:

```bash
cd ~/ishop
php -v                                   # must say 8.3; see Troubleshooting if not
cp deploy/env.production.example .env
```

3. Edit `~/ishop/.env` (File Manager → Edit) and fill in every value marked **CHANGE**: `APP_URL`, the `DB_*` lines, the `MAIL_*` lines, `ISHOP_ADMIN_EMAIL` and `ISHOP_ADMIN_PASSWORD`.
4. Back in Terminal:

```bash
php artisan key:generate --force         # creates APP_KEY (back up .env afterwards)
php artisan migrate --force              # creates the tables
php artisan db:seed --force              # roles, governorates, VAT, first admin
php artisan storage:link                 # makes product photos reachable
php artisan optimize                     # caches config, routes, views
php artisan filament:optimize            # caches admin panel components
chmod -R 775 storage bootstrap/cache
```

5. Remove `ISHOP_ADMIN_PASSWORD` from `.env` once you have signed in, then run `php artisan optimize` again.

### 4. Cron job (once)

*Cron Jobs* → *Add New Cron Job* → Common Settings: **Once Per Minute**, command:

```bash
cd /home/cpaneluser/ishop && php artisan schedule:run >> /dev/null 2>&1
```

Every minute this processes background work (product photo conversions, emails) and, nightly, deletes abandoned guest carts. Without it, newly uploaded photos never appear.

### 5. Check the launch

- Open `https://shop.example.com`. `http://` must redirect to `https://`.
- `https://shop.example.com/admin` → sign in with the admin email → set up two-factor authentication (required).
- In the admin panel add: **shipping areas and prices**, categories, products with photos and stock.
- After uploading a photo, wait about a minute (cron) and check that it shows on the store.
- Place a test Cash-on-Delivery order from a customer account, then cancel it from the admin panel.
- Test "Forgot password" to confirm emails are sent.

### Updating the store later

```bash
cd ~/ishop && php artisan down           # visitors see the maintenance page
```

Upload the new zip into `~/ishop`, extract it and **overwrite** existing files (your `.env`, uploads and logs are not in the zip and stay as they are). Then:

```bash
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
php artisan up
```

Files deleted from the project are not removed by extracting over the old version; that is harmless.

### Backups

FastComet keeps daily backups (cPanel → *JetBackup*). Before each update also download a copy of `.env` and a database export (*phpMyAdmin* → *Export*). Keep `.env` safe: without its `APP_KEY`, encrypted data (sessions, two-factor secrets) can't be read.

### Troubleshooting

| Problem | Fix |
|---|---|
| "500 Server Error" | Read the newest file in `~/ishop/storage/logs/`. Most often a wrong `DB_*` value or a missing PHP extension. |
| Changes to `.env` have no effect | Run `php artisan optimize` (the config is cached). |
| Endless redirect | The site is behind a proxy that talks HTTP to the server (e.g. Cloudflare "Flexible"). Use "Full" SSL in Cloudflare, or set `FORCE_HTTPS=false`. |
| Product photos missing / 403 | Run `php artisan storage:link`; check the cron job. |
| `php -v` is not 8.3 in Terminal | Use the full path, e.g. `/opt/alt/php83/usr/bin/php artisan …`, in the commands and in the cron job. |
| Emails not arriving | Check the `MAIL_*` values (host is usually `mail.your-domain`, port 465, scheme `smtps`), then `php artisan optimize`. |

---

## العربية

يعمل المتجر على **نطاق فرعي** (مثل `shop.example.com`) يشير مجلده الجذري إلى مجلد `public` في المشروع، فلا يمكن الوصول إلى شيفرة التطبيق من الإنترنت. ترفع ملفًا مضغوطًا واحدًا، ولا يحتاج الخادم إلى Terminal إلا لبضعة أوامر `php artisan`. وسيلة الدفع عند الإطلاق: **الدفع عند الاستلام**، ويُضاف Paymob في إصدار لاحق.

استبدل في كل الخطوات `cpaneluser` باسم مستخدم cPanel، و`shop.example.com` بنطاقك الفرعي.

### 1. تجهيز الإصدار (على جهازك)

احفظ عملك في commit أولًا، ثم نفّذ في مجلد المشروع:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-release.ps1
```

يُنشئ ذلك الملف `releases\ishop-<commit>.zip`، وفيه حزم PHP الخاصة بالإنتاج والواجهة مبنيّة وملفات لوحة التحكم. ولا يحتوي أبدًا على `.env` أو السجلات أو الملفات المرفوعة أو ملفات التطوير.

### 2. إعداد cPanel (مرة واحدة)

1. **PHP:** من *Select PHP Version* اختر **8.3**، وفعّل الإضافات: `bcmath` و`ctype` و`curl` و`dom` و`exif` و`fileinfo` و`gd` و`intl` و`mbstring` و`openssl` و`pdo_mysql` و`sodium` و`tokenizer` و`xml` و`zip`. ومن *Options* اضبط: `memory_limit` على 256M، و`upload_max_filesize` على 10M، و`post_max_size` على 12M.
2. **قاعدة البيانات:** من *MySQL Databases* أنشئ قاعدة بيانات (مثل `cpaneluser_ishop`)، وأنشئ مستخدمًا بكلمة مرور قوية، ثم أضفه إلى القاعدة بصلاحيات **All Privileges**.
3. **البريد:** من *Email Accounts* أنشئ `no-reply@example.com` (لرسائل استعادة كلمة المرور وتأكيد البريد).
4. **النطاق الفرعي:** من *Domains* ثم *Create A New Domain* أضف `shop.example.com`، وألغِ خيار "Share document root"، واجعل المجلد الجذري **`ishop/public`**.
5. **شهادة SSL:** من *SSL/TLS Status* اضغط *Run AutoSSL* ليعمل `https://shop.example.com` بشهادة.

### 3. الرفع والتثبيت (أول مرة)

1. من *File Manager* افتح مجلدك الرئيسي (`/home/cpaneluser`)، وأنشئ المجلد **`ishop`**، وارفع الملف المضغوط داخله، ثم *Extract*، ثم احذف الملف المضغوط.
2. افتح *Terminal* (أو SSH) ونفّذ:

```bash
cd ~/ishop
php -v                                   # يجب أن يظهر 8.3، وإلا فراجع قسم حل المشكلات
cp deploy/env.production.example .env
```

3. عدّل الملف `~/ishop/.env` (من File Manager ثم Edit)، واملأ كل قيمة مكتوب بجانبها **CHANGE**: `APP_URL` وأسطر `DB_*` وأسطر `MAIL_*` و`ISHOP_ADMIN_EMAIL` و`ISHOP_ADMIN_PASSWORD`.
4. ثم في Terminal:

```bash
php artisan key:generate --force         # ينشئ APP_KEY (احتفظ بنسخة من .env بعدها)
php artisan migrate --force              # ينشئ الجداول
php artisan db:seed --force              # الأدوار، والمحافظات، والضريبة، وأول مدير
php artisan storage:link                 # لإتاحة صور المنتجات
php artisan optimize                     # تخزين مؤقت للإعدادات والمسارات والقوالب
php artisan filament:optimize            # تخزين مؤقت لمكوّنات لوحة التحكم
chmod -R 775 storage bootstrap/cache
```

5. بعد أول تسجيل دخول احذف `ISHOP_ADMIN_PASSWORD` من `.env`، ثم نفّذ `php artisan optimize` مرة أخرى.

### 4. مهمة Cron (مرة واحدة)

من *Cron Jobs* ثم *Add New Cron Job* اختر **Once Per Minute**، والأمر:

```bash
cd /home/cpaneluser/ishop && php artisan schedule:run >> /dev/null 2>&1
```

تعالج هذه المهمة كل دقيقة الأعمال الخلفية (تجهيز صور المنتجات والرسائل)، وتحذف ليلًا سلال الزوار المهجورة. ومن دونها لن تظهر الصور الجديدة أبدًا.

### 5. التحقق بعد الإطلاق

- افتح `https://shop.example.com`، ويجب أن يحوّل `http://` إلى `https://`.
- ادخل إلى `https://shop.example.com/admin` ببريد المدير، وفعّل المصادقة الثنائية (إلزامية).
- أضف من لوحة التحكم: **مناطق الشحن وأسعارها**، والتصنيفات، والمنتجات بصورها ومخزونها.
- بعد رفع صورة انتظر نحو دقيقة (مهمة Cron)، وتأكد من ظهورها في المتجر.
- اطلب طلبًا تجريبيًا بالدفع عند الاستلام من حساب عميل، ثم ألغِه من لوحة التحكم.
- جرّب "نسيت كلمة المرور" للتأكد من وصول الرسائل.

### تحديث المتجر لاحقًا

```bash
cd ~/ishop && php artisan down           # يرى الزوار صفحة الصيانة
```

ارفع الملف المضغوط الجديد إلى `~/ishop` وفك ضغطه مع **استبدال** الملفات الموجودة (لا يحتوي الملف على `.env` ولا على الملفات المرفوعة ولا على السجلات، فتبقى كما هي). ثم:

```bash
php artisan migrate --force
php artisan optimize
php artisan filament:optimize
php artisan up
```

الملفات المحذوفة من المشروع لا تُحذف من الخادم عند فك الضغط فوق النسخة القديمة، ولا ضرر من ذلك.

### النسخ الاحتياطي

تحتفظ FastComet بنسخ احتياطية يومية (cPanel ثم *JetBackup*). وقبل كل تحديث نزّل أيضًا نسخة من `.env` وتصديرًا لقاعدة البيانات (*phpMyAdmin* ثم *Export*). واحفظ `.env` في مكان آمن، فمن دون `APP_KEY` الموجود فيه لا يمكن قراءة البيانات المشفّرة (الجلسات وأسرار المصادقة الثنائية).

### حل المشكلات

| المشكلة | الحل |
|---|---|
| "500 Server Error" | اقرأ أحدث ملف في `~/ishop/storage/logs/`. والسبب غالبًا قيمة خاطئة في `DB_*` أو إضافة PHP غير مفعّلة. |
| تعديلات `.env` لا تظهر | نفّذ `php artisan optimize` (الإعدادات مخزّنة مؤقتًا). |
| تحويل لا ينتهي | الموقع خلف وسيط يتصل بالخادم عبر HTTP (مثل Cloudflare بوضع "Flexible"). استخدم وضع "Full" في Cloudflare، أو اضبط `FORCE_HTTPS=false`. |
| صور المنتجات لا تظهر أو 403 | نفّذ `php artisan storage:link`، وتحقق من مهمة Cron. |
| `php -v` لا يعرض 8.3 في Terminal | استخدم المسار الكامل، مثل `/opt/alt/php83/usr/bin/php artisan …`، في الأوامر وفي مهمة Cron. |
| الرسائل لا تصل | راجع قيم `MAIL_*` (الخادم غالبًا `mail.نطاقك` والمنفذ 465 والنوع `smtps`)، ثم نفّذ `php artisan optimize`. |
