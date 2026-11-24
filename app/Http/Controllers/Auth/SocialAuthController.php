<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Sign in / sign up with Google or GitHub, and link them in settings.
 *
 * Rules (agreed before building):
 * - A known provider account signs its user in.
 * - Otherwise, the provider's email: an existing customer with that email is
 *   linked only if the provider says the email is verified (else anyone
 *   could create a provider account with someone else's address); a new
 *   email creates a customer account, already verified.
 * - Staff accounts never sign in this way (password + 2FA only).
 * - Users with two-factor auth still get the two-factor challenge.
 * - Signed in already: the provider is linked to the current account.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider): SymfonyRedirect
    {
        $this->ensureEnabled($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureEnabled($provider);
        $linking = $request->user() !== null;

        try {
            $remote = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            // Cancelled on the provider's page, expired state, network error…
            Log::info('Social sign-in failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->fail(__('auth_social.failed'), $linking);
        }

        $providerId = (string) $remote->getId();
        $account = SocialAccount::query()->where('provider', $provider)->where('provider_id', $providerId)->first();

        if ($linking) {
            return $this->link($request->user(), $provider, $providerId, $remote, $account);
        }

        $user = $account !== null ? $account->user : $this->userForEmail($provider, $remote);

        if ($user instanceof RedirectResponse) {
            return $user;
        }

        if (! $user->is_active) {
            return $this->fail(__('auth_social.inactive'));
        }

        if ($user->isStaff()) {
            return $this->fail(__('auth_social.staff'));
        }

        if (! $account) {
            $user->socialAccounts()->create(['provider' => $provider, 'provider_id' => $providerId, 'email' => $remote->getEmail()]);
        }

        // Same as Fortify: a confirmed second factor is asked for before signing in.
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, SocialAccount::PROVIDERS, true), 404);

        /** @var User $user */
        $user = $request->user();
        $user->socialAccounts()->where('provider', $provider)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('auth_social.disconnected', ['provider' => $this->label($provider)])]);

        return back();
    }

    /** The account for the provider's email: existing (verified only) or new. */
    private function userForEmail(string $provider, ProviderUser $remote): User|RedirectResponse
    {
        $email = mb_strtolower(trim((string) $remote->getEmail()));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail(__('auth_social.no_email', ['provider' => $this->label($provider)]));
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            if (! $this->emailVerified($provider, $remote)) {
                return $this->fail(__('auth_social.unverified', ['provider' => $this->label($provider)]));
            }

            return $existing;
        }

        return DB::transaction(function () use ($remote, $email): User {
            $user = User::create([
                'name' => mb_substr(trim((string) ($remote->getName() ?: $remote->getNickname() ?: Str::before($email, '@'))), 0, 255),
                'email' => $email,
                // Never shown or used; "Forgot password" sets a real one if wanted.
                'password' => Str::password(40),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });
    }

    private function link(User $user, string $provider, string $providerId, ProviderUser $remote, ?SocialAccount $account): RedirectResponse
    {
        if ($account && $account->user_id !== $user->getKey()) {
            return $this->fail(__('auth_social.taken', ['provider' => $this->label($provider)]), linking: true);
        }

        if (! $account) {
            $user->socialAccounts()->updateOrCreate(['provider' => $provider], ['provider_id' => $providerId, 'email' => $remote->getEmail()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('auth_social.connected', ['provider' => $this->label($provider)])]);

        return redirect()->route('profile.edit');
    }

    /**
     * Google says so explicitly. GitHub only ever gives a verified address:
     * its public email must be verified, and otherwise Socialite picks the
     * primary *verified* one.
     */
    private function emailVerified(string $provider, ProviderUser $remote): bool
    {
        if ($provider === 'github') {
            return true;
        }

        $raw = method_exists($remote, 'getRaw') ? $remote->getRaw() : [];

        return filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function ensureEnabled(string $provider): void
    {
        abort_unless(in_array($provider, SocialAccount::enabledProviders(), true), 404);
    }

    private function fail(string $message, bool $linking = false): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return redirect()->route($linking ? 'profile.edit' : 'login');
    }

    private function label(string $provider): string
    {
        return $provider === 'github' ? 'GitHub' : 'Google';
    }
}
