<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fills the home page, the newsletter band, the About menu and its pages
 * with the demo media in database/seeders/demo-home (see CREDITS.md).
 * Everything here is editable afterwards in the admin panel.
 *
 * Runs after DemoFashionSeeder (it points at its products). Settings that
 * already exist are left alone, so it never overwrites the admin's edits.
 *
 *   php artisan db:seed --class=DemoHomeSeeder
 */
class DemoHomeSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! config('ishop.demo_data')) {
            throw new RuntimeException('DemoHomeSeeder is for local development only (set ISHOP_DEMO_DATA=true for a demo store).');
        }

        $this->pages();

        if (Setting::query()->where('key', 'home')->doesntExist()) {
            Setting::put('home', $this->home());
        }

        if (Setting::query()->where('key', 'newsletter')->doesntExist()) {
            Setting::put('newsletter', [
                'image' => $this->media('newsletter.jpg'),
                'kicker' => ['en' => 'Keep me updated', 'ar' => 'ابقَ على اطلاع'],
                'title' => ['en' => 'Newsletter', 'ar' => 'النشرة البريدية'],
                'text' => ['en' => 'Subscribe to hear about new collections, special offers and store news.', 'ar' => 'اشترك لتصلك أخبار التشكيلات الجديدة والعروض الخاصة وأخبار المتجر.'],
            ]);
        }

        if (Setting::query()->where('key', 'about_menu')->doesntExist()) {
            Setting::put('about_menu', ['cards' => [
                ['image' => $this->media('menu-about-1.jpg', 'menu'), 'title' => ['en' => 'Our story', 'ar' => 'قصتنا'], 'subtitle' => ['en' => 'Made by hand', 'ar' => 'صُنع يدويًا'], 'link' => 'pages/our-story'],
                ['image' => $this->media('menu-about-2.jpg', 'menu'), 'title' => ['en' => 'Materials & care', 'ar' => 'الخامات والعناية'], 'subtitle' => ['en' => 'Leather that lasts', 'ar' => 'جلد يدوم'], 'link' => 'pages/materials-care'],
            ]]);
        }

        $this->command?->info('Demo home page ready.');
    }

    /** @return array<string, mixed> */
    private function home(): array
    {
        $id = fn (string $slug): ?int => Product::query()->where('slug', $slug)->value('id');
        $t = fn (string $en, string $ar): array => ['en' => $en, 'ar' => $ar];

        return [
            'hero' => [
                ['type' => 'image', 'image' => $this->media('hero-1.jpg'), 'kicker' => $t('The leather edit', 'تشكيلة الجلد'), 'title' => $t('Made to be carried', 'صُنعت لترافقك'), 'button' => $t('Shop now', 'تسوّق الآن'), 'link' => 'women'],
                ['type' => 'video', 'video' => $this->media('hero-video.mp4'), 'image' => $this->media('hero-video-poster.jpg'), 'kicker' => $t('New season', 'موسم جديد'), 'title' => $t('Dressed for the city', 'أناقة المدينة'), 'button' => $t('Discover', 'اكتشف'), 'link' => 'new-arrivals'],
                ['type' => 'image', 'image' => $this->media('hero-3.jpg'), 'kicker' => $t('Autumn layers', 'طبقات الخريف'), 'title' => $t('The camel coat', 'المعطف الكاميل'), 'button' => $t('Shop coats', 'تسوّق المعاطف'), 'link' => 'women-coats'],
            ],
            'best_sellers' => ['kicker' => $t('Our best sellers', 'الأكثر مبيعًا'), 'category_ids' => collect(['women', 'men', 'accessories'])->map(fn (string $slug) => Category::query()->where('slug', $slug)->value('id'))->filter()->values()->all()],
            'new_in' => ['category' => 'new-arrivals', 'kicker' => $t('Just landed', 'وصل للتو'), 'title' => $t('New in', 'وصل حديثًا'), 'button' => $t('Shop new arrivals', 'تسوّق الوافد الجديد')],
            'trio' => [
                ['image' => $this->media('trio-women.jpg'), 'category' => 'women'],
                ['image' => $this->media('trio-men.jpg'), 'category' => 'men'],
                ['image' => $this->media('trio-accessories.jpg'), 'category' => 'accessories'],
            ],
            'marquee_top' => [
                ['text' => $t('New in: evening dresses', 'جديد: فساتين السهرة'), 'link' => 'dresses', 'product_id' => $id('midnight-slip-dress')],
                ['text' => $t('Pay on delivery anywhere in Egypt', 'ادفع عند الاستلام في كل مصر'), 'link' => 'pages/shipping-returns', 'product_id' => $id('saddle-crossbody')],
                ['text' => $t('Handmade leather loafers', 'لوفر جلد مصنوع يدويًا'), 'link' => 'loafers', 'product_id' => $id('horsebit-loafers')],
            ],
            'marquee_bottom' => [
                ['text' => $t('Shop new arrivals', 'تسوّق الوافد الجديد'), 'link' => 'new-arrivals', 'product_id' => $id('stiletto-ankle-boots')],
                ['text' => $t('Leather that lasts', 'جلد يدوم'), 'link' => 'pages/materials-care', 'product_id' => $id('crescent-shoulder-bag')],
                ['text' => $t('Gift-ready wallets', 'محافظ جاهزة للإهداء'), 'link' => 'accessories', 'product_id' => $id('bifold-wallet')],
            ],
            'video' => ['video' => $this->media('craft-video.mp4'), 'poster' => $this->media('craft-video-poster.jpg'), 'kicker' => $t('Made in Egypt', 'صُنع في مصر'), 'title' => $t('Finest handmade', 'أجود الصناعة اليدوية')],
            'looks' => [
                ['image' => $this->media('look-1.jpg'), 'hotspots' => [
                    ['product_id' => $id('navy-linen-tee'), 'x' => 42, 'y' => 25],
                    ['product_id' => $id('linen-day-dress'), 'x' => 61, 'y' => 45],
                ]],
                ['image' => $this->media('look-2.jpg'), 'hotspots' => [
                    ['product_id' => $id('indigo-denim-shirt'), 'x' => 52, 'y' => 64],
                    ['product_id' => $id('silk-tie-blouse'), 'x' => 70, 'y' => 52],
                ]],
            ],
            'sale' => [
                'enabled' => true,
                'image' => $this->media('sale.jpg'),
                'kicker' => $t('Limited time only', 'لفترة محدودة'),
                'title' => $t('Super sale up to 40% off', 'تخفيضات كبرى حتى 40%'),
                'text' => $t('Shop our selection of bags, shoes and clothing at reduced prices during the Super Sale. Hurry up!', 'تسوّق تشكيلتنا من الشنط والأحذية والملابس بأسعار مخفّضة خلال التخفيضات الكبرى. أسرع!'),
                'button' => $t('Shop now', 'تسوّق الآن'),
                'link' => 'women',
                'ends_at' => now()->addDays(14)->addHours(5)->startOfHour()->toDateTimeString(),
            ],
            'compare' => [
                'title' => $t('Choose your colour', 'اختر لونك'),
                'left' => ['image' => $this->productPhoto('saddle-crossbody'), 'label' => $t('Saddle Crossbody', 'شنطة كروس سادل'), 'link' => 'products/saddle-crossbody'],
                'right' => ['image' => $this->productPhoto('structured-top-handle'), 'label' => $t('Structured Top Handle', 'شنطة يد كلاسيك'), 'link' => 'products/structured-top-handle'],
            ],
        ];
    }

    /** Copies a demo file to the public disk once; returns its stored path. */
    private function media(string $file, string $directory = 'home'): string
    {
        $path = "{$directory}/{$file}";
        $source = database_path("seeders/demo-home/{$file}");

        if (! app()->runningUnitTests() && is_file($source) && ! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, (string) file_get_contents($source));
        }

        return $path;
    }

    /** A demo product photo (demo-images/{slug}-1.jpg) copied for the compare slider. */
    private function productPhoto(string $slug): string
    {
        $path = "home/compare-{$slug}.jpg";
        $source = database_path("seeders/demo-images/{$slug}-1.jpg");

        if (! app()->runningUnitTests() && is_file($source) && ! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, (string) file_get_contents($source));
        }

        return $path;
    }

    private function pages(): void
    {
        $pages = [
            ['our-story', 'discover', 'default', ['Our story', 'قصتنا'], [
                '<p>iShop started as a small leather workshop in Alexandria. Today we design clothing, shoes and bags for everyday life, and every bag is still cut and stitched by hand.</p><p>We make fewer things, better: honest materials, careful finishing and prices that stay fair.</p>',
                '<p>بدأ iShop ورشةً صغيرة للجلود في الإسكندرية. واليوم نصمّم الملابس والأحذية والشنط للحياة اليومية، وما زالت كل شنطة تُقص وتُخاط يدويًا.</p><p>نصنع أشياء أقل ولكن أفضل: خامات صادقة، وتشطيب دقيق، وأسعار عادلة.</p>',
            ]],
            ['commitments', 'discover', 'default', ['Commitments', 'التزاماتنا'], [
                '<p>We work with local tanneries and workshops, pay fair wages and use recycled packaging. We repair what we sell for the first year.</p>',
                '<p>نتعامل مع مدابغ وورش محلية، وندفع أجورًا عادلة، ونستخدم تغليفًا معاد التدوير، ونصلح ما نبيعه مجانًا خلال السنة الأولى.</p>',
            ]],
            ['materials-care', 'discover', 'default', ['Materials & care', 'الخامات والعناية'], [
                '<h2>Leather</h2><p>Full-grain leather darkens and softens with use. Wipe with a dry cloth, keep away from heat and use a leather balm twice a year.</p><h2>Clothing</h2><p>Wash cold, inside out, and dry flat. Iron linen while it is still slightly damp.</p>',
                '<h2>الجلد</h2><p>يغمق الجلد الطبيعي ويلين مع الاستخدام. امسحه بقطعة قماش جافة، وأبعده عن الحرارة، واستخدم مرطّب الجلد مرتين في السنة.</p><h2>الملابس</h2><p>اغسلها بالماء البارد ومقلوبة، وجففها مفرودة، واكوِ الكتان وهو رطب قليلًا.</p>',
            ]],
            ['faq', 'help', 'default', ['FAQ', 'الأسئلة الشائعة'], [
                '<h2>How long does delivery take?</h2><p>2–4 working days in Cairo and Alexandria, 3–6 days elsewhere.</p><h2>Can I pay on delivery?</h2><p>Yes, anywhere we deliver. You can also pay by card.</p><h2>Can I return an item?</h2><p>Yes, within 14 days if it is unused and in its packaging.</p>',
                '<h2>كم يستغرق التوصيل؟</h2><p>من 2 إلى 4 أيام عمل في القاهرة والإسكندرية، ومن 3 إلى 6 أيام في باقي المحافظات.</p><h2>هل يمكنني الدفع عند الاستلام؟</h2><p>نعم، في كل المناطق التي نوصّل إليها، ويمكنك الدفع بالبطاقة أيضًا.</p><h2>هل يمكنني إرجاع منتج؟</h2><p>نعم، خلال 14 يومًا إذا كان غير مستخدم وفي تغليفه.</p>',
            ]],
            ['shipping-returns', 'help', 'default', ['Shipping & returns', 'الشحن والإرجاع'], [
                '<p>Shipping prices depend on your area and are shown at checkout. Free returns within 14 days: contact us and a courier collects the item.</p>',
                '<p>تختلف تكلفة الشحن حسب منطقتك وتظهر عند إتمام الطلب. الإرجاع مجاني خلال 14 يومًا: تواصل معنا وسيستلم المندوب المنتج.</p>',
            ]],
            ['contact', 'help', 'contact', ['Contact us', 'تواصل معنا'], [
                '<p>Questions about an order, a size or a product? Send us a message and we reply within one working day.</p>',
                '<p>لديك سؤال عن طلب أو مقاس أو منتج؟ أرسل لنا رسالة وسنرد خلال يوم عمل واحد.</p>',
            ]],
        ];

        foreach ($pages as $i => [$slug, $group, $template, $title, $body]) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => ['en' => $title[0], 'ar' => $title[1]],
                'body' => ['en' => $body[0], 'ar' => $body[1]],
                'menu_group' => $group,
                'template' => $template,
                'is_published' => true,
                'sort_order' => $i,
            ]);
        }
    }
}
