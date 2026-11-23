<?php

namespace App\Filament\Admin\Resources\Categories\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatableFields::clean($data, (new Category)->getTranslatableAttributes());
    }
}
