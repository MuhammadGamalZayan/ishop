<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront language.
 *
 * - On /en/... and /ar/... the URL decides, and the choice is remembered
 *   (session + a year-long cookie).
 * - Pages without a language in the URL (sign in, account settings…) use
 *   the remembered choice, else the browser's language, else English.
 * - route() calls fill {locale} automatically, so links stay in the
 *   visitor's language without passing it everywhere.
 *
 * Plain Laravel on purpose: unlike mcamara/laravel-localization it works
 * with `php artisan route:cache` (used on deploy) without extra setup.
 */
class SetLocale
{
    public const COOKIE = 'ishop_locale';

    /** @return list<string> */
    public static function supported(): array
    {
        return config('ishop.locales');
    }

    public static function preferred(Request $request): string
    {
        $remembered = $request->session()->get('locale') ?? $request->cookie(self::COOKIE);

        if (is_string($remembered) && in_array($remembered, self::supported(), true)) {
            return $remembered;
        }

        return $request->getPreferredLanguage(self::supported()) ?? 'en';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $fromUrl = $request->route('locale');
        $locale = is_string($fromUrl) && in_array($fromUrl, self::supported(), true)
            ? $fromUrl
            : self::preferred($request);

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        if ($fromUrl !== null && $request->session()->get('locale') !== $locale) {
            $request->session()->put('locale', $locale);
            Cookie::queue(cookie(self::COOKIE, $locale, 60 * 24 * 365, httpOnly: true, sameSite: 'lax'));
        }

        // Controllers never need the {locale} parameter themselves.
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
