<?php

namespace App\Filament\Admin\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Orders\Pages\ListOrders;
use App\Filament\Admin\Resources\Orders\Pages\ViewOrder;
use App\Filament\Admin\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Admin\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Orders are read-only records of what was sold. Staff can only move them
 * through their statuses (via OrderWorkflow); items, prices and addresses
 * are snapshots and are never edited.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getModelLabel(): string
    {
        return __('admin.orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.orders.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.sales');
    }

    /** Orders waiting for a confirmation call. */
    public static function getNavigationBadge(): ?string
    {
        $pending = Order::query()->where('status', OrderStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer_phone', 'customer_email'];
    }

    public static function statusColor(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'warning',
            OrderStatus::Confirmed, OrderStatus::Processing => 'info',
            OrderStatus::Shipped => 'primary',
            OrderStatus::Delivered => 'success',
            OrderStatus::Cancelled, OrderStatus::Returned => 'gray',
        };
    }

    public static function paymentColor(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Paid => 'success',
            PaymentStatus::Pending => 'warning',
            PaymentStatus::Failed => 'danger',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
