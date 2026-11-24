<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => '/auth/google/callback'],
            'services.github' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => '/auth/github/callback'],
        ]);
    }

    /** @param array<string, mixed> $raw */
    private function fakeProvider(string $provider, string $id, ?string $email, array $raw = [], string $name = 'Sara Ali'): void
    {
        $user = (new SocialiteUser)->setRaw($raw)->map(['id' => $id, 'name' => $name, 'nickname' => null, 'email' => $email]);

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_a_new_email_creates_a_verified_customer_and_signs_in(): void
    {
        $this->fakeProvider('google', 'g-1', 'New.Person@Example.com', ['email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect();

        $user = User::query()->where('email', 'new.person@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);
    }

    public function test_an_existing_customer_is_linked_when_the_provider_verified_the_email(): void
    {
        $customer = User::factory()->create(['email' => 'sara@example.com']);
        $this->fakeProvider('google', 'g-2', 'sara@example.com', ['email_verified' => true]);

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($customer);
        $this->assertSame(1, $customer->socialAccounts()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_an_existing_customer_is_not_taken_over_with_an_unverified_email(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);
        $this->fakeProvider('google', 'g-3', 'sara@example.com', ['email_verified' => false]);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, SocialAccount::query()->count());
    }

    public function test_a_linked_account_signs_in_even_if_the_provider_email_changed(): void
    {
        $customer = User::factory()->create();
        $customer->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42', 'email' => 'old@example.com']);
        $this->fakeProvider('github', '42', 'different@example.com');

        $this->get('/auth/github/callback');

        $this->assertAuthenticatedAs($customer);
    }

    public function test_staff_cannot_sign_in_with_a_social_account(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'boss@example.com']);
        $admin->assignRole('admin');
        $this->fakeProvider('google', 'g-4', 'boss@example.com', ['email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_two_factor_users_still_get_the_challenge(): void
    {
        $customer = User::factory()->create(['email' => 'safe@example.com']);
        $customer->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->fakeProvider('google', 'g-5', 'safe@example.com', ['email_verified' => true]);

        $this->get('/auth/google/callback')->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->assertSame($customer->id, session('login.id'));
    }

    public function test_a_provider_without_an_email_is_refused(): void
    {
        $this->fakeProvider('github', '77', null);

        $this->get('/auth/github/callback')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_provider_without_keys_is_not_available(): void
    {
        config(['services.github.client_id' => null]);

        $this->get('/auth/github/redirect')->assertNotFound();
        $this->get('/auth/github/callback')->assertNotFound();
        $this->get('/auth/facebook/redirect')->assertNotFound();
    }

    public function test_signed_in_users_link_and_unlink_providers(): void
    {
        $customer = User::factory()->create();
        $this->fakeProvider('github', '99', 'me@example.com');

        $this->actingAs($customer)->get('/auth/github/callback')->assertRedirect(route('profile.edit'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $customer->id, 'provider' => 'github', 'provider_id' => '99']);

        $this->actingAs($customer)->delete('/settings/social/github')->assertRedirect();
        $this->assertSame(0, $customer->socialAccounts()->count());
    }

    public function test_a_provider_account_linked_to_someone_else_is_refused(): void
    {
        $owner = User::factory()->create();
        $owner->socialAccounts()->create(['provider' => 'github', 'provider_id' => '5']);
        $other = User::factory()->create();
        $this->fakeProvider('github', '5', 'x@example.com');

        $this->actingAs($other)->get('/auth/github/callback')->assertRedirect(route('profile.edit'));

        $this->assertSame($owner->id, SocialAccount::query()->sole()->user_id);
    }
}
