<?php

namespace App\Filament\Admin\Resources\ProductAttributes\Pages;

use App\Filament\Admin\Resources\ProductAttributes\ProductAttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductAttributes extends ListRecords
{
    protected static string $resource = ProductAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
