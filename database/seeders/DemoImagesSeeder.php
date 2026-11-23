<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Photos for the demo catalog: free Unsplash images (see
 * demo-images/CREDITS.md), matched to products by their English name,
 * e.g. "Mini Clutch" -> demo-images/mini-clutch-1.jpg, -2.jpg.
 *
 *   php artisan db:seed --class=DemoImagesSeeder
 *
 * Local only. A product that already has photos is left alone, so it is
 * safe to run again and never touches photos uploaded in the admin panel.
 */
class DemoImagesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoImagesSeeder is for local development only.');
        }

        $folder = database_path('seeders/demo-images');
        $added = 0;

        foreach (Product::query()->get() as $product) {
            if ($product->getMedia('images')->isNotEmpty()) {
                continue;
            }

            $slug = Str::slug((string) $product->getTranslation('name', 'en'));
            $files = glob("{$folder}/{$slug}-[0-9].jpg") ?: [];
            sort($files);

            foreach ($files as $file) {
                // copyMedia keeps the source file in the repository.
                $product->copyMedia($file)->toMediaCollection('images');
                $added++;
            }
        }

        $this->command?->info("Demo photos added: {$added}.");
    }
}
