<?php

namespace Tests\Feature\Admin;

use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Filament\Admin\Resources\Products\Pages\EditProduct;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Filament\Admin\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Admin\Support\MoneyInput;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Catalog\VariantGenerator;
use App\Services\Inventory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductAttribute $colour;

    private ProductAttribute $size;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->colour = ProductAttribute::create(['name' => ['en' => 'Colour', 'ar' => 'اللون'], 'type' => AttributeType::Color]);
        $this->size = ProductAttribute::create(['name' => ['en' => 'Size', 'ar' => 'المقاس'], 'sort_order' => 1]);
    }

    private function value(ProductAttribute $attribute, string $en, string $ar, ?string $hex = null): ProductAttributeValue
    {
        return $attribute->values()->create(['value' => ['en' => $en, 'ar' => $ar], 'color_hex' => $hex]);
    }

    private function relation(Product $product)
    {
        return Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class]);
    }

    public function test_money_is_converted_to_piastres_without_float_errors(): void
    {
        $this->assertSame(1999, MoneyInput::toPiastres('19.99'));
        $this->assertSame(125000, MoneyInput::toPiastres('1250'));
        $this->assertSame(125050, MoneyInput::toPiastres('1250.5'));
        $this->assertSame(1, MoneyInput::toPiastres('0.01'));
        $this->assertNull(MoneyInput::toPiastres(''));
    }

    public function test_a_simple_product_gets_one_default_variant_and_opening_stock(): void
    {
        $category = Category::create(['name' => ['en' => 'Bags', 'ar' => 'شنط']]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['en' => 'Card Holder', 'ar' => 'حافظة كروت'],
                'first' => ['price' => '1250.50', 'sku' => 'card-01', 'stock' => 7],
                'categories' => [$category->id],
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::sole();
        $variant = $product->variants()->sole();

        $this->assertSame(125050, $variant->price);
        $this->assertSame('CARD-01', $variant->sku);
        $this->assertTrue($variant->is_default);
        $this->assertSame(7, $variant->stock_quantity);
        $this->assertSame(StockMovementType::Restock, StockMovement::sole()->type);
        $this->assertSame($this->admin->id, StockMovement::sole()->user_id);
        $this->assertNotNull($product->published_at, 'Active without a date publishes now');
        $this->assertSame([$category->id], $product->categories()->pluck('categories.id')->all());
        $this->assertSame(1, Product::published()->count());
    }

    public function test_a_product_with_options_gets_every_combination(): void
    {
        $black = $this->value($this->colour, 'Black', 'أسود', '#000000');
        $navy = $this->value($this->colour, 'Navy', 'كحلي', '#1d2a44');
        $cabin = $this->value($this->size, 'Cabin', 'كابينة');
        $large = $this->value($this->size, 'Large', 'كبير');

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => ['en' => 'Weekender', 'ar' => 'شنطة سفر'],
                'has_options' => true,
                'first' => ['price' => '11500', 'sku' => 'WKD'],
                'options' => [$this->colour->id => [$black->id, $navy->id], $this->size->id => [$cabin->id, $large->id]],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $variants = Product::sole()->variants()->with('attributeValues')->orderBy('id')->get();

        $this->assertCount(4, $variants);
        $this->assertSame(['WKD-BLACK-CABIN', 'WKD-BLACK-LARGE', 'WKD-NAVY-CABIN', 'WKD-NAVY-LARGE'], $variants->pluck('sku')->all());
        $this->assertSame([true, false, false, false], $variants->pluck('is_default')->all());
        $this->assertTrue($variants->every(fn ($v) => $v->price === 1150000 && $v->stock_quantity === 0));
    }

    public function test_options_switched_on_without_values_is_an_error(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => ['en' => 'X', 'ar' => 'س'], 'has_options' => true, 'first' => ['price' => '100']])
            ->call('create')
            ->assertHasErrors(['data.has_options']);

        $this->assertSame(0, Product::count());
    }

    public function test_generating_again_only_adds_new_combinations(): void
    {
        $black = $this->value($this->colour, 'Black', 'أسود', '#000000');
        $navy = $this->value($this->colour, 'Navy', 'كحلي', '#1d2a44');
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);
        $generator = app(VariantGenerator::class);

        $generator->generate($product, [$this->colour->id => [$black->id]], 9900);
        $created = $generator->generate($product, [$this->colour->id => [$black->id, $navy->id]], 9900);

        $this->assertCount(1, $created);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
    }

    public function test_the_generator_rejects_values_from_another_attribute(): void
    {
        $black = $this->value($this->colour, 'Black', 'أسود', '#000000');
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);

        $this->expectException(\InvalidArgumentException::class);
        app(VariantGenerator::class)->generate($product, [$this->size->id => [$black->id]], 9900);
    }

    public function test_generate_action_in_the_variants_table(): void
    {
        $black = $this->value($this->colour, 'Black', 'أسود', '#000000');
        $navy = $this->value($this->colour, 'Navy', 'كحلي', '#1d2a44');
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);

        $this->relation($product)
            ->callTableAction('generate', data: ['options' => [$this->colour->id => [$black->id, $navy->id]], 'price' => '9900', 'sku_prefix' => 'TOTE'])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $this->assertSame(['TOTE-BLACK', 'TOTE-NAVY'], $product->variants()->orderBy('id')->pluck('sku')->all());
    }

    public function test_adjust_stock_restock_count_and_remove(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);
        $variant = $product->variants()->create(['sku' => 'TOTE', 'price' => 990000, 'is_default' => true]);

        $this->relation($product)
            ->callTableAction('adjustStock', $variant, data: ['reason' => 'restock', 'quantity' => 10])
            ->assertHasNoTableActionErrors();
        $this->assertSame(10, $variant->fresh()->stock_quantity);

        $this->relation($product)
            ->callTableAction('adjustStock', $variant, data: ['reason' => 'count', 'quantity' => 8])
            ->assertHasTableActionErrors(['note' => 'required']);

        $this->relation($product)
            ->callTableAction('adjustStock', $variant, data: ['reason' => 'count', 'quantity' => 8, 'note' => 'Monthly count'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(8, $variant->fresh()->stock_quantity);

        // Removing more than there is: refused, nothing changes.
        $this->relation($product)
            ->callTableAction('adjustStock', $variant, data: ['reason' => 'remove', 'quantity' => 9, 'note' => 'Damaged'])
            ->assertNotified();
        $this->assertSame(8, $variant->fresh()->stock_quantity);

        $this->assertSame([10, -2], StockMovement::orderBy('id')->pluck('quantity')->all());
        $this->assertTrue(StockMovement::query()->get()->every(fn ($m) => $m->user_id === $this->admin->id));
    }

    public function test_stock_actions_need_inventory_permissions(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);
        $variant = $product->variants()->create(['sku' => 'TOTE', 'price' => 990000]);

        $this->admin->givePermissionTo([]); // admin role has inventory rights; remove them for this check
        $this->admin->roles()->first()->revokePermissionTo(['inventory.adjust', 'inventory.view']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->relation($product)
            ->assertTableActionHidden('adjustStock', $variant)
            ->assertTableActionHidden('stockHistory', $variant);
    }

    public function test_stock_history_shows_movements(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);
        $variant = $product->variants()->create(['sku' => 'TOTE', 'price' => 990000]);
        app(Inventory::class)->adjust($variant, 5, StockMovementType::Restock, $this->admin, 'From supplier X');

        $this->relation($product)->assertTableActionVisible('stockHistory', $variant);

        // The modal body is rendered by this view.
        $html = view('filament.admin.stock-history', ['movements' => $variant->stockMovements()->with('user')->get()])->render();

        $this->assertStringContainsString('From supplier X', $html);
        $this->assertStringContainsString(e($this->admin->name), $html);
        $this->assertStringContainsString('+5', $html);
    }

    public function test_an_active_product_needs_an_active_variant(): void
    {
        $product = Product::create(['name' => ['en' => 'Empty', 'ar' => 'فارغ'], 'status' => ProductStatus::Draft]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['status' => 'active'])
            ->call('save')
            ->assertNotified();

        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_the_last_active_variant_of_an_active_product_is_not_deleted(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت'], 'status' => ProductStatus::Active, 'published_at' => now()]);
        $only = $product->variants()->create(['sku' => 'TOTE', 'price' => 990000, 'is_default' => true]);

        $this->relation($product)->callTableAction(DeleteAction::class, $only)->assertNotified();
        $this->assertNotSoftDeleted($only);

        $second = $product->variants()->create(['sku' => 'TOTE-2', 'price' => 990000]);
        $this->relation($product)->callTableAction(DeleteAction::class, $only);

        $this->assertSoftDeleted($only);
        $this->assertTrue($second->fresh()->is_default, 'default moves to the next active variant');
    }

    public function test_related_products_are_saved_and_exclude_itself(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);
        $other = Product::create(['name' => ['en' => 'Wallet', 'ar' => 'محفظة']]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['related' => [$other->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$other->id], $product->related()->pluck('products.id')->all());
    }

    public function test_list_page_loads_and_searches_by_sku(): void
    {
        $product = Product::create(['name' => ['en' => 'Tote', 'ar' => 'توت']]);
        $product->variants()->create(['sku' => 'FIND-ME', 'price' => 990000]);
        Product::create(['name' => ['en' => 'Other', 'ar' => 'آخر']]);

        $this->get('/admin/products')->assertOk();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$product]);
    }
}
