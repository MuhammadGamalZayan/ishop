<?php

namespace App\Support\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The home page, as plain arrays for Inertia, from the "home" setting that
 * staff edit in the admin panel (Home page). Every section is optional: an
 * empty or unfinished section is simply left out. Texts are stored in both
 * languages and come out in the current one; image paths become URLs.
 */
class HomePage
{
    public function __construct(private readonly Catalog $catalog) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        $s = Setting::get('home');

        return [
            'hero' => $this->hero($s['hero'] ?? []),
            'bestSellers' => $this->bestSellers($s['best_sellers'] ?? []),
            'newIn' => $this->newIn($s['new_in'] ?? []),
            'trio' => $this->trio($s['trio'] ?? []),
            'marquee' => array_values(array_filter([$this->marquee($s['marquee_top'] ?? []), $this->marquee($s['marquee_bottom'] ?? [])])),
            'video' => $this->video($s['video'] ?? []),
            'looks' => $this->looks($s['looks'] ?? []),
            'sale' => $this->sale($s['sale'] ?? []),
            'compare' => $this->compare($s['compare'] ?? []),
        ];
    }

    /**
     * The newsletter band above the footer (every store page).
     *
     * @return array<string, string|null>
     */
    public static function newsletter(): array
    {
        $n = Setting::get('newsletter');

        return [
            'image' => Catalog::mediaUrl($n['image'] ?? null),
            'kicker' => Catalog::text($n['kicker'] ?? null) ?: __('shop.newsletter.kicker'),
            'title' => Catalog::text($n['title'] ?? null) ?: __('shop.newsletter.title'),
            'text' => Catalog::text($n['text'] ?? null) ?: __('shop.newsletter.text'),
        ];
    }

    /**
     * @param  array<int, mixed>  $slides
     * @return array<int, array<string, mixed>>
     */
    private function hero(array $slides): array
    {
        return collect($slides)->filter(fn ($s): bool => is_array($s) && (filled($s['image'] ?? null) || filled($s['video'] ?? null)))
            ->map(fn (array $s): array => [
                'type' => ($s['type'] ?? 'image') === 'video' && filled($s['video'] ?? null) ? 'video' : 'image',
                'image' => Catalog::mediaUrl($s['image'] ?? null),
                'video' => Catalog::mediaUrl($s['video'] ?? null),
                'kicker' => Catalog::text($s['kicker'] ?? null),
                'title' => Catalog::text($s['title'] ?? null),
                'button' => Catalog::text($s['button'] ?? null),
                'url' => Catalog::link($s['link'] ?? null),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    private function bestSellers(array $section): ?array
    {
        $ids = array_map('intval', (array) ($section['category_ids'] ?? []));
        $categories = $ids === []
            ? Category::query()->active()->whereNull('parent_id')->where('show_in_menu', true)->orderBy('sort_order')->limit(3)->get()
            : Category::query()->active()->whereKey($ids)->get()->sortBy(fn (Category $c): int => (int) array_search($c->getKey(), $ids, true));

        $best = array_flip($this->catalog->bestSellerIds());

        $tabs = $categories->map(function (Category $category) use ($best): array {
            $ranked = $this->inCategory($category)
                ->with('categories:id')
                ->orderByDesc('is_featured')
                ->latest('published_at')
                ->limit(40)
                ->get()
                ->sortBy(fn (Product $p): int => $best[$p->getKey()] ?? PHP_INT_MAX)
                ->values();

            $products = $this->mixed($category, $ranked, 4);

            return [
                'name' => $category->name,
                'url' => route('shop.category', ['slug' => $category->slug]).'?sort=best',
                'products' => $this->catalog->cards($products->values()),
            ];
        })->filter(fn (array $tab): bool => $tab['products'] !== [])->values()->all();

        return $tabs === [] ? null : ['kicker' => Catalog::text($section['kicker'] ?? null) ?: __('shop.home.best_sellers'), 'tabs' => $tabs];
    }

    /**
     * Best sellers that aren't all from one shelf: take turns between the
     * category's sub-sections (Clothing, Shoes, Bags…), best seller first in
     * each, so a tab shows e.g. a dress, heels, a bag and a coat.
     *
     * @param  Collection<int, Product>  $ranked  best first
     * @return EloquentCollection<int, Product>
     */
    private function mixed(Category $category, Collection $ranked, int $count): EloquentCollection
    {
        // Each direct sub-category and everything below it is one "shelf".
        $shelves = $category->children()->get()->mapWithKeys(fn (Category $c): array => [$c->getKey() => [$c->getKey(), ...$c->descendantIds()]]);

        $byShelf = $ranked->groupBy(function (Product $p) use ($shelves): int {
            $ids = $p->categories->pluck('id')->all();

            return (int) ($shelves->search(fn (array $tree): bool => array_intersect($ids, $tree) !== []) ?: 0);
        })->map->values();

        $picked = new EloquentCollection;
        for ($round = 0; $picked->count() < $count && $byShelf->contains(fn ($items) => $items->has($round)); $round++) {
            foreach ($byShelf as $items) {
                if ($picked->count() < $count && $items->has($round)) {
                    $picked->push($items->get($round));
                }
            }
        }

        return $picked;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>|null
     */
    private function newIn(array $section): ?array
    {
        $category = Category::query()->active()->where('slug', $section['category'] ?? 'new-arrivals')->first();
        if (! $category) {
            return null;
        }

        $products = $this->inCategory($category)->latest('published_at')->limit(4)->get();
        if ($products->isEmpty()) {
            return null;
        }

        return [
            'kicker' => Catalog::text($section['kicker'] ?? null),
            'title' => Catalog::text($section['title'] ?? null) ?: $category->name,
            'url' => route('shop.category', ['slug' => $category->slug]),
            'button' => Catalog::text($section['button'] ?? null) ?: __('shop.home.shop_new'),
            'products' => $this->catalog->cards($products),
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function trio(array $items): array
    {
        $out = collect($items)->filter(fn ($i): bool => is_array($i) && filled($i['image'] ?? null))
            ->map(function (array $i): ?array {
                $category = filled($i['category'] ?? null) ? Category::query()->active()->where('slug', $i['category'])->first() : null;

                return [
                    'image' => Catalog::mediaUrl($i['image']),
                    'title' => Catalog::text($i['title'] ?? null) ?: ($category->name ?? ''),
                    'url' => $category ? route('shop.category', ['slug' => $category->slug]) : Catalog::link($i['link'] ?? null),
                ];
            })->filter()->take(3)->values()->all();

        return count($out) === 3 ? $out : [];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>|null
     */
    private function marquee(array $items): ?array
    {
        $products = Product::query()->with('media')->whereKey(collect($items)->pluck('product_id')->filter()->all())->get()->keyBy('id');

        $out = collect($items)->filter(fn ($i): bool => is_array($i) && filled(Catalog::text($i['text'] ?? null)))
            ->map(fn (array $i): array => [
                'text' => Catalog::text($i['text']),
                'url' => Catalog::link($i['link'] ?? null),
                'image' => $products->get($i['product_id'] ?? 0)?->getFirstMediaUrl('images', 'thumb') ?: null,
            ])->values()->all();

        return $out === [] ? null : $out;
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>|null
     */
    private function video(array $v): ?array
    {
        if (blank($v['video'] ?? null)) {
            return null;
        }

        return [
            'video' => Catalog::mediaUrl($v['video']),
            'poster' => Catalog::mediaUrl($v['poster'] ?? null),
            'kicker' => Catalog::text($v['kicker'] ?? null),
            'title' => Catalog::text($v['title'] ?? null),
        ];
    }

    /**
     * @param  array<int, mixed>  $slides
     * @return array<int, array<string, mixed>>
     */
    private function looks(array $slides): array
    {
        $ids = collect($slides)->flatMap(fn ($s) => collect($s['hotspots'] ?? [])->pluck('product_id'))->filter()->unique()->all();
        $cards = collect($this->catalog->cards($this->catalog->cardQuery()->whereKey($ids)->get()))->keyBy('id');

        return collect($slides)->filter(fn ($s): bool => is_array($s) && filled($s['image'] ?? null))
            ->map(fn (array $s): array => [
                'image' => Catalog::mediaUrl($s['image']),
                'hotspots' => collect($s['hotspots'] ?? [])
                    ->filter(fn ($h): bool => is_array($h) && $cards->has($h['product_id'] ?? 0))
                    ->map(fn (array $h): array => [
                        'x' => max(0, min(100, (float) ($h['x'] ?? 50))),
                        'y' => max(0, min(100, (float) ($h['y'] ?? 50))),
                        'product' => $cards->get($h['product_id']),
                    ])->values()->all(),
            ])
            ->filter(fn (array $s): bool => $s['hotspots'] !== [])
            ->values()->all();
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>|null
     */
    private function sale(array $s): ?array
    {
        $ends = filled($s['ends_at'] ?? null) ? Carbon::parse($s['ends_at']) : null;

        // Shown only while switched on and before its end date.
        if (! ($s['enabled'] ?? false) || blank($s['image'] ?? null) || ($ends && $ends->isPast())) {
            return null;
        }

        return [
            'image' => Catalog::mediaUrl($s['image']),
            'kicker' => Catalog::text($s['kicker'] ?? null),
            'title' => Catalog::text($s['title'] ?? null),
            'text' => Catalog::text($s['text'] ?? null),
            'button' => Catalog::text($s['button'] ?? null),
            'url' => Catalog::link($s['link'] ?? null),
            'endsAt' => $ends?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>|null
     */
    private function compare(array $c): ?array
    {
        $side = fn (array $x): array => ['image' => Catalog::mediaUrl($x['image'] ?? null), 'label' => Catalog::text($x['label'] ?? null), 'url' => Catalog::link($x['link'] ?? null)];
        $left = $side((array) ($c['left'] ?? []));
        $right = $side((array) ($c['right'] ?? []));

        if (! $left['image'] || ! $right['image']) {
            return null;
        }

        return ['title' => Catalog::text($c['title'] ?? null) ?: __('shop.home.choose_colour'), 'left' => $left, 'right' => $right];
    }

    /** @return Builder<Product> */
    private function inCategory(Category $category): Builder
    {
        $ids = [$category->getKey(), ...$category->descendantIds()];

        return $this->catalog->cardQuery()->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
    }
}
