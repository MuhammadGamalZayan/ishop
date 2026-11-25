<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Services\Catalog\VariantGenerator;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample catalog for local development and screenshots: categories,
 * colour/size options, ~12 products with variants and stock, and a few
 * shipping areas, then the demo photos (DemoImagesSeeder).
 *
 *   php artisan db:seed --class=DemoCatalogSeeder
 *
 * Refuses to run in production unless ISHOP_DEMO_DATA=true (a demo store).
 * Safe to run once; a second run is skipped.
 *
 * Local test accounts: admin@ishop.test (AdminUserSeeder) and
 * customer@ishop.test (registered through the storefront while testing),
 * both with the same local-only password as AdminUserSeeder's fallback.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(VariantGenerator $generator, Inventory $inventory): void
    {
        if (app()->isProduction() && ! config('ishop.demo_data')) {
            throw new RuntimeException('DemoCatalogSeeder is for local development only (set ISHOP_DEMO_DATA=true for a demo store).');
        }

        if (Product::query()->exists()) {
            $this->command?->warn('Products already exist; demo catalog skipped.');

            return;
        }

        $this->shipping();

        $brand = Brand::create(['name' => 'iShop Atelier', 'description' => ['en' => 'Our own leather workshop.', 'ar' => 'ورشة الجلود الخاصة بنا.']]);

        $tree = [
            'women' => [['Women', 'نسائي'], ['shoulder-bags' => ['Shoulder bags', 'شنط كتف'], 'crossbody' => ['Crossbody', 'كروس']]],
            'men' => [['Men', 'رجالي'], ['business-bags' => ['Business bags', 'شنط عمل'], 'weekend-bags' => ['Weekend bags', 'شنط سفر']]],
            'accessories' => [['Accessories', 'إكسسوارات'], ['wallets' => ['Wallets', 'محافظ'], 'card-holders' => ['Card holders', 'حافظات كروت']]],
        ];

        $categories = [];
        $order = 0;
        foreach ($tree as $slug => [$names, $children]) {
            $parent = Category::create(['name' => ['en' => $names[0], 'ar' => $names[1]], 'slug' => $slug, 'sort_order' => $order++, 'description' => [
                'en' => "{$names[0]}'s bags and accessories in full-grain leather, made to last.",
                'ar' => 'شنط وإكسسوارات من الجلد الطبيعي الكامل، مصنوعة لتدوم.',
            ]]);
            $categories[$slug] = $parent;
            $childOrder = 0;
            foreach ($children as $childSlug => $childNames) {
                $categories[$childSlug] = Category::create(['parent_id' => $parent->id, 'name' => ['en' => $childNames[0], 'ar' => $childNames[1]], 'slug' => $childSlug, 'sort_order' => $childOrder++]);
            }
        }

        $colour = ProductAttribute::create(['name' => ['en' => 'Colour', 'ar' => 'اللون'], 'type' => AttributeType::Color, 'sort_order' => 0]);
        $size = ProductAttribute::create(['name' => ['en' => 'Size', 'ar' => 'المقاس'], 'type' => AttributeType::Select, 'sort_order' => 1]);

        $colours = [];
        foreach ([['Black', 'أسود', '#1c1c1c'], ['Navy', 'كحلي', '#1d2a44'], ['Brown', 'بني', '#5a3424'], ['Khaki', 'كاكي', '#55553a'], ['Tan', 'جملي', '#a0673c'], ['Ivory', 'عاجي', '#e7e0d2'], ['Petrol', 'بترولي', '#1f4848']] as $i => [$en, $ar, $hex]) {
            $colours[$en] = $colour->values()->create(['value' => ['en' => $en, 'ar' => $ar], 'color_hex' => $hex, 'sort_order' => $i]);
        }
        $sizes = [];
        foreach ([['Small', 'صغير'], ['Medium', 'وسط'], ['Cabin', 'كابينة'], ['Large', 'كبير']] as $i => [$en, $ar]) {
            $sizes[$en] = $size->values()->create(['value' => ['en' => $en, 'ar' => $ar], 'sort_order' => $i]);
        }

        // name en, name ar, price EGP, categories, colours, sizes, featured
        $products = [
            ['Crescent Shoulder Bag', 'شنطة كتف هلال', 8200, ['women', 'shoulder-bags'], ['Petrol', 'Black'], [], true],
            ['Structured Top Handle', 'شنطة يد كلاسيك', 8900, ['women', 'shoulder-bags'], ['Brown', 'Black'], [], false],
            ['Saddle Crossbody', 'شنطة كروس سادل', 9600, ['women', 'crossbody'], ['Ivory', 'Tan'], ['Small', 'Medium'], true],
            ['Mini Clutch', 'ميني كلاتش', 6800, ['women', 'crossbody'], ['Black'], [], false],
            ['Slim Briefcase', 'شنطة أوراق سليم', 9900, ['men', 'business-bags'], ['Black', 'Navy'], [], true],
            ['Document Portfolio', 'حافظة مستندات', 7500, ['men', 'business-bags'], [], [], false],
            ['Work Tote', 'شنطة شغل', 9900, ['men', 'business-bags'], ['Khaki', 'Brown'], [], false],
            ['Compact Weekender', 'شنطة ويك إند', 11500, ['men', 'weekend-bags'], ['Black', 'Navy', 'Brown', 'Khaki'], ['Cabin', 'Large'], true],
            ['Duffle Weekender', 'شنطة سفر دافل', 12800, ['men', 'weekend-bags'], ['Brown', 'Black'], ['Cabin', 'Large'], false],
            ['Bifold Wallet', 'محفظة بطيّتين', 2400, ['accessories', 'wallets'], ['Black', 'Brown', 'Tan'], [], false],
            ['Card Holder', 'حافظة كروت', 1800, ['accessories', 'card-holders'], ['Navy', 'Black'], [], false],
            ['Coin Pouch', 'محفظة عملات', 1200, ['accessories', 'wallets'], [], [], false],
        ];

        foreach ($products as $i => [$en, $ar, $price, $cats, $productColours, $productSizes, $featured]) {
            $product = Product::create([
                'brand_id' => $brand->id,
                'name' => ['en' => $en, 'ar' => $ar],
                'short_description' => ['en' => 'Full-grain leather, made in Egypt.', 'ar' => 'جلد طبيعي كامل، صُنع في مصر.'],
                'description' => [
                    'en' => "The {$en} is cut from full-grain leather and finished by hand. Cotton lining, metal hardware, and a shape that softens with use.",
                    'ar' => "{$ar} مقصوصة من الجلد الطبيعي الكامل ومشطّبة يدويًا. بطانة قطنية، وإكسسوارات معدنية، وشكل يلين مع الاستخدام.",
                ],
                'status' => ProductStatus::Active,
                'published_at' => now()->subDays($i * 4),
                'is_featured' => $featured,
            ]);
            $product->categories()->attach(array_map(fn ($slug) => $categories[$slug]->id, $cats));

            if ($productColours === [] && $productSizes === []) {
                $variant = $product->variants()->create(['sku' => strtoupper(str_replace(' ', '-', $en)), 'price' => $price * 100, 'is_default' => true]);
                $inventory->adjust($variant, 8, StockMovementType::Restock, note: 'Demo stock');

                continue;
            }

            $options = [];
            if ($productColours) {
                $options[$colour->id] = array_map(fn ($c) => $colours[$c]->id, $productColours);
            }
            if ($productSizes) {
                $options[$size->id] = array_map(fn ($s) => $sizes[$s]->id, $productSizes);
            }

            foreach ($generator->generate($product, $options, $price * 100) as $n => $variant) {
                // Mostly in stock, a few low, one sold out: so badges and warnings show.
                $stock = [6, 2, 9, 0, 4, 1, 7, 3][($i + $n) % 8];
                if ($stock > 0) {
                    $inventory->adjust($variant, $stock, StockMovementType::Restock, note: 'Demo stock');
                }
            }
        }

        // Suggestions: wallets go with briefcases.
        Product::query()->where('slug', 'slim-briefcase')->first()?->related()->attach(Product::query()->whereIn('slug', ['card-holder', 'bifold-wallet'])->pluck('id'));
        Product::query()->where('slug', 'compact-weekender')->first()?->related()->attach(Product::query()->whereIn('slug', ['card-holder'])->pluck('id'));

        $this->command?->info('Demo catalog created: '.Product::count().' products.');

        // Clothing, shoes and the mega menu, then photos for everything.
        $this->call(DemoFashionSeeder::class);
        $this->call(DemoImagesSeeder::class);
        $this->call(DemoHomeSeeder::class);
    }

    private function shipping(): void
    {
        $areas = [
            'Cairo' => [['Nasr City', 'مدينة نصر', 6000], ['Maadi', 'المعادي', 6000], ['Downtown', 'وسط البلد', 5000], ['New Cairo', 'القاهرة الجديدة', 7000]],
            'Giza' => [['Dokki', 'الدقي', 6000], ['6th of October', '6 أكتوبر', 8000]],
            'Alexandria' => [['Smouha', 'سموحة', 7000], ['Miami', 'ميامي', 7000], ['Sidi Gaber', 'سيدي جابر', 7000]],
        ];

        foreach ($areas as $governorate => $list) {
            $g = Governorate::query()->where('name->en', $governorate)->first();
            if (! $g || $g->shippingAreas()->exists()) {
                continue;
            }
            foreach ($list as $i => [$en, $ar, $price]) {
                $g->shippingAreas()->create(['name' => ['en' => $en, 'ar' => $ar], 'shipping_price' => $price, 'sort_order' => $i]);
            }
        }
    }
}
