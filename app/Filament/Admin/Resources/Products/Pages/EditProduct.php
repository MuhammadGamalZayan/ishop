<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->modalDescription(__('admin.products.help.delete')),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return TranslatableFields::fill($this->getRecord(), $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === ProductStatus::Active->value && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        return TranslatableFields::clean($data, $this->getRecord()->getTranslatableAttributes());
    }

    /** A product can't go live with nothing to sell. */
    protected function beforeSave(): void
    {
        /** @var Product $product */
        $product = $this->getRecord();
        $goingLive = ($this->data['status'] ?? null) === ProductStatus::Active->value;

        if ($goingLive && ! $product->variants()->where('is_active', true)->where('price', '>', 0)->exists()) {
            Notification::make()
                ->danger()
                ->title(__('admin.common.action_blocked'))
                ->body(__('admin.products.errors.no_active_variant'))
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
