<?php

namespace App\Filament\Admin\Resources\Governorates\Pages;

use App\Filament\Admin\Resources\Governorates\GovernorateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGovernorates extends ListRecords
{
    protected static string $resource = GovernorateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
