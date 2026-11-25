<?php

namespace App\Support\Storefront;

use App\Enums\AttributeType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Shapes catalog data for the storefront pages (plain arrays for Inertia).
 * Only published products, active variants and active categories appear.
 */
class Catalog
{
    /** @var array<int, true>|null */
    private ?array $bestSellers = null;

    /**
     * The header: one entry per top-level category marked "show in menu".
     * Mega menu columns come from the category tree: a sub-category that has
     * its own children becomes a column (Clothing → Dresses, Shirts…); plain
     * sub-categories are gathered in a "Shop" column. Popular colours and the
     * picture cards are chosen per category in the admin panel.
     *
     * @return list<array<string, mixed>>
     */
    public function navigation(): array
    {
        $tops = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->where('show_in_menu', true)
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')->with(['children' => fn ($c) => $c->active()->orderBy('sort_order')])])
            ->orderBy('sort_order')
            ->get();

        $colourIds = $tops->flatMap(fn (Category $c): array => $c->menu_colour_value_ids ?? [])->unique()->values()->all();
        $colours = $colourIds === [] ? collect() : ProductAttributeValue::query()->whereKey($colourIds)->get()->keyBy('id');

        $newArrivals = Category::query()->active()->where('slug', 'new-arrivals')->first();

        return $tops->map(function (Category $top) use ($colours, $newArrivals): array {
            $url = route('shop.category', ['slug' => $top->slug]);
            $link = fn (Category $c): array => ['name' => $c->name, 'url' => route('shop.category', ['slug' => $c->slug])];

            $columns = $top->children->filter(fn (Category $g): bool => $g->children->isNotEmpty())
                ->map(fn (Category $g): array => ['title' => $g->name, 'url' => route('shop.category', ['slug' => $g->slug]), 'links' => $g->children->map($link)->values()->all()])
                ->values()->all();

            $leaves = $top->children->filter(fn (Category $g): bool => $g->children->isEmpty())->map($link)->values()->all();
            if ($leaves !== []) {
                $columns[] = ['title' => __('shop.menu.shop'), 'url' => $url, 'links' => $leaves];
            }

            $highlights = array_values(array_filter([
                $newArrivals ? ['name' => __('shop.menu.new_in'), 'url' => route('shop.category', ['slug' => $newArrivals->slug])] : null,
                ['name' => __('shop.menu.best_sellers'), 'url' => $url.'?sort=best'],
                ['name' => __('shop.menu.view_all', ['name' => $top->name]), 'url' => $url],
            ]));

            return [
                'name' => $top->name,
                'url' => $url,
                // Kept for the footer and the phone menu's simple list.
                'children' => $top->children->map($link)->values()->all(),
                'columns' => $columns,
                'colours' => collect($top->menu_colour_value_ids ?? [])
                    ->map(fn ($id) => $colours->get($id))
                    ->filter()
                    ->map(fn (ProductAttributeValue $v): array => ['name' => $v->value, 'hex' => $v->color_hex, 'url' => $url.'?'.http_build_query(['colour' => [$v->slug]])])
                    ->values()->all(),
                'highlights' => $highlights,
                'cards' => self::pictureCards($top->menu_cards ?? []),
            ];
        })->values()->all();
    }

    /**
     * The About mega menu: published pages in two columns (Discover, Help)
     * and the two picture cards set in the admin panel (Home page → About menu).
     * Empty when there is nothing to show, so the header hides the item.
     *
     * @return array{columns: list<array{title: string, links: list<array{name: string, url: string}>}>, cards: list<array<string, mixed>>}|null
     */
    public function aboutMenu(): ?array
    {
        $pages = Page::query()->published()->whereNotNull('menu_group')->orderBy('sort_order')->get(['id', 'title', 'slug', 'menu_group']);

        $columns = collect(Page::MENU_GROUPS)
            ->map(fn (string $group): array => [
                'title' => __("shop.menu.{$group}"),
                'links' => $pages->where('menu_group', $group)->map(fn (Page $p): array => ['name' => $p->title, 'url' => route('shop.page', ['slug' => $p->slug])])->values()->all(),
            ])
            ->filter(fn (array $c): bool => $c['links'] !== [])
            ->values()->all();

        if ($columns === []) {
            return null;
        }

        return ['columns' => $columns, 'cards' => self::pictureCards(Setting::get('about_menu')['cards'] ?? [])];
    }

    /**
     * Picture cards (menus, home page): stored image path + titles in both
     * languages + a link that is a category slug, a path or a full URL.
     *
     * @param  array<int, mixed>  $cards
     * @return list<array{image: string|null, title: string, subtitle: string, url: string}>
     */
    public static function pictureCards(array $cards): array
    {
        return collect($cards)->filter(fn ($c): bool => is_array($c))->map(fn (array $c): array => [
            'image' => self::mediaUrl($c['image'] ?? null),
            'title' => self::text($c['title'] ?? null),
            'subtitle' => self::text($c['subtitle'] ?? null),
            'url' => self::link($c['link'] ?? null),
        ])->values()->all();
    }

    /** The current language's text from ['en' => …, 'ar' => …] (falls back to English). */
    public static function text(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) ($value ?? '');
        }

        return (string) ($value[app()->getLocale()] ?? '') ?: (string) ($value['en'] ?? '');
    }

    /** A stored public-disk path (or a full URL) → URL. */
    public static function mediaUrl(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        if (! is_string($path) || $path === '') {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }

    /** Admin link field: full URL or "/path" as typed; products/… and pages/… or a category slug in the current language. */
    public static function link(mixed $link): string
    {
        $link = trim((string) $link);

        if ($link === '') {
            return route('shop.home');
        }

        if (str_starts_with($link, 'http') || str_starts_with($link, '/')) {
            return $link;
        }

        // "products/mini-clutch", "pages/faq": in the visitor's language.
        if (preg_match('#^(products|pages)/[a-z0-9-]+$#', $link)) {
            return url('/'.app()->getLocale().'/'.$link);
        }

        return route('shop.category', ['slug' => $link]);
    }

    public function cardQuery(): Builder
    {
        return Product::query()
            ->published()
            ->whereHas('variants', fn ($q) => $q->where('is_active', true))
            ->with([
                'media',
                'variants' => fn ($q) => $q->where('is_active', true)->with('attributeValues.productAttribute'),
            ]);
    }

    /** @param  Collection<int, Product>  $products */
    public function cards(Collection $products): array
    {
        return $products->map(fn (Product $p): array => $this->card($p))->values()->all();
    }

    public function card(Product $product): array
    {
        /** @var Collection<int, ProductVariant> $variants */
        $variants = $product->variants;
        $default = $variants->firstWhere('is_default', true) ?? $variants->first();
        $min = (int) $variants->min('price');
        $max = (int) $variants->max('price');
        $colours = $this->colours($variants);
        $inStock = $variants->contains(fn (ProductVariant $v): bool => $v->isInStock());
        $onSale = $default?->isOnSale() ?? false;

        return [
            'id' => $product->getKey(),
            'name' => $product->name,
            'url' => route('shop.product', ['slug' => $product->slug]),
            'price' => $min,
            'compare_at' => $onSale ? $default->compare_at_price : null,
            'price_varies' => $min !== $max,
            'image' => $product->getFirstMediaUrl('images', 'card') ?: null,
            'image_hover' => $product->getMedia('images')->get(1)?->getUrl('card'),
            'swatch' => $colours[0]['hex'] ?? null,
            'colours' => count($colours),
            'badge' => ! $inStock ? 'sold_out' : ($this->isBestSeller($product) ? 'best' : ($onSale ? 'sale' : ($this->isNew($product) ? 'new' : null))),
            'in_stock' => $inStock,
            // One variant and nothing to choose: the + button adds it straight away.
            'quick_variant_id' => $variants->count() === 1 && $inStock ? $default?->getKey() : null,
        ];
    }

    /** @param  Collection<int, ProductVariant>  $variants */
    private function colours(Collection $variants): array
    {
        return $variants
            ->flatMap(fn (ProductVariant $v) => $v->attributeValues)
            ->filter(fn (ProductAttributeValue $value): bool => $value->productAttribute->type === AttributeType::Color)
            ->unique('id')
            ->map(fn (ProductAttributeValue $value): array => ['name' => $value->value, 'hex' => $value->color_hex])
            ->values()
            ->all();
    }

    private function isNew(Product $product): bool
    {
        return $product->published_at !== null && $product->published_at->gt(now()->subDays(30));
    }

    /** Top 10 products by units sold in the last 90 days (cancelled orders excluded). */
    public function isBestSeller(Product $product): bool
    {
        $this->bestSellers ??= OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->where('orders.placed_at', '>=', now()->subDays(90))
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])
            ->groupBy('product_variants.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit(10)
            ->pluck('product_variants.product_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();

        return isset($this->bestSellers[$product->getKey()]);
    }

    /** Ids of the best sellers, best first. */
    public function bestSellerIds(): array
    {
        $this->isBestSeller(new Product);

        return array_keys($this->bestSellers ?? []);
    }
}
