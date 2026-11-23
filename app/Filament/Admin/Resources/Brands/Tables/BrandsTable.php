<?php

namespace App\Filament\Admin\Resources\Brands\Tables;

use App\Filament\Admin\Resources\Brands\BrandResource;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('products'))
            ->defaultSort('name')
            ->columns([
                SpatieMediaLibraryImageColumn::make('logo')
                    ->label(__('admin.brands.fields.logo'))
                    ->collection('logo')
                    ->imageSize(36),
                TextColumn::make('name')
                    ->label(__('admin.brands.fields.name'))
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('admin.common.slug'))
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('products_count')
                    ->label(__('admin.common.products_count'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label(__('admin.common.is_active'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.common.is_active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                BrandResource::deleteAction(),
                RestoreAction::make(),
            ]);
    }
}
