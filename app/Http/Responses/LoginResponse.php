<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * One shared Fortify login for customers and staff.
 *
 * - Staff who can enter the admin panel land on /admin unless they were
 *   heading somewhere specific (the "intended" URL).
 * - The login form is an Inertia page but the panel is not, so a redirect into
 *   the panel must be a full page visit (Inertia::location), otherwise Inertia
 *   would try to render Filament's HTML as a page component.
 */
class LoginResponse implements LoginResponseContract, TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return $request->routeIs('two-factor.login*')
                ? new JsonResponse('', 204)
                : new JsonResponse(['two_factor' => false]);
        }

        $target = $this->targetUrl($request);

        if ($request->header('X-Inertia') && $this->isPanelUrl($target)) {
            return Inertia::location($target);
        }

        return redirect()->to($target);
    }

    private function targetUrl(Request $request): string
    {
        $default = Fortify::redirects('login');
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $intended !== '') {
            return $intended;
        }

        $user = $request->user();
        $panel = Filament::getPanel('admin');

        if ($user instanceof User && $user->canAccessPanel($panel)) {
            return $panel->getUrl();
        }

        return url($default);
    }

    private function isPanelUrl(string $url): bool
    {
        $panelUrl = rtrim(url(Filament::getPanel('admin')->getPath()), '/');

        return $url === $panelUrl || str_starts_with($url, $panelUrl.'/');
    }
}
