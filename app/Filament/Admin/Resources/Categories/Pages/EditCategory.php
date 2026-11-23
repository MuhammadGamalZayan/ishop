<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Categories\Actions\CategoryActions;
use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CategoryActions::delete(),
            CategoryActions::restore(),
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
