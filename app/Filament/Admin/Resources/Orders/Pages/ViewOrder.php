<?php

namespace App\Filament\Admin\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Filament\Admin\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderWorkflow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One button per next status the order may take (OrderStatus decides which).
 * Every change goes through OrderWorkflow: locked, recorded in the history,
 * restock on cancel, COD marked paid on delivery.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.orders.title', ['number' => $this->getRecord()->number]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->move(OrderStatus::Confirmed, Heroicon::OutlinedPhone, 'success'),
            $this->move(OrderStatus::Processing, Heroicon::OutlinedArchiveBox, 'primary'),
            $this->move(OrderStatus::Shipped, Heroicon::OutlinedTruck, 'primary'),
            $this->move(OrderStatus::Delivered, Heroicon::OutlinedCheckCircle, 'success'),
            ActionGroup::make([
                $this->move(OrderStatus::Cancelled, Heroicon::OutlinedXCircle, 'danger', commentRequired: true),
                $this->move(OrderStatus::Returned, Heroicon::OutlinedArrowUturnLeft, 'warning', commentRequired: true),
            ])->label(__('admin.orders.actions.more'))->button()->color('gray'),
        ];
    }

    private function move(OrderStatus $to, Heroicon $icon, string $color, bool $commentRequired = false): Action
    {
        return Action::make('to_'.$to->value)
            ->label(__("admin.orders.actions.{$to->value}"))
            ->icon($icon)
            ->color($color)
            ->visible(fn (Order $record): bool => $record->status->canTransitionTo($to))
            ->authorize(fn (Order $record): bool => auth()->user()->can('updateStatus', $record))
            ->requiresConfirmation()
            ->modalHeading(__("admin.orders.actions.{$to->value}"))
            ->modalDescription(__("admin.orders.confirm.{$to->value}"))
            ->schema([
                Textarea::make('comment')
                    ->label($commentRequired ? __('admin.orders.fields.reason') : __('admin.orders.fields.comment'))
                    ->rows(2)
                    ->maxLength(255)
                    ->required($commentRequired),
            ])
            ->action(function (array $data, Order $record, Action $action) use ($to): void {
                try {
                    app(OrderWorkflow::class)->transition($record, $to, auth()->user(), $data['comment'] ?? null);
                } catch (InvalidOrderTransitionException $e) {
                    // Someone else changed it a moment ago.
                    Notification::make()->danger()->title(__('admin.common.action_blocked'))->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                $this->refreshFormData(['status', 'payment_status']);
                $this->getRecord()->refresh();

                Notification::make()
                    ->success()
                    ->title(__('admin.orders.notifications.moved', ['status' => __("checkout.statuses.{$to->value}")]))
                    ->send();
            });
    }
}
