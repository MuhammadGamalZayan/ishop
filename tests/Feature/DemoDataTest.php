<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** The demo catalog stays out of a real shop unless ISHOP_DEMO_DATA turns it on. */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GovernorateSeeder::class);
        $this->app['env'] = 'production';
    }

    private function runDemoSeeder(): void
    {
        // Directly: db:seed would ask for confirmation in production.
        $this->app->make(DemoCatalogSeeder::class)->setContainer($this->app)->__invoke();
    }

    public function test_production_refuses_demo_products_by_default(): void
    {
        config(['ishop.demo_data' => false]);

        $this->expectException(RuntimeException::class);
        $this->runDemoSeeder();
    }

    public function test_production_seeds_demo_products_when_switched_on(): void
    {
        config(['ishop.demo_data' => true]);

        $this->runDemoSeeder();

        $this->assertGreaterThan(0, Product::query()->count());
    }
}
