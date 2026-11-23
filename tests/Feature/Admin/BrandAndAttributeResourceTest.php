<?php

namespace Tests\Feature\Admin;

use App\Enums\AttributeType;
use App\Exceptions\ModelInUseException;
use App\Filament\Admin\Resources\Brands\Pages\CreateBrand;
use App\Filament\Admin\Resources\Brands\Pages\EditBrand;
use App\Filament\Admin\Resources\ProductAttributes\Pages\CreateProductAttribute;
use App\Filament\Admin\Resources\ProductAttributes\Pages\EditProductAttribute;
use App\Filament\Admin\Resources\ProductAttributes\RelationManagers\ValuesRelationManager;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandAndAttributeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    private function colour(): ProductAttribute
    {
        return ProductAttribute::create(['name' => ['en' => 'Colour', 'ar' => 'اللون'], 'type' => AttributeType::Color]);
    }

    private function variantUsing(ProductAttributeValue $value, ?Brand $brand = null): ProductVariant
    {
        $product = Product::create(['name' => ['en' => 'Bag', 'ar' => 'شنطة'], 'brand_id' => $brand?->id]);

        $variant = new ProductVariant(['sku' => 'BAG-'.$value->id, 'price' => 100000]);
        $variant->product()->associate($product)->save();
        $variant->attributeValues()->attach($value);

        return $variant;
    }

    // ---- Brands ---------------------------------------------------------

    public function test_admin_creates_a_brand_with_an_auto_slug_and_optional_description(): void
    {
        Livewire::test(CreateBrand::class)
            ->fillForm(['name' => 'Maison Nile', 'description' => ['en' => 'Leather from Cairo', 'ar' => '']])
            ->call('create')
            ->assertHasNoFormErrors();

        $brand = Brand::sole();
        $this->assertSame('maison-nile', $brand->slug);
        $this->assertSame(['en' => 'Leather from Cairo'], $brand->getTranslations('description'));
    }

    public function test_brand_slug_must_be_unique_and_well_formed(): void
    {
        Brand::create(['name' => 'Nile']);

        foreach (['nile', 'Not Valid'] as $slug) {
            Livewire::test(CreateBrand::class)
                ->fillForm(['name' => 'Other', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }

        $this->assertSame(1, Brand::count());
    }

    public function test_a_brand_with_products_is_not_deleted(): void
    {
        $brand = Brand::create(['name' => 'Nile']);
        Product::create(['name' => ['en' => 'Bag', 'ar' => 'شنطة'], 'brand_id' => $brand->id]);

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();

        $this->assertNotSoftDeleted($brand);

        $this->expectException(ModelInUseException::class);
        $brand->delete();
    }

    public function test_an_unused_brand_is_soft_deleted(): void
    {
        $brand = Brand::create(['name' => 'Nile']);

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($brand);
    }

    // ---- Attributes and values -----------------------------------------

    public function test_admin_creates_an_attribute_and_lands_on_its_edit_page(): void
    {
        Livewire::test(CreateProductAttribute::class)
            ->fillForm(['name' => ['en' => 'Size', 'ar' => 'المقاس'], 'type' => 'select'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(EditProductAttribute::getUrl(['record' => ProductAttribute::sole()]));

        $this->assertSame('size', ProductAttribute::sole()->slug);
    }

    public function test_colour_values_need_a_valid_swatch_colour(): void
    {
        $colour = $this->colour();

        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $colour, 'pageClass' => EditProductAttribute::class])
            ->callTableAction(CreateAction::class, data: ['value' => ['en' => 'Navy', 'ar' => 'كحلي']])
            ->assertHasTableActionErrors(['color_hex' => 'required']);

        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $colour, 'pageClass' => EditProductAttribute::class])
            ->callTableAction(CreateAction::class, data: ['value' => ['en' => 'Navy', 'ar' => 'كحلي'], 'color_hex' => '#1D2A44'])
            ->assertHasNoTableActionErrors();

        $value = ProductAttributeValue::sole();
        $this->assertSame('navy', $value->slug);
        $this->assertSame('#1d2a44', $value->color_hex);
        $this->assertSame('كحلي', $value->getTranslation('value', 'ar'));
    }

    public function test_value_slugs_are_unique_per_attribute_only(): void
    {
        $colour = $this->colour();
        $colour->values()->create(['value' => ['en' => 'Black', 'ar' => 'أسود'], 'color_hex' => '#000000']);
        $material = ProductAttribute::create(['name' => ['en' => 'Strap', 'ar' => 'الحزام']]);

        // A slug typed by the admin that is taken in the same attribute: validation error.
        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $colour, 'pageClass' => EditProductAttribute::class])
            ->callTableAction(CreateAction::class, data: ['value' => ['en' => 'Jet', 'ar' => 'فاحم'], 'slug' => 'black', 'color_hex' => '#111111'])
            ->assertHasTableActionErrors(['slug' => 'unique']);

        // Left empty: the auto slug steps aside instead of crashing on the unique index.
        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $colour, 'pageClass' => EditProductAttribute::class])
            ->callTableAction(CreateAction::class, data: ['value' => ['en' => 'Black', 'ar' => 'أسود'], 'color_hex' => '#111111'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(['black', 'black-2'], $colour->values()->orderBy('id')->pluck('slug')->all());

        // The same slug in a different attribute is fine.
        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $material, 'pageClass' => EditProductAttribute::class])
            ->callTableAction(CreateAction::class, data: ['value' => ['en' => 'Black', 'ar' => 'أسود']])
            ->assertHasNoTableActionErrors();
        $this->assertSame('black', $material->values()->sole()->slug);
    }

    public function test_editing_a_value_keeps_both_languages(): void
    {
        $colour = $this->colour();
        $value = $colour->values()->create(['value' => ['en' => 'Black', 'ar' => 'أسود'], 'color_hex' => '#000000']);

        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $colour, 'pageClass' => EditProductAttribute::class])
            ->mountTableAction(EditAction::class, $value)
            ->assertTableActionDataSet(['value' => ['en' => 'Black', 'ar' => 'أسود']])
            ->setTableActionData(['value' => ['en' => 'Jet Black', 'ar' => 'أسود فاحم']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(['en' => 'Jet Black', 'ar' => 'أسود فاحم'], $value->fresh()->getTranslations('value'));
    }

    public function test_values_used_by_variants_cannot_be_deleted(): void
    {
        $colour = $this->colour();
        $value = $colour->values()->create(['value' => ['en' => 'Black', 'ar' => 'أسود'], 'color_hex' => '#000000']);
        $this->variantUsing($value);

        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $colour, 'pageClass' => EditProductAttribute::class])
            ->callTableAction(DeleteAction::class, $value)
            ->assertNotified();
        $this->assertModelExists($value);

        Livewire::test(EditProductAttribute::class, ['record' => $colour->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();
        $this->assertModelExists($colour);
    }

    public function test_an_unused_attribute_is_deleted_with_its_values(): void
    {
        $colour = $this->colour();
        $value = $colour->values()->create(['value' => ['en' => 'Black', 'ar' => 'أسود'], 'color_hex' => '#000000']);

        Livewire::test(EditProductAttribute::class, ['record' => $colour->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($colour);
        $this->assertModelMissing($value);
    }

    public function test_customers_cannot_open_brands_or_options(): void
    {
        $customer = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/brands')->assertForbidden();
        $this->actingAs($customer)->get('/admin/attributes')->assertForbidden();
    }
}
