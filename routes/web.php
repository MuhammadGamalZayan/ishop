<?php

use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\AddressController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\Shop\WishlistController;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// "/" sends visitors to their language: remembered choice, else browser, else English.
Route::get('/', fn (Request $request) => redirect('/'.SetLocale::preferred($request)))->name('home');

// Language switch for pages without /en or /ar in the URL (sign in, settings…).
Route::get('locale/{lang}', function (Request $request, string $lang) {
    $request->session()->put('locale', $lang);
    cookie()->queue(cookie(SetLocale::COOKIE, $lang, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));

    // Back to the same page only if it is on this site.
    $back = url()->previous();

    return redirect(str_starts_with($back, url('/')) ? $back : '/'.$lang);
})->whereIn('lang', ['en', 'ar'])->name('locale.switch');

// After sign-in Fortify lands here: staff go to the admin panel, customers to their orders.
Route::get('dashboard', function (Request $request) {
    /** @var User $user */
    $user = $request->user();

    return $user->canAccessPanel(Filament::getPanel('admin'))
        ? redirect('/admin')
        : redirect()->route('shop.account.orders');
})->middleware('auth')->name('dashboard');

// Checkout submits (no locale in the URL: they only answer with redirects).
Route::middleware(['auth', 'throttle:checkout'])->group(function () {
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('checkout/coupon', [CheckoutController::class, 'applyCoupon'])->name('checkout.coupon');
    Route::delete('checkout/coupon', [CheckoutController::class, 'removeCoupon'])->name('checkout.coupon.remove');
});

Route::middleware('auth')->group(function () {
    Route::post('account/orders/{number}/cancel', [AccountController::class, 'cancel'])->name('account.orders.cancel');
    Route::post('account/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::put('account/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address')->name('account.addresses.update');
    Route::delete('account/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address')->name('account.addresses.destroy');
    Route::post('account/addresses/{address}/default', [AddressController::class, 'makeDefault'])->whereNumber('address')->name('account.addresses.default');
});

// Storefront pages live under /en/... and /ar/... (SetLocale reads the prefix).
Route::prefix('{locale}')
    ->whereIn('locale', ['en', 'ar'])
    ->name('shop.')
    ->group(function () {
        Route::get('/', [ShopController::class, 'home'])->name('home');
        Route::get('wishlist', [ShopController::class, 'wishlist'])->name('wishlist');

        Route::middleware('auth')->group(function () {
            Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
            Route::get('account', fn () => redirect()->route('shop.account.orders'))->name('account');
            Route::get('account/orders', [AccountController::class, 'orders'])->name('account.orders');
            Route::get('account/orders/{number}', [AccountController::class, 'order'])->name('account.order');
            Route::get('account/addresses', [AddressController::class, 'index'])->name('account.addresses');
        });

        // Product URLs don't include the category, so they stay stable if categories change.
        Route::get('products/{slug}', [ShopController::class, 'product'])->name('product');
        Route::get('products/{slug}/quick', [ShopController::class, 'quick'])->middleware('throttle:cart')->name('product.quick');
        // Category slugs can't be reserved words (config/ishop.php), so this catch-all is last.
        Route::get('{slug}', [ShopController::class, 'category'])->where('slug', '[a-z0-9-]+')->name('category');
    });

// Cart and wishlist work for guests too (cookie token). Rate limited per user / IP.
Route::middleware('throttle:cart')->group(function () {
    Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('cart/items/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.items.update');
    Route::delete('cart/items/{item}', [CartController::class, 'destroy'])->whereNumber('item')->name('cart.items.destroy');
    Route::post('wishlist/{product}', [WishlistController::class, 'toggle'])->whereNumber('product')->name('wishlist.toggle');
});

// These addresses only receive form submissions. Opened in the browser (typed,
// bookmarked, refreshed after a form), send people to a real page instead of
// a "Method Not Allowed" error.
Route::get('cart/items/{item?}', fn () => redirect()->route('home'))->whereNumber('item');
Route::get('wishlist/{product}', fn () => redirect()->route('home'))->whereNumber('product');
Route::get('checkout', fn () => redirect()->route('shop.checkout'));
Route::get('checkout/coupon', fn () => redirect()->route('shop.checkout'));
Route::get('account/{any?}', fn () => redirect()->route('shop.account.orders'))->where('any', '.*');

require __DIR__.'/settings.php';
