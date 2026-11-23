<?php

namespace App\Filament\Admin\Resources\Brands\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Brands\BrandResource;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BrandResource::deleteAction(),
            RestoreAction::make(),
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
