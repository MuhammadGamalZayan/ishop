<?php

namespace App\Filament\Admin\Resources\ProductAttributes\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\ProductAttributes\ProductAttributeResource;
use Filament\Resources\Pages\EditRecord;

class EditProductAttribute extends EditRecord
{
    protected static string $resource = ProductAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProductAttributeResource::deleteAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return TranslatableFields::fill($this->getRecord(), $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TranslatableFields::clean($data, $this->getRecord()->getTranslatableAttributes());
    }
}
