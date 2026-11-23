<?php

namespace App\Filament\Admin\Resources\Brands\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Brands\BrandResource;
use App\Models\Brand;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatableFields::clean($data, (new Brand)->getTranslatableAttributes());
    }
}
