<?php

namespace App\Filament\Admin\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('items')->with('address'))
            ->defaultSort('placed_at', 'desc')
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')
                    ->label(__('admin.orders.fields.number'))
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('placed_at')
                    ->label(__('admin.orders.fields.placed_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label(__('admin.orders.fields.customer'))
                    ->description(fn (Order $record): string => $record->customer_phone)
                    ->searchable(['customer_name', 'customer_phone', 'customer_email']),
                TextColumn::make('area')
                    ->label(__('admin.orders.fields.area'))
                    ->state(fn (Order $record): ?string => $record->address
                        ? $record->address->area_name.', '.$record->address->governorate_name
                        : null)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('admin.orders.fields.status'))
                    ->badge()
                    ->color(fn (OrderStatus $state): string => OrderResource::statusColor($state))
                    ->formatStateUsing(fn (OrderStatus $state): string => __("checkout.statuses.{$state->value}")),
                TextColumn::make('payment_status')
                    ->label(__('admin.orders.fields.payment'))
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => OrderResource::paymentColor($state))
                    ->formatStateUsing(fn (PaymentStatus $state): string => __("admin.orders.payment_statuses.{$state->value}"))
                    ->description(fn (Order $record): string => __("admin.orders.methods.{$record->payment_method->value}")),
                TextColumn::make('items_count')
                    ->label(__('admin.orders.fields.items'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('grand_total')
                    ->label(__('admin.orders.fields.total'))
                    ->money(config('ishop.currency'), divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->label(__('admin.orders.fields.method'))
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(
                        fn (PaymentMethod $m): array => [$m->value => __("admin.orders.methods.{$m->value}")],
                    )),
                SelectFilter::make('payment_status')
                    ->label(__('admin.orders.fields.payment'))
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(
                        fn (PaymentStatus $s): array => [$s->value => __("admin.orders.payment_statuses.{$s->value}")],
                    )),
                Filter::make('placed_between')
                    ->schema([
                        DatePicker::make('from')->label(__('admin.orders.filters.from')),
                        DatePicker::make('until')->label(__('admin.orders.filters.until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('placed_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('placed_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
