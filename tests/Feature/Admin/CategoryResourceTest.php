<?php

namespace Tests\Feature\Admin;

use App\Exceptions\CategoryInUseException;
use App\Filament\Admin\Resources\Categories\Pages\CreateCategory;
use App\Filament\Admin\Resources\Categories\Pages\EditCategory;
use App\Filament\Admin\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        $user = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $user->assignRole('admin');

        return $user;
    }

    private function category(array $attributes = []): Category
    {
        return Category::create($attributes + [
            'name' => ['en' => 'Shoes', 'ar' => 'أحذية'],
        ]);
    }

    public function test_guests_are_sent_to_the_shared_login(): void
    {
        $this->get('/admin/categories')->assertRedirect(route('login'));
    }

    public function test_customers_cannot_open_the_panel(): void
    {
        $customer = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/categories')->assertForbidden();
    }

    public function test_admins_without_two_factor_cannot_open_the_panel(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        $this->actingAs($user)->get('/admin/categories')->assertForbidden();
    }

    public function test_admin_can_list_categories(): void
    {
        $category = $this->category();

        $this->actingAs($this->admin())->get('/admin/categories')->assertOk();

        Livewire::test(ListCategories::class)->assertCanSeeTableRecords([$category]);
    }

    public function test_admin_creates_a_category_in_both_languages_with_an_auto_slug(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => ['en' => "Men's Shoes", 'ar' => 'أحذية رجالي'],
                'description' => ['en' => '', 'ar' => ''],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::sole();
        $this->assertSame("Men's Shoes", $category->getTranslation('name', 'en'));
        $this->assertSame('أحذية رجالي', $category->getTranslation('name', 'ar'));
        $this->assertSame('mens-shoes', $category->slug);
        $this->assertNull($category->getRawOriginal('description'));
        $this->assertTrue($category->is_active);
    }

    public function test_both_languages_are_required(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => ['en' => 'Shoes', 'ar' => '']])
            ->call('create')
            ->assertHasFormErrors(['name.ar' => 'required']);

        $this->assertSame(0, Category::count());
    }

    public function test_slug_must_be_valid_unique_and_not_reserved(): void
    {
        $this->actingAs($this->admin());
        $this->category(['slug' => 'shoes']);

        foreach (['cart' => 'not_in', 'Bad Slug' => 'regex', 'shoes' => 'unique'] as $slug => $rule) {
            Livewire::test(CreateCategory::class)
                ->fillForm(['name' => ['en' => 'X', 'ar' => 'س'], 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }

        $this->assertSame(1, Category::count());
    }

    public function test_edit_form_is_filled_with_every_translation(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertSchemaStateSet([
                'name' => ['en' => 'Shoes', 'ar' => 'أحذية'],
                'slug' => 'shoes',
            ])
            ->fillForm(['name' => ['en' => 'Sneakers', 'ar' => 'سنيكرز']])
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertSame('سنيكرز', $category->getTranslation('name', 'ar'));
        $this->assertSame('shoes', $category->slug, 'slug must not change on update');
    }

    public function test_clearing_one_language_on_edit_removes_it(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category(['description' => ['en' => 'All shoes', 'ar' => 'كل الأحذية']]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['description' => ['en' => 'All shoes', 'ar' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['en' => 'All shoes'], $category->fresh()->getTranslations('description'));

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['description' => ['en' => '', 'ar' => '']])
            ->call('save');

        $this->assertNull($category->fresh()->getRawOriginal('description'));
    }

    public function test_a_category_cannot_be_moved_under_its_own_child(): void
    {
        $this->actingAs($this->admin());
        $parent = $this->category();
        $child = $this->category(['name' => ['en' => 'Boots', 'ar' => 'بوت'], 'parent_id' => $parent->id]);
        $grandchild = $this->category(['name' => ['en' => 'Hiking', 'ar' => 'هايكنج'], 'parent_id' => $child->id]);

        foreach ([$parent, $grandchild] as $target) {
            Livewire::test(EditCategory::class, ['record' => $parent->getRouteKey()])
                ->fillForm(['parent_id' => $target->id === $parent->id ? $parent->id : $grandchild->id])
                ->call('save')
                ->assertHasFormErrors(['parent_id']);
        }

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_a_category_with_children_is_not_deleted(): void
    {
        $this->actingAs($this->admin());
        $parent = $this->category();
        $this->category(['name' => ['en' => 'Boots', 'ar' => 'بوت'], 'parent_id' => $parent->id]);

        Livewire::test(EditCategory::class, ['record' => $parent->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertNotSoftDeleted($parent);
    }

    public function test_the_model_refuses_to_delete_a_category_with_children(): void
    {
        $parent = $this->category();
        $this->category(['name' => ['en' => 'Boots', 'ar' => 'بوت'], 'parent_id' => $parent->id]);

        $this->expectException(CategoryInUseException::class);
        $parent->delete();
    }

    public function test_an_empty_category_is_soft_deleted_and_can_be_restored(): void
    {
        $this->actingAs($this->admin());
        $category = $this->category();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($category);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->callAction(RestoreAction::class);

        $this->assertNotSoftDeleted($category);
    }

    public function test_a_child_is_not_restored_while_its_parent_is_trashed(): void
    {
        $this->actingAs($this->admin());
        $parent = $this->category();
        $child = $this->category(['name' => ['en' => 'Boots', 'ar' => 'بوت'], 'parent_id' => $parent->id]);
        $child->delete();
        $parent->delete();

        Livewire::test(EditCategory::class, ['record' => $child->getRouteKey()])
            ->callAction(RestoreAction::class)
            ->assertNotified();

        $this->assertSoftDeleted($child);
    }
}
