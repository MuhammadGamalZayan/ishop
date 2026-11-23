<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A customer deletes their own account.
 *
 * The users row is kept (soft deleted) because orders, payments and coupon
 * redemptions must keep pointing at it. Everything that identifies the
 * person is removed, so:
 * - the email and phone are free to register again,
 * - no personal data stays on the account itself (order addresses are
 *   separate snapshots kept for invoices),
 * - the account can never be signed into again.
 */
class CloseAccount
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->passkeys()->delete();
            $user->addresses()->delete();
            $user->cart()->delete();
            $user->wishlist()->delete();

            $user->forceFill([
                'name' => 'Deleted user',
                // RFC 2606 ".invalid" can never receive mail; the id keeps it unique.
                'email' => "deleted-{$user->getKey()}@deleted.invalid",
                'phone' => null,
                'email_verified_at' => null,
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'is_active' => false,
            ])->save();

            $user->syncRoles([]);
            $user->delete();
        });
    }
}
