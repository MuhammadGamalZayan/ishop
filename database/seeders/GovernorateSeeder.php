<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

/**
 * Egypt's 27 governorates. Shipping areas and their prices are added in the
 * admin panel (or by DemoCatalogSeeder for local development).
 */
class GovernorateSeeder extends Seeder
{
    /** @var list<array{en: string, ar: string}> */
    public const GOVERNORATES = [
        ['en' => 'Cairo', 'ar' => 'القاهرة'],
        ['en' => 'Giza', 'ar' => 'الجيزة'],
        ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
        ['en' => 'Qalyubia', 'ar' => 'القليوبية'],
        ['en' => 'Dakahlia', 'ar' => 'الدقهلية'],
        ['en' => 'Sharqia', 'ar' => 'الشرقية'],
        ['en' => 'Gharbia', 'ar' => 'الغربية'],
        ['en' => 'Monufia', 'ar' => 'المنوفية'],
        ['en' => 'Beheira', 'ar' => 'البحيرة'],
        ['en' => 'Kafr El Sheikh', 'ar' => 'كفر الشيخ'],
        ['en' => 'Damietta', 'ar' => 'دمياط'],
        ['en' => 'Port Said', 'ar' => 'بورسعيد'],
        ['en' => 'Ismailia', 'ar' => 'الإسماعيلية'],
        ['en' => 'Suez', 'ar' => 'السويس'],
        ['en' => 'Fayoum', 'ar' => 'الفيوم'],
        ['en' => 'Beni Suef', 'ar' => 'بني سويف'],
        ['en' => 'Minya', 'ar' => 'المنيا'],
        ['en' => 'Assiut', 'ar' => 'أسيوط'],
        ['en' => 'Sohag', 'ar' => 'سوهاج'],
        ['en' => 'Qena', 'ar' => 'قنا'],
        ['en' => 'Luxor', 'ar' => 'الأقصر'],
        ['en' => 'Aswan', 'ar' => 'أسوان'],
        ['en' => 'Red Sea', 'ar' => 'البحر الأحمر'],
        ['en' => 'New Valley', 'ar' => 'الوادي الجديد'],
        ['en' => 'Matrouh', 'ar' => 'مطروح'],
        ['en' => 'North Sinai', 'ar' => 'شمال سيناء'],
        ['en' => 'South Sinai', 'ar' => 'جنوب سيناء'],
    ];

    public function run(): void
    {
        foreach (self::GOVERNORATES as $index => $name) {
            $existing = Governorate::query()->where('name->en', $name['en'])->first();

            if ($existing === null) {
                Governorate::create(['name' => $name, 'sort_order' => $index + 1, 'is_active' => true]);
            }
        }
    }
}
