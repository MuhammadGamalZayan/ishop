<?php

namespace Database\Seeders;

use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        if (TaxRate::query()->exists()) {
            return;
        }

        TaxRate::create([
            'name' => ['en' => 'VAT', 'ar' => 'ضريبة القيمة المضافة'],
            'rate_basis_points' => 1400, // 14%
            'is_inclusive' => true,
            'is_active' => true,
        ]);
    }
}
