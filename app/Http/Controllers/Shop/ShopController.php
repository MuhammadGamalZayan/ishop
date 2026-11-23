<?php

namespace App\Http\Controllers\Shop;

use App\Enums\AttributeType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Support\Storefront\Catalog;
use App\Support\Storefront\ProductPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    /**
     * Home: hero, then "best sellers" with one tab per top-level category
     * (the mockup's Women / Men). Best sellers first, then featured, then newest.
     */
    public function home(): Response
    {
        $tabs = Category::query()->active()->whereNull('parent_id')->orderBy('sort_order')->limit(4)->get()
            ->map(function (Category $category): array {
                $ids = $category->descendantIds();
                $ids[] = $category->getKey();

                $products = $this->catalog->cardQuery()
                    ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids))
                    ->orderByDesc('is_featured')
                    ->latest('published_at')
                    ->limit(12)
                    ->get();

                $best = array_flip($this->catalog->bestSellerIds());
                $products = $products->sortBy(fn (Product $p): int => $best[$p->getKey()] ?? PHP_INT_MAX)->take(4);

                return [
                    'name' => $category->name,
                    'url' => route('shop.category', ['slug' => $category->slug]),
                    'products' => $this->catalog->cards($products->values()),
                ];
            })
            ->filter(fn (array $tab): bool => $tab['products'] !== [])
            ->values();

        return Inertia::render('shop/home', [
            'tabs' => $tabs,
            'heroLinks' => $tabs->take(2)->map(fn (array $t): array => ['name' => $t['name'], 'url' => $t['url']])->all(),
        ]);
    }

    /**
     * Category page (or "all" products): filters from the query string,
     * all applied in the database. Unknown filter values are ignored.
     */
    public function category(Request $request, string $slug): Response
    {
        $category = $slug === 'all'
            ? null
            : Category::query()->active()->where('slug', $slug)->firstOrFail();

        $filters = $request->validate([
            'type' => ['sometimes', 'array', 'max:20'],
            'type.*' => ['string', 'max:120'],
            'colour' => ['sometimes', 'array', 'max:20'],
            'colour.*' => ['string', 'max:120'],
            'in_stock' => ['sometimes', 'boolean'],
            'min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'sort' => ['sometimes', 'in:featured,best,newest,price_asc,price_desc'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));

        $scope = $category ? [...$category->descendantIds(), $category->getKey()] : null;
        $base = fn () => $this->catalog->cardQuery()
            ->when($scope, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $scope)));

        $query = $base()
            // Name (both languages) and SKUs live in search_text; FULLTEXT on MySQL/MariaDB.
            ->when($search !== '', fn ($q) => in_array($q->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? $q->whereFullText('search_text', $search)
                : $q->where('search_text', 'like', '%'.$search.'%'))
            ->when($filters['type'] ?? null, fn ($q, $types) => $q->whereHas('categories', fn ($c) => $c->whereIn('slug', $types)))
            ->when($filters['colour'] ?? null, fn ($q, $colours) => $q->whereHas('variants', fn ($v) => $v
                ->where('is_active', true)
                ->whereHas('attributeValues', fn ($a) => $a->whereIn('attribute_values.slug', $colours)
                    ->whereHas('productAttribute', fn ($t) => $t->where('type', AttributeType::Color)))))
            ->when($filters['in_stock'] ?? false, fn ($q) => $q->whereHas('variants', fn ($v) => $v
                ->where('is_active', true)
                ->where(fn ($s) => $s->where('track_inventory', false)->orWhere('stock_quantity', '>', 0))))
            ->when(isset($filters['min']), fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('price', '>=', $filters['min'] * 100)))
            ->when(isset($filters['max']), fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('price', '<=', $filters['max'] * 100)))
            ->addSelect(['min_price' => ProductVariant::query()->selectRaw('MIN(price)')->whereColumn('product_id', 'products.id')->where('is_active', true)]);

        $sort = $filters['sort'] ?? 'featured';
        match ($sort) {
            'price_asc' => $query->orderBy('min_price'),
            'price_desc' => $query->orderByDesc('min_price'),
            'newest' => $query->latest('published_at'),
            'best' => $this->orderByBestSellers($query),
            default => $query->orderByDesc('is_featured')->latest('published_at'),
        };

        $page = $query->paginate(24)->withQueryString();
        $parent = $category?->loadMissing('parent')->parent;
        $parent = $parent?->is_active ? $parent : null;

        return Inertia::render('shop/category', [
            'category' => [
                'name' => $category?->name ?? __('shop.nav.all'),
                'description' => $category?->description,
                'image' => $category?->getFirstMediaUrl('image') ?: null,
                'slug' => $slug,
                'url' => route('shop.category', ['slug' => $slug]),
                'parent' => $parent ? ['name' => $parent->name, 'url' => route('shop.category', ['slug' => $parent->slug])] : null,
            ],
            'products' => $this->catalog->cards(new EloquentCollection($page->items())),
            'pagination' => [
                'total' => $page->total(),
                'label' => trans_choice('shop.category.products', $page->total(), ['count' => $page->total()]),
                'current' => $page->currentPage(),
                'last' => $page->lastPage(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'facets' => $this->facets($category, $scope),
            'filters' => [
                'type' => $filters['type'] ?? [],
                'colour' => $filters['colour'] ?? [],
                'in_stock' => (bool) ($filters['in_stock'] ?? false),
                'min' => $filters['min'] ?? null,
                'max' => $filters['max'] ?? null,
                'sort' => $sort,
                'q' => $search,
            ],
        ]);
    }

    public function product(string $slug): Response
    {
        $product = Product::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'media',
                'brand',
                'categories' => fn ($q) => $q->active(),
                'variants' => fn ($q) => $q->where('is_active', true)->with('attributeValues.productAttribute'),
            ])
            ->firstOrFail();

        abort_if($product->variants->isEmpty(), 404);

        return Inertia::render('shop/product', [
            'product' => app(ProductPage::class)->data($product),
        ]);
    }

    private function orderByBestSellers(Builder $query): void
    {
        $ids = $this->catalog->bestSellerIds();

        if ($ids === []) {
            $query->orderByDesc('is_featured')->latest('published_at');

            return;
        }

        // Best sellers first in rank order, then the rest by newest.
        $query->orderByRaw('CASE '.collect($ids)->map(fn ($id, $i) => 'WHEN products.id = '.(int) $id.' THEN '.$i)->implode(' ').' ELSE '.count($ids).' END')
            ->latest('published_at');
    }

    /** Filter options with counts, for the filter drawer. */
    private function facets(?Category $category, ?array $scope): array
    {
        $inScope = fn () => Product::query()->published()
            ->when($scope, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $scope)));

        $types = $category
            ? $category->children()->active()->get()->map(fn (Category $child): array => [
                'value' => $child->slug,
                'label' => $child->name,
                'count' => $inScope()->whereHas('categories', fn ($c) => $c->whereIn('categories.id', [...$child->descendantIds(), $child->getKey()]))->count(),
            ])->filter(fn (array $t): bool => $t['count'] > 0)->values()->all()
            : [];

        $colours = ProductAttributeValue::query()
            ->whereHas('productAttribute', fn ($q) => $q->where('type', AttributeType::Color))
            ->whereHas('variants', fn ($v) => $v->where('is_active', true)->whereIn('product_id', $inScope()->select('products.id')))
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductAttributeValue $v): array => ['value' => $v->slug, 'label' => $v->value, 'hex' => $v->color_hex])
            ->all();

        $prices = ProductVariant::query()
            ->where('is_active', true)
            ->whereIn('product_id', $inScope()->select('products.id'))
            ->selectRaw('MIN(price) as low, MAX(price) as high')
            ->first();

        return [
            'types' => $types,
            'colours' => $colours,
            'price' => ['min' => intdiv((int) $prices?->low, 100), 'max' => (int) ceil(((int) $prices?->high) / 100)],
        ];
    }
}
