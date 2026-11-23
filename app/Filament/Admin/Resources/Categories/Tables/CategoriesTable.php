<?php

namespace App\Filament\Admin\Resources\Categories\Tables;

use App\Filament\Admin\Resources\Categories\Actions\CategoryActions;
use App\Models\Category;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parent')->withCount(['children', 'products']))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                SpatieMediaLibraryImageColumn::make('image')
                    ->label(__('admin.categories.fields.image'))
                    ->collection('image')
                    ->square()
                    ->imageSize(40),
                TextColumn::make('name')
                    ->label(__('admin.categories.fields.name'))
                    ->weight('medium')
                    ->description(fn (Category $record): ?string => $record->parent?->name)
                    // Names are JSON ({"en": …, "ar": …}); search both languages, case-insensitively.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $q) use ($search): void {
                        foreach (config('ishop.locales') as $locale) {
                            $q->orWhereRaw(
                                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$locale}\"'))) LIKE ?",
                                ['%'.mb_strtolower($search).'%'],
                            );
                        }
                    })),
                TextColumn::make('slug')
                    ->label(__('admin.categories.fields.slug'))
                    ->prefix('/')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('children_count')
                    ->label(__('admin.categories.fields.children_count'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('products_count')
                    ->label(__('admin.categories.fields.products_count'))
                    ->numeric()
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label(__('admin.categories.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.categories.fields.is_active')),
                SelectFilter::make('parent_id')
                    ->label(__('admin.categories.fields.parent'))
                    ->relationship('parent', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (Category $category): string => $category->name),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                CategoryActions::delete(),
                CategoryActions::restore(),
            ]);
    }
}
