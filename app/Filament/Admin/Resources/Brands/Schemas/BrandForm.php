<?php

namespace App\Filament\Admin\Resources\Brands\Schemas;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Support\SlugInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Section::make(__('admin.brands.sections.content'))
                            ->schema([
                                // Brand names are proper nouns, written the same in both languages.
                                TextInput::make('name')
                                    ->label(__('admin.brands.fields.name'))
                                    ->helperText(__('admin.brands.help.name'))
                                    ->required()
                                    ->maxLength(120),
                                TranslatableFields::tabs('description', fn (string $locale): array => [
                                    Textarea::make("description.{$locale}")
                                        ->label(__('admin.brands.fields.description'))
                                        ->rows(4)
                                        ->maxLength(2000),
                                ]),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make(__('admin.common.settings'))
                            ->schema([
                                Toggle::make('is_active')
                                    ->label(__('admin.common.is_active'))
                                    ->default(true),
                                SlugInput::make('brands'),
                                SpatieMediaLibraryFileUpload::make('logo')
                                    ->label(__('admin.brands.fields.logo'))
                                    ->collection('logo')
                                    ->image()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(1024),
                            ]),
                    ]),
            ]);
    }
}
