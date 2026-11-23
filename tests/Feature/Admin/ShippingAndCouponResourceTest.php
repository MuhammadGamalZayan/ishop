<?php

namespace Tests\Feature\Admin;

use App\Enums\CouponType;
use App\Filament\Admin\Resources\Coupons\CouponResource;
use App\Filament\Admin\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Admin\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Admin\Resources\Governorates\Pages\EditGovernorate;
use App\Filament\Admin\Resources\Governorates\Pages\ListGovernorates;
use App\Filament\Admin\Resources\Governorates\RelationManagers\ShippingAreasRelationManager;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\User;
use Database\Seeders\GovernorateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ShippingAndCouponResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, GovernorateSeeder::class]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    private function areas(Governorate $governorate)
    {
        return Livewire::test(ShippingAreasRelationManager::class, ['ownerRecord' => $governorate, 'pageClass' => EditGovernorate::class]);
    }

    // ---- Shipping -------------------------------------------------------

    public function test_the_27_governorates_are_listed(): void
    {
        $this->get('/admin/shipping')->assertOk();

        Livewire::test(ListGovernorates::class)->assertCountTableRecords(27);
    }

    public function test_admin_adds_an_area_with_a_price_and_a_free_one(): void
    {
        $cairo = Governorate::query()->orderBy('id')->first();

        $this->areas($cairo)
            ->callTableAction(CreateAction::class, data: ['name' => ['en' => 'Nasr City', 'ar' => 'مدينة نصر'], 'shipping_price' => '60', 'is_active' => true])
            ->assertHasNoTableActionErrors();
        $this->areas($cairo)
            ->callTableAction(CreateAction::class, data: ['name' => ['en' => 'Downtown', 'ar' => 'وسط البلد'], 'shipping_price' => '0', 'is_active' => true])
            ->assertHasNoTableActionErrors();

        $this->assertSame([6000, 0], $cairo->shippingAreas()->orderBy('id')->pluck('shipping_price')->all());
        $this->assertSame('مدينة نصر', $cairo->shippingAreas()->orderBy('id')->first()->getTranslation('name', 'ar'));
    }

    public function test_same_price_for_all_areas(): void
    {
        $alex = Governorate::query()->orderBy('id')->skip(1)->first();
        $alex->shippingAreas()->createMany([
            ['name' => ['en' => 'Smouha', 'ar' => 'سموحة'], 'shipping_price' => 5000],
            ['name' => ['en' => 'Miami', 'ar' => 'ميامي'], 'shipping_price' => 7000],
        ]);

        $this->areas($alex)
            ->callTableAction('samePrice', data: ['shipping_price' => '55.5'])
            ->assertHasNoTableActionErrors();

        $this->assertSame([5550, 5550], $alex->shippingAreas()->pluck('shipping_price')->all());
    }

    public function test_areas_with_saved_addresses_are_not_deleted(): void
    {
        $governorate = Governorate::query()->first();
        $area = $governorate->shippingAreas()->create(['name' => ['en' => 'A', 'ar' => 'أ'], 'shipping_price' => 5000]);

        Address::forceCreate([
            'user_id' => $this->admin->id,
            'governorate_id' => $governorate->id,
            'shipping_area_id' => $area->id,
        ] + $this->addressColumns());

        $this->areas($governorate)->callTableAction(DeleteAction::class, $area)->assertNotified();
        $this->assertModelExists($area);

        Livewire::test(EditGovernorate::class, ['record' => $governorate->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();
        $this->assertModelExists($governorate);
    }

    /** Whatever other NOT NULL columns the addresses table has. */
    private function addressColumns(): array
    {
        $columns = [];
        foreach (Schema::getColumns('addresses') as $column) {
            if (! $column['nullable'] && $column['default'] === null && ! in_array($column['name'], ['id', 'user_id', 'governorate_id', 'shipping_area_id'], true)) {
                $columns[$column['name']] = str_contains($column['type_name'], 'int') ? 1 : 'x';
            }
        }

        return $columns;
    }

    // ---- Coupons --------------------------------------------------------

    public function test_percentage_coupon_is_stored_in_basis_points_and_uppercased(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'welcome10',
                'type' => 'percentage',
                'percent' => '12.5',
                'max_discount' => '500',
                'min_subtotal' => '1000',
                'usage_limit' => 100,
                'usage_limit_per_user' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::sole();
        $this->assertSame('WELCOME10', $coupon->code);
        $this->assertSame(CouponType::Percentage, $coupon->type);
        $this->assertSame(1250, $coupon->value);
        $this->assertSame(50000, $coupon->max_discount);
        $this->assertSame(100000, $coupon->min_subtotal);
        $this->assertSame('12.5% (up to 500.00)', CouponResource::describeValue($coupon));
    }

    public function test_fixed_coupon_round_trips_through_the_edit_form(): void
    {
        $coupon = Coupon::create(['code' => 'FIFTY', 'type' => CouponType::Fixed, 'value' => 5000]);

        Livewire::test(EditCoupon::class, ['record' => $coupon->getRouteKey()])
            ->assertSchemaStateSet(['amount' => '50.00'])
            ->fillForm(['amount' => '75'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(7500, $coupon->fresh()->value);
    }

    public function test_free_shipping_has_no_value_and_no_cap(): void
    {
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'SHIPFREE', 'type' => 'free_shipping'])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::sole();
        $this->assertSame(0, $coupon->value);
        $this->assertNull($coupon->max_discount);
    }

    public function test_coupon_validation(): void
    {
        Coupon::create(['code' => 'TAKEN', 'type' => CouponType::Fixed, 'value' => 100]);

        $cases = [
            [['code' => 'taken', 'type' => 'fixed', 'amount' => '10'], 'code'],
            [['code' => 'BAD CODE', 'type' => 'fixed', 'amount' => '10'], 'code'],
            [['code' => 'P1', 'type' => 'percentage', 'percent' => '150'], 'percent'],
            [['code' => 'P2', 'type' => 'percentage'], 'percent'],
            [['code' => 'F1', 'type' => 'fixed'], 'amount'],
            [['code' => 'D1', 'type' => 'fixed', 'amount' => '10', 'starts_at' => '2026-12-10 10:00', 'expires_at' => '2026-12-01 10:00'], 'expires_at'],
            [['code' => 'U1', 'type' => 'fixed', 'amount' => '10', 'usage_limit' => 5, 'usage_limit_per_user' => 6], 'usage_limit_per_user'],
        ];

        foreach ($cases as [$data, $field]) {
            Livewire::test(CreateCoupon::class)->fillForm($data)->call('create')->assertHasFormErrors([$field]);
        }

        // Optional fields left empty are fine.
        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'OK', 'type' => 'fixed', 'amount' => '10', 'usage_limit_per_user' => 2, 'expires_at' => '2027-01-01 00:00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Coupon::count());
    }

    public function test_deleted_codes_cannot_be_reused_by_mistake(): void
    {
        Coupon::create(['code' => 'OLD', 'type' => CouponType::Fixed, 'value' => 100])->delete();

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => 'old', 'type' => 'fixed', 'amount' => '10'])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    public function test_coupon_status(): void
    {
        $make = fn (array $a) => Coupon::create($a + ['code' => 'C'.uniqid(), 'type' => CouponType::Fixed, 'value' => 100])->fresh();

        $this->assertSame('active', CouponResource::status($make([])));
        $this->assertSame('disabled', CouponResource::status($make(['is_active' => false])));
        $this->assertSame('scheduled', CouponResource::status($make(['starts_at' => now()->addDay()])));
        $this->assertSame('expired', CouponResource::status($make(['expires_at' => now()->subMinute()])));
    }

    public function test_customers_cannot_open_shipping_or_coupons(): void
    {
        $customer = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/admin/shipping')->assertForbidden();
        $this->actingAs($customer)->get('/admin/coupons')->assertForbidden();
    }
}
