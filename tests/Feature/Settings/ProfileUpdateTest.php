<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_is_stored_in_lowercase_so_the_user_can_still_log_in()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Test@Example.com',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('test@example.com', $user->refresh()->email);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'Test@Example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_must_be_unique_regardless_of_case()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'Taken@Example.com',
            ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();

        // The row stays for order history, but nothing identifies the person.
        $this->assertSoftDeleted($user);
        $closed = User::withTrashed()->find($user->id);
        $this->assertSame("deleted-{$user->id}@deleted.invalid", $closed->email);
        $this->assertSame('Deleted user', $closed->name);
        $this->assertFalse($closed->is_active);
        $this->assertFalse(Hash::check('password', $closed->password));
    }

    public function test_a_deleted_account_frees_its_email_and_phone_for_a_new_registration()
    {
        $user = User::factory()->create(['email' => 'someone@example.com', 'phone' => '01000000000']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

        $this->post(route('register.store'), [
            'name' => 'Someone Again',
            'email' => 'someone@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertNotSame($user->id, auth()->id());

        $this->assertNull(User::withTrashed()->find($user->id)->phone);
        $this->assertTrue(User::where('phone', '01000000000')->doesntExist());
    }

    public function test_a_deleted_account_cannot_sign_in_with_its_old_email()
    {
        $user = User::factory()->create(['email' => 'gone@example.com']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

        $this->post(route('login.store'), [
            'email' => 'gone@example.com',
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
