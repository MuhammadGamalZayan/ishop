<?php

namespace App\Filament\Admin\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('admin.orders.sections.items'))->schema([
                    ViewEntry::make('items')
                        ->hiddenLabel()
                        ->view('filament.admin.order-items'),
                ]),
                Section::make(__('admin.orders.sections.note'))
                    ->visible(fn (Order $record): bool => filled($record->customer_note))
                    ->schema([
                        TextEntry::make('customer_note')->hiddenLabel(),
                    ]),
                Section::make(__('admin.orders.sections.history'))->schema([
                    ViewEntry::make('statusHistory')
                        ->hiddenLabel()
                        ->view('filament.admin.order-history'),
                ]),
            ]),

            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('admin.orders.sections.order'))->schema([
                    TextEntry::make('number')->label(__('admin.orders.fields.number'))->fontFamily('mono')->copyable(),
                    TextEntry::make('placed_at')->label(__('admin.orders.fields.placed_at'))->dateTime(),
                    TextEntry::make('status')
                        ->label(__('admin.orders.fields.status'))
                        ->badge()
                        ->color(fn (OrderStatus $state): string => OrderResource::statusColor($state))
                        ->formatStateUsing(fn (OrderStatus $state): string => __("checkout.statuses.{$state->value}")),
                    TextEntry::make('payment_method')
                        ->label(__('admin.orders.fields.method'))
                        ->formatStateUsing(fn (PaymentMethod $state): string => __("admin.orders.methods.{$state->value}")),
                    TextEntry::make('payment_status')
                        ->label(__('admin.orders.fields.payment'))
                        ->badge()
                        ->color(fn (PaymentStatus $state): string => OrderResource::paymentColor($state))
                        ->formatStateUsing(fn (PaymentStatus $state): string => __("admin.orders.payment_statuses.{$state->value}")),
                    TextEntry::make('coupon_code')
                        ->label(__('admin.orders.fields.coupon'))
                        ->fontFamily('mono')
                        ->visible(fn (Order $record): bool => filled($record->coupon_code)),
                ]),
                Section::make(__('admin.orders.sections.customer'))->schema([
                    TextEntry::make('customer_name')->label(__('admin.orders.fields.name')),
                    TextEntry::make('customer_phone')
                        ->label(__('admin.orders.fields.phone'))
                        ->copyable()
                        ->url(fn (Order $record): string => 'tel:'.$record->customer_phone),
                    TextEntry::make('customer_email')->label(__('admin.orders.fields.email'))->copyable(),
                ]),
                Section::make(__('admin.orders.sections.address'))->schema([
                    TextEntry::make('address_text')
                        ->hiddenLabel()
                        ->state(fn (Order $record): array => self::addressLines($record))
                        ->listWithLineBreaks(),
                ]),
            ]),
        ]);
    }

    /** @return list<string> */
    public static function addressLines(Order $order): array
    {
        $a = $order->address;

        if ($a === null) {
            return [];
        }

        $details = array_filter([
            __('admin.orders.address.building', ['value' => $a->building]),
            $a->floor ? __('admin.orders.address.floor', ['value' => $a->floor]) : null,
            $a->apartment ? __('admin.orders.address.apartment', ['value' => $a->apartment]) : null,
        ]);

        return array_values(array_filter([
            trim($a->first_name.' '.$a->last_name).' · '.$a->phone,
            $a->street,
            implode('، ', $details),
            $a->landmark ? __('admin.orders.address.landmark', ['value' => $a->landmark]) : null,
            $a->area_name.' — '.$a->governorate_name,
        ]));
    }
}
