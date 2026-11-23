<?php

namespace App\Filament\Admin\Resources\Governorates\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Governorates\GovernorateResource;
use Filament\Resources\Pages\EditRecord;

class EditGovernorate extends EditRecord
{
    protected static string $resource = GovernorateResource::class;

    protected function getHeaderActions(): array
    {
        return [GovernorateResource::deleteAction()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return TranslatableFields::fill($this->getRecord(), $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TranslatableFields::clean($data, ['name']);
    }
}
