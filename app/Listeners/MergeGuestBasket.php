<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Cart\GuestBasketMerger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;

/**
 * Guest cart + wishlist → account, right after sign-in (password, passkey,
 * 2FA challenge, remember-me) or registration.
 */
class MergeGuestBasket
{
    public function __construct(
        private readonly GuestBasketMerger $merger,
        private readonly Request $request,
    ) {}

    public function handle(Login|Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->merger->merge($event->user, $this->request);
        }
    }
}
