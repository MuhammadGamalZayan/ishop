<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared Fortify login: staff land on the admin panel, customers on the storefront.
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
        $this->configureAuthorization();
        $this->configureAdminLanguageSwitch();
        $this->configureRateLimits();
    }

    /**
     * Cart and wishlist endpoints: generous for real shoppers, tight enough
     * to stop scripts filling carts or probing stock.
     */
    protected function configureRateLimits(): void
    {
        RateLimiter::for('cart', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Placing orders and trying coupon codes: enough for real use, too few to guess codes.
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * Arabic / English switch in the Filament panel. Filament reads its text
     * direction from the active locale, so Arabic renders RTL automatically.
     * The storefront locale is handled separately by mcamara/laravel-localization.
     */
    protected function configureAdminLanguageSwitch(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch
                ->locales(['en', 'ar'])
                ->labels(['ar' => 'العربية', 'en' => 'English'])
                ->cookieName('ishop_admin_locale')
                // The package checks the browser language before its own cookie;
                // read the cookie first so the admin's explicit choice wins.
                ->userPreferredLocale(fn (): ?string => request()->cookie('ishop_admin_locale'));
        });
    }

    /**
     * Outside production, any lazy-loaded relation (N+1), silently dropped
     * attribute or missing attribute throws, so mistakes surface while coding.
     */
    protected function configureModels(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
    }

    /**
     * super-admin passes every permission check; everyone else goes through
     * spatie/laravel-permission and the model policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole('super-admin') ? true : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
