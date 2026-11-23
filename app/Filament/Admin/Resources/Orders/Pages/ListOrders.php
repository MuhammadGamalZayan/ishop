<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** One tab per status; "To confirm" opens first when there is anything waiting. */
    public function getTabs(): array
    {
        $counts = Order::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = ['all' => Tab::make(__('admin.orders.tabs.all'))];

        foreach (OrderStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            $tabs[$status->value] = Tab::make(__("admin.orders.tabs.{$status->value}"))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status))
                ->badge($count ?: null)
                ->badgeColor($status === OrderStatus::Pending ? 'warning' : 'gray');
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return Order::query()->where('status', OrderStatus::Pending)->exists() ? OrderStatus::Pending->value : 'all';
    }
}
