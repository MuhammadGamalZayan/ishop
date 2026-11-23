<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first super-admin from ISHOP_ADMIN_EMAIL / ISHOP_ADMIN_PASSWORD.
 * In local development it falls back to admin@ishop.test / password.
 * In production it refuses to run without both variables.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ISHOP_ADMIN_EMAIL');
        $password = env('ISHOP_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            if (! app()->isLocal()) {
                throw new RuntimeException('Set ISHOP_ADMIN_EMAIL and ISHOP_ADMIN_PASSWORD in .env before seeding an admin.');
            }

            $email = 'admin@ishop.test';
            $password = 'password';
        }

        $admin = User::query()->firstOrCreate(
            ['email' => mb_strtolower($email)],
            ['name' => 'iShop Admin', 'password' => $password],
        );

        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now(), 'is_active' => true])->save();
        $admin->assignRole('super-admin');
    }
}
