<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Services\Catalog\VariantGenerator;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns the demo bag shop into a small fashion store for the mega menu:
 *
 *   Women → Clothing (Dresses, Shirts & Blouses, Coats & Jackets)
 *           Shoes (Heels, Boots) · Bags (the existing bag categories)
 *   Men   → Clothing (Shirts, T-shirts, Coats & Jackets)
 *           Shoes (Loafers, Derby shoes) · Bags (existing)
 *   New arrivals (not a header item: linked from the menu and the home page)
 *
 * plus 13 clothing / shoe products with sizes, colours and stock, the
 * menu's popular colours and picture cards. Safe to run on an existing
 * demo database: everything is found by slug first, nothing is duplicated.
 *
 *   php artisan db:seed --class=DemoFashionSeeder
 */
class DemoFashionSeeder extends Seeder
{
    /** @var array<string, Category> */
    private array $cat = [];

    public function run(VariantGenerator $generator, Inventory $inventory): void
    {
        if (app()->isProduction() && ! config('ishop.demo_data')) {
            throw new RuntimeException('DemoFashionSeeder is for local development only (set ISHOP_DEMO_DATA=true for a demo store).');
        }

        $women = $this->category('women', ['Women', 'نسائي']);
        $men = $this->category('men', ['Men', 'رجالي']);
        $accessories = $this->category('accessories', ['Accessories', 'إكسسوارات']);

        // Women
        $this->group($women, 'women-clothing', ['Clothing', 'ملابس'], 0, [
            'dresses' => ['Dresses', 'فساتين'],
            'women-shirts' => ['Shirts & blouses', 'قمصان وبلوزات'],
            'women-coats' => ['Coats & jackets', 'معاطف وجواكت'],
        ]);
        $this->group($women, 'women-shoes', ['Shoes', 'أحذية'], 1, [
            'heels' => ['Heels', 'كعب عالي'],
            'boots' => ['Boots', 'بوت'],
        ]);
        $this->group($women, 'women-bags', ['Bags', 'شنط'], 2, [
            'shoulder-bags' => ['Shoulder bags', 'شنط كتف'],
            'crossbody' => ['Crossbody', 'كروس'],
        ]);

        // Men
        $this->group($men, 'men-clothing', ['Clothing', 'ملابس'], 0, [
            'men-shirts' => ['Shirts', 'قمصان'],
            't-shirts' => ['T-shirts', 'تيشيرتات'],
            'men-coats' => ['Coats & jackets', 'معاطف وجواكت'],
        ]);
        $this->group($men, 'men-shoes', ['Shoes', 'أحذية'], 1, [
            'loafers' => ['Loafers', 'لوفر'],
            'derby-shoes' => ['Derby shoes', 'أحذية ديربي'],
        ]);
        $this->group($men, 'men-bags', ['Bags', 'شنط'], 2, [
            'business-bags' => ['Business bags', 'شنط عمل'],
            'weekend-bags' => ['Weekend bags', 'شنط سفر'],
        ]);

        // Header shows Women, Men (and About). Accessories and New arrivals are
        // reached from the menu links and the home page.
        $accessories->forceFill(['show_in_menu' => false])->save();
        $newIn = $this->category('new-arrivals', ['New arrivals', 'وصل حديثًا'], sort: 9);
        $newIn->forceFill(['show_in_menu' => false])->save();

        [$colour, $size, $shoeSize] = $this->attributes();
        $colours = $this->values($colour, [
            'Black' => ['أسود', '#1c1c1c'], 'Navy' => ['كحلي', '#1d2a44'], 'Brown' => ['بني', '#5a3424'], 'Tan' => ['جملي', '#a0673c'],
            'Ivory' => ['عاجي', '#e7e0d2'], 'Camel' => ['كاميل', '#b0794a'], 'White' => ['أبيض', '#f5f4f0'], 'Red' => ['أحمر', '#a3242a'],
            'Almond green' => ['أخضر لوزي', '#8a9a7b'], 'Indigo' => ['نيلي', '#2f3e63'],
        ]);
        $sizes = $this->values($size, ['XS' => ['XS', null], 'S' => ['S', null], 'M' => ['M', null], 'L' => ['L', null], 'XL' => ['XL', null]]);
        $shoeSizes = $this->values($shoeSize, array_combine(
            array_map('strval', range(37, 45)),
            array_map(fn (int $n): array => [(string) $n, null], range(37, 45)),
        ));

        $brand = Brand::query()->first() ?? Brand::create(['name' => 'iShop Atelier']);

        // slug, en, ar, price EGP, leaf category, top, colours, sizes (clothing|shoe|none), new arrival?
        $products = [
            ['midnight-slip-dress', 'Midnight Slip Dress', 'فستان سهرة ميدنايت', 2450, 'dresses', 'women', ['Black'], 'clothing', true],
            ['linen-day-dress', 'Linen Day Dress', 'فستان كتان نهاري', 2250, 'dresses', 'women', ['White', 'Ivory'], 'clothing', false],
            ['silk-tie-blouse', 'Silk Tie Blouse', 'بلوزة حرير بربطة', 1950, 'women-shirts', 'women', ['White'], 'clothing', true],
            ['crimson-v-neck-top', 'Crimson V-Neck Top', 'توب أحمر بفتحة V', 1350, 'women-shirts', 'women', ['Red'], 'clothing', true],
            ['camel-trench-coat', 'Camel Trench Coat', 'ترنش كوت كاميل', 6900, 'women-coats', 'women', ['Camel'], 'clothing', false],
            ['ivory-pointed-heels', 'Ivory Pointed Heels', 'كعب عاجي مدبب', 3100, 'heels', 'women', ['Ivory'], 'shoe', false],
            ['stiletto-ankle-boots', 'Stiletto Ankle Boots', 'بوت كاحل بكعب رفيع', 4800, 'boots', 'women', ['Black'], 'shoe', true],
            ['indigo-denim-shirt', 'Indigo Denim Shirt', 'قميص جينز نيلي', 1650, 'men-shirts', 'men', ['Indigo'], 'clothing', false],
            ['white-oxford-shirt', 'White Oxford Shirt', 'قميص أكسفورد أبيض', 1450, 'men-shirts', 'men', ['White'], 'clothing', false],
            ['navy-linen-tee', 'Navy Linen Tee', 'تيشيرت كتان كحلي', 950, 't-shirts', 'men', ['Navy'], 'clothing', true],
            ['wool-overcoat', 'Wool Overcoat', 'بالطو صوف', 7400, 'men-coats', 'men', ['Brown'], 'clothing', false],
            ['horsebit-loafers', 'Horsebit Loafers', 'لوفر جلد', 4300, 'loafers', 'men', ['Tan', 'Black'], 'shoe', false],
            ['tan-derby-shoes', 'Tan Derby Shoes', 'حذاء ديربي جملي', 3900, 'derby-shoes', 'men', ['Tan'], 'shoe', true],
        ];

        foreach ($products as $i => [$slug, $en, $ar, $price, $leaf, $top, $productColours, $sizeKind, $isNew]) {
            if (Product::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $product = Product::create([
                'brand_id' => $brand->id,
                'name' => ['en' => $en, 'ar' => $ar],
                'slug' => $slug,
                'short_description' => ['en' => 'Designed in Egypt, made to last.', 'ar' => 'مصمم في مصر، ومصنوع ليدوم.'],
                'description' => [
                    'en' => "The {$en}: a clean cut in quality fabric, finished by hand. Easy to dress up or down.",
                    'ar' => "{$ar}: قصّة نظيفة بخامة عالية الجودة ولمسات نهائية يدوية، تناسب الإطلالات الرسمية واليومية.",
                ],
                'status' => ProductStatus::Active,
                // New arrivals are recent (the "New" badge uses the publish date).
                'published_at' => $isNew ? now()->subDays($i % 5) : now()->subDays(40 + $i * 3),
            ]);

            $categoryIds = [$this->cat[$top]->id, $this->cat[$leaf]->id];
            if ($isNew) {
                $categoryIds[] = $newIn->id;
            }
            $product->categories()->attach($categoryIds);

            $options = [$colour->id => array_map(fn (string $c): int => $colours[$c]->id, $productColours)];
            if ($sizeKind === 'clothing') {
                $options[$size->id] = array_map(fn (string $s): int => $sizes[$s]->id, ['S', 'M', 'L', 'XL']);
            } elseif ($sizeKind === 'shoe') {
                $range = $top === 'women' ? range(37, 41) : range(40, 45);
                $options[$shoeSize->id] = array_map(fn (int $n): int => $shoeSizes[(string) $n]->id, $range);
            }

            foreach ($generator->generate($product, $options, $price * 100) as $n => $variant) {
                $stock = [5, 3, 8, 0, 6, 2, 9, 4][($i + $n) % 8];
                if ($stock > 0) {
                    $inventory->adjust($variant, $stock, StockMovementType::Restock, note: 'Demo stock');
                }
            }
        }

        $this->menu($colours);
        $this->command?->info('Demo fashion catalogue ready.');
    }

    /** @param array{0: string, 1: string} $names */
    private function category(string $slug, array $names, ?Category $parent = null, int $sort = 0): Category
    {
        $category = Category::withTrashed()->where('slug', $slug)->first()
            ?? Category::create(['name' => ['en' => $names[0], 'ar' => $names[1]], 'slug' => $slug, 'sort_order' => $sort, 'parent_id' => $parent?->id]);

        if ($category->trashed()) {
            $category->restore();
        }

        return $this->cat[$slug] = $category;
    }

    /**
     * A menu column: a group under the top category with its links below it.
     * Existing categories with these slugs are moved under the group.
     *
     * @param  array{0: string, 1: string}  $names
     * @param  array<string, array{0: string, 1: string}>  $children
     */
    private function group(Category $top, string $slug, array $names, int $sort, array $children): void
    {
        $group = $this->category($slug, $names, $top, $sort);
        $group->forceFill(['parent_id' => $top->id, 'sort_order' => $sort])->save();

        $order = 0;
        foreach ($children as $childSlug => $childNames) {
            $child = $this->category($childSlug, $childNames, $group, $order);
            $child->forceFill(['parent_id' => $group->id, 'sort_order' => $order++])->save();
        }
    }

    /** @return array{0: ProductAttribute, 1: ProductAttribute, 2: ProductAttribute} */
    private function attributes(): array
    {
        $find = fn (string $en, string $ar, AttributeType $type, int $sort): ProductAttribute => ProductAttribute::query()->get()
            ->first(fn (ProductAttribute $a): bool => $a->getTranslation('name', 'en') === $en)
            ?? ProductAttribute::create(['name' => ['en' => $en, 'ar' => $ar], 'type' => $type, 'sort_order' => $sort]);

        return [
            $find('Colour', 'اللون', AttributeType::Color, 0),
            $find('Size', 'المقاس', AttributeType::Select, 1),
            $find('Shoe size', 'مقاس الحذاء', AttributeType::Select, 2),
        ];
    }

    /**
     * @param  array<string, array{0: string, 1: string|null}>  $wanted  en => [ar, hex]
     * @return array<string, ProductAttributeValue>
     */
    private function values(ProductAttribute $attribute, array $wanted): array
    {
        $existing = $attribute->values()->get()->keyBy(fn (ProductAttributeValue $v): string => (string) $v->getTranslation('value', 'en'));
        $sort = (int) $attribute->values()->max('sort_order');

        $out = [];
        foreach ($wanted as $en => [$ar, $hex]) {
            $out[$en] = $existing->get($en) ?? $attribute->values()->create(['value' => ['en' => $en, 'ar' => $ar], 'color_hex' => $hex, 'sort_order' => ++$sort]);
        }

        return $out;
    }

    /**
     * Popular colours and the two picture cards of the Women and Men menus.
     *
     * @param  array<string, ProductAttributeValue>  $colours
     */
    private function menu(array $colours): void
    {
        $card = function (string $file, array $title, array $subtitle, string $link): array {
            $source = database_path("seeders/demo-home/{$file}");
            $path = "menu/{$file}";

            if (! app()->runningUnitTests() && is_file($source) && ! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, (string) file_get_contents($source));
            }

            return ['image' => $path, 'title' => ['en' => $title[0], 'ar' => $title[1]], 'subtitle' => ['en' => $subtitle[0], 'ar' => $subtitle[1]], 'link' => $link];
        };

        $this->cat['women']->forceFill([
            'menu_colour_value_ids' => array_map(fn (string $c): int => $colours[$c]->id, ['Black', 'Camel', 'Almond green', 'Ivory']),
            'menu_cards' => [
                $card('menu-women-1.jpg', ['The autumn edit', 'تشكيلة الخريف'], ['Coats & bags', 'معاطف وشنط'], 'women-coats'),
                $card('menu-women-2.jpg', ['Evening dresses', 'فساتين السهرة'], ['New in', 'وصل حديثًا'], 'dresses'),
            ],
        ])->save();

        $this->cat['men']->forceFill([
            'menu_colour_value_ids' => array_map(fn (string $c): int => $colours[$c]->id, ['Black', 'Brown', 'Navy']),
            'menu_cards' => [
                $card('menu-men-1.jpg', ['Tailored', 'تفصيل أنيق'], ['Coats & shirts', 'معاطف وقمصان'], 'men-coats'),
                $card('menu-men-2.jpg', ['The loafer', 'اللوفر'], ['Handmade leather', 'جلد مصنوع يدويًا'], 'loafers'),
            ],
        ])->save();
    }
}
