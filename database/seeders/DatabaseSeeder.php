<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data needed in every environment, then the first admin.
     * Every seeder here is safe to run more than once.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            GovernorateSeeder::class,
            TaxRateSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
