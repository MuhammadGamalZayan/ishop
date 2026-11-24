<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response, plus HTTPS-only in production.
 *
 * No Content-Security-Policy yet: the admin panel (Filament / Livewire /
 * Alpine) needs inline scripts and eval, so a useful CSP needs nonces
 * wired through both panels first. Framing is blocked here instead.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('ishop.force_https') && ! $request->isSecure()) {
            // 301 for GET/HEAD; 308 keeps the method and body of a form post.
            return redirect()->secure($request->getRequestUri(), $request->isMethodSafe() ? 301 : 308);
        }

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Stripe's card fields are an iframe from js.stripe.com and may use the Payment Request API.
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://js.stripe.com")');

        if ($request->isSecure()) {
            // Only honoured over HTTPS. Allow-popups: payment / sign-in windows must still work.
            $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

            // One year; browsers then refuse plain HTTP for this host.
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
