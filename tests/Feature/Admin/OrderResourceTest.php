<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\PlaceOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Checkout\CheckoutTestCase;

class OrderResourceTest extends CheckoutTestCase
{
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->staff = User::factory()->withTwoFactor()->create(['is_active' => true, 'name' => 'Mona Staff']);
        $this->staff->assignRole('admin');
    }

    private function order(int $stock = 5, int $quantity = 2): Order
    {
        $variant = $this->variant(price: 100000, stock: $stock);
        $this->cart([$variant->id => $quantity]);

        return app(PlaceOrder::class)->handle($this->customer, $this->data(['customerNote' => 'Ring twice <b>please</b>']));
    }

    public function test_list_opens_on_orders_to_confirm_and_shows_a_badge(): void
    {
        $pending = $this->order();
        $this->actingAs($this->staff);

        $this->get('/admin/orders')->assertOk();
        $this->assertSame('1', OrderResource::getNavigationBadge());

        Livewire::test(ListOrders::class)
            ->assertSet('activeTab', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->searchTable($pending->number)
            ->assertCanSeeTableRecords([$pending])
            ->searchTable('01001234567')
            ->assertCanSeeTableRecords([$pending]);
    }

    public function test_order_page_shows_snapshots_and_escapes_customer_text(): void
    {
        $order = $this->order();
        $this->actingAs($this->staff);

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Smouha')
            ->assertSee('12 Fawzy Moaz St')
            ->assertSee('2,000.00 EGP', false)
            ->assertSee('Ring twice &lt;b&gt;please&lt;/b&gt;', false)
            ->assertDontSee('Ring twice <b>please</b>', false);
    }

    public function test_staff_walk_an_order_to_delivered(): void
    {
        $order = $this->order();
        $this->actingAs($this->staff);

        $page = Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('to_confirmed')
            ->assertActionHidden('to_delivered')
            ->callAction('to_confirmed', data: ['comment' => 'Called, customer confirmed'])
            ->assertHasNoActionErrors();

        foreach (['to_processing', 'to_shipped', 'to_delivered'] as $action) {
            Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction($action);
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $confirmed = $order->statusHistory()->where('to_status', OrderStatus::Confirmed)->sole();
        $this->assertSame([$this->staff->id, 'Called, customer confirmed'], [$confirmed->user_id, $confirmed->comment]);
    }

    public function test_cancelling_needs_a_reason_and_restocks(): void
    {
        $order = $this->order(stock: 5, quantity: 2);
        $variantId = $order->items()->value('product_variant_id');
        $this->actingAs($this->staff);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('to_cancelled', data: ['comment' => ''])
            ->assertHasActionErrors(['comment' => 'required']);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('to_cancelled', data: ['comment' => 'Customer did not answer 3 calls'])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(5, \App\Models\ProductVariant::find($variantId)->stock_quantity);
    }

    public function test_without_status_permission_the_buttons_are_hidden(): void
    {
        $order = $this->order();
        $viewer = User::factory()->withTwoFactor()->create(['is_active' => true]);
        $viewer->assignRole('admin');
        $viewer->roles()->first()->revokePermissionTo('orders.update-status');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($viewer);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('to_confirmed')
            ->assertActionHidden('to_cancelled');
    }

    public function test_orders_cannot_be_created_edited_or_deleted_from_the_panel(): void
    {
        $order = $this->order();
        $this->actingAs($this->staff);

        $this->assertFalse(OrderResource::hasPage('create'));
        $this->assertFalse(OrderResource::hasPage('edit'));
        $this->assertFalse($this->staff->can('delete', $order) && ! $this->staff->hasRole('super-admin'));
        $this->get('/admin/orders/create')->assertNotFound();
    }

    public function test_customers_cannot_open_the_orders_screen(): void
    {
        $this->order();
        $this->customer->assignRole('customer');

        $this->actingAs($this->customer)->get('/admin/orders')->assertForbidden();
    }
}
