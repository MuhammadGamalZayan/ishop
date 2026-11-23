<?php

use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use App\Http\Controllers\Shop\WishlistController;
use Illuminate\Support\Facades\Route;

// "/" sends visitors to their language: remembered choice, else browser, else English.
Route::get('/', fn (Request $request) => redirect('/'.SetLocale::preferred($request)))->name('home');

// Storefront pages live under /en/... and /ar/... (SetLocale reads the prefix).
Route::prefix('{locale}')
    ->whereIn('locale', ['en', 'ar'])
    ->name('shop.')
    ->group(function () {
        Route::get('/', [ShopController::class, 'home'])->name('home');
        // Product URLs don't include the category, so they stay stable if categories change.
        Route::get('products/{slug}', [ShopController::class, 'product'])->name('product');
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

// These addresses only receive button submissions. Opened in the browser
// (typed, bookmarked, refreshed after a form), send people to a real page
// instead of a "Method Not Allowed" error.
Route::get('cart/items/{item?}', fn () => redirect()->route('home'))->whereNumber('item');
Route::get('wishlist/{product}', fn () => redirect()->route('home'))->whereNumber('product');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
