<?php

namespace App\Http\Middleware;

use App\Services\Cart\CartService;
use App\Services\Cart\WishlistService;
use App\Support\Storefront\CartDrawer;
use App\Support\Storefront\Catalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'locale' => app()->getLocale(),
            'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            // Storefront text (lang/{en,ar}/shop.php) for the React `t()` helper.
            't' => fn (): array => trans('shop'),
            'nav' => fn (): array => app(Catalog::class)->navigation(),
            // Lazy: one small query, only when the page renders the header badge.
            'cart' => fn (): array => ['count' => app(CartService::class)->count()],
            'wishlist' => fn (): array => app(WishlistService::class)->productIds(),
            // Only loaded when the drawer opens (router.reload({ only: ['cartDrawer'] })).
            'cartDrawer' => Inertia::optional(fn (): array => app(CartDrawer::class)->data()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
