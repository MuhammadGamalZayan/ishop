<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Setting;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\GovernorateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TaxRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Mega menu, home page sections, content pages, newsletter and contact form. */
class HomeAndMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, GovernorateSeeder::class, TaxRateSeeder::class, DemoCatalogSeeder::class]);
    }

    public function test_the_menu_has_women_and_men_with_columns_colours_and_about(): void
    {
        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('nav', 2)
            ->where('nav.0.name', 'Women')
            ->where('nav.1.name', 'Men')
            ->where('nav.0.columns.0.title', 'Clothing')
            ->where('nav.0.columns.0.links.0.name', 'Dresses')
            ->where('nav.0.colours.0.name', 'Black')
            ->where('nav.0.colours.0.url', fn (string $url): bool => str_contains($url, '/en/women?colour'))
            ->has('nav.0.cards', 2)
            ->has('aboutMenu.columns', 2)
            ->where('aboutMenu.columns.0.links.0.name', 'Our story'));
    }

    public function test_hidden_categories_stay_out_of_the_header(): void
    {
        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nav', fn ($nav): bool => collect($nav)->pluck('name')->doesntContain('Accessories')));
    }

    public function test_the_home_page_has_every_section(): void
    {
        $this->get('/ar')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('shop/home')
            ->has('hero', 3)
            ->where('hero.1.type', 'video')
            ->where('hero.0.title', 'صُنعت لترافقك')
            ->has('bestSellers.tabs', 3)
            ->where('bestSellers.tabs.2.name', 'إكسسوارات')
            // Mixed shelves, not only bags: the Women tab includes clothing or shoes.
            ->where('bestSellers.tabs.0.products', fn ($products): bool => collect($products)->pluck('url')->contains(fn (string $u): bool => str_contains($u, 'dress') || str_contains($u, 'boots') || str_contains($u, 'heels') || str_contains($u, 'blouse')))
            ->has('newIn.products', 4)
            ->has('trio', 3)
            ->has('marquee', 2)
            ->has('video')
            ->has('looks', 2)
            ->has('looks.0.hotspots', 2)
            ->has('sale.endsAt')
            ->has('compare.left.image'));
    }

    public function test_an_ended_or_switched_off_sale_is_hidden(): void
    {
        $home = Setting::get('home');
        $home['sale']['ends_at'] = now()->subMinute()->toDateTimeString();
        Setting::put('home', $home);

        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page->where('sale', null));
    }

    public function test_unpublished_pages_are_not_shown_or_listed(): void
    {
        Page::query()->where('slug', 'faq')->update(['is_published' => false]);

        $this->get('/en/pages/faq')->assertNotFound();
        $this->get('/en/pages/our-story')->assertOk();
        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('aboutMenu.columns', fn ($cols): bool => collect($cols)->flatMap(fn ($c) => $c['links'])->pluck('name')->doesntContain('FAQ')));
    }

    public function test_newsletter_sign_up_is_stored_once_in_lowercase(): void
    {
        $this->post('/newsletter', ['email' => 'Sara@Example.com'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/newsletter', ['email' => 'sara@example.com'])->assertRedirect();
        $this->post('/newsletter', ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        $this->assertSame(['sara@example.com'], NewsletterSubscriber::query()->pluck('email')->all());
    }

    public function test_the_contact_form_stores_the_message(): void
    {
        $this->post('/contact', ['name' => 'Sara', 'email' => 'sara@example.com', 'message' => 'Do you ship to Aswan?'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/contact', ['name' => '', 'email' => 'x', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame('Do you ship to Aswan?', ContactMessage::query()->sole()->message);
    }

    public function test_page_slugs_cannot_become_category_slugs(): void
    {
        $category = Category::create(['name' => ['en' => 'Pages', 'ar' => 'صفحات'], 'slug' => 'pages']);

        $this->assertNotSame('pages', $category->slug);
    }
}
