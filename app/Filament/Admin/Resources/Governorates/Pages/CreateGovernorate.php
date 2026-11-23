<?php

namespace App\Filament\Admin\Resources\Governorates\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Governorates\GovernorateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGovernorate extends CreateRecord
{
    protected static string $resource = GovernorateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TranslatableFields::clean($data, ['name']);
    }

    // Straight to the edit page, where the areas and prices are added.
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
