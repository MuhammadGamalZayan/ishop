<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The guest's cart/wishlist key: a random UUID in a cookie.
 *
 * The cookie is encrypted by Laravel (it is not in the encryptCookies
 * exceptions), HttpOnly, SameSite=Lax and Secure on HTTPS, and lives for
 * `ishop.guest_cart_days`. A cart row is only created on the first add,
 * never on a page view, so bots don't fill the table.
 */
final class GuestToken
{
    /** The token sent by the browser, if it is a well-formed UUID. */
    public static function read(Request $request): ?string
    {
        $value = $request->cookie(self::name());

        return is_string($value) && Str::isUuid($value) ? $value : null;
    }

    /** The current token, or a new one queued on the response. */
    public static function readOrIssue(Request $request): string
    {
        if ($token = self::read($request)) {
            return $token;
        }

        $token = (string) Str::uuid();
        self::queue($request, $token);

        // Later code in the same request must see the same token.
        $request->cookies->set(self::name(), $token);

        return $token;
    }

    /** Refresh the 30 days on activity. */
    public static function refresh(Request $request, string $token): void
    {
        self::queue($request, $token);
    }

    public static function forget(Request $request): void
    {
        Cookie::queue(Cookie::forget(self::name()));
        $request->cookies->remove(self::name());
    }

    public static function name(): string
    {
        return config('ishop.guest_cookie');
    }

    private static function queue(Request $request, string $token): void
    {
        Cookie::queue(cookie(
            name: self::name(),
            value: $token,
            minutes: config('ishop.guest_cart_days') * 24 * 60,
            secure: $request->isSecure() || app()->isProduction(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
