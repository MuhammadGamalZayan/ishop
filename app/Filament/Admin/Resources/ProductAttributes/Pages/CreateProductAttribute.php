<?php

namespace App\Filament\Admin\Resources\ProductAttributes\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\ProductAttributes\ProductAttributeResource;
use App\Models\ProductAttribute;
use Filament\Resources\Pages\CreateRecord;

class CreateProductAttribute extends CreateRecord
{
    protected static string $resource = ProductAttributeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatableFields::clean($data, (new ProductAttribute)->getTranslatableAttributes());
    }

    // Go straight to the edit page, where the values are added.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
