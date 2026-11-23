<?php

return [

    /*
    | Category slugs live at the URL root (/en/{slug}), so these words can
    | never be used as a category slug: they belong to other pages.
    */
    'reserved_slugs' => [
        'all', 'products', 'product', 'cart', 'checkout', 'login', 'logout', 'register',
        'account', 'orders', 'wishlist', 'search', 'admin', 'api', 'settings', 'dashboard',
        'password', 'email', 'two-factor-challenge', 'user', 'payments', 'webhooks', 'up',
        'storage', 'build', 'livewire', 'filament',
    ],

    // Content languages. Every translatable field must be filled in all of them.
    'locales' => ['en', 'ar'],

    'currency' => 'EGP',

    // Unpaid online orders are cancelled and restocked after this many minutes.
    'payment_timeout_minutes' => 30,

    // Guest carts and wishlists are deleted after this many days without activity.
    'guest_cart_days' => 30,

    // Encrypted, HttpOnly cookie holding the guest's random cart/wishlist token.
    'guest_cookie' => 'ishop_guest',

    // Most units of one variant a single cart line may hold.
    'max_line_quantity' => 10,

    // Most different lines in one cart / products in one wishlist.
    'max_cart_lines' => 50,
    'max_wishlist_items' => 200,

    // Admins must confirm two-factor auth before entering Filament (skipped only in `local`).
    'admin_requires_2fa' => env('ISHOP_ADMIN_REQUIRES_2FA', true),

];
