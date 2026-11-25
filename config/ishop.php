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
        'storage', 'build', 'livewire', 'filament', 'pages', 'newsletter', 'contact',
    ],

    // Send every plain-HTTP request to HTTPS (and generate https:// links).
    // On in production unless FORCE_HTTPS=false.
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

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

    // Lets DemoCatalogSeeder / DemoImagesSeeder run in production, for a demo
    // or portfolio store. Off by default so a real shop never gets fake products.
    'demo_data' => (bool) env('ISHOP_DEMO_DATA', false),

    // First super-admin, created by AdminUserSeeder. Required in production.
    'admin' => [
        'email' => env('ISHOP_ADMIN_EMAIL'),
        'password' => env('ISHOP_ADMIN_PASSWORD'),
    ],

];
