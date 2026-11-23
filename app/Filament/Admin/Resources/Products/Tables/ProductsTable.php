<?php

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Models\Category;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('brand')
                ->withCount('variants')
                ->withMin('variants', 'price')
                ->withMax('variants', 'price')
                ->withSum(['variants as stock_total' => fn (Builder $q) => $q->where('track_inventory', true)], 'stock_quantity'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                SpatieMediaLibraryImageColumn::make('images')
                    ->label('')
                    ->collection('images')
                    ->conversion('thumb')
                    ->limit(1)
                    ->square()
                    ->imageSize(44),
                TextColumn::make('name')
                    ->label(__('admin.products.fields.name'))
                    ->weight('medium')
                    ->description(fn (Product $record): ?string => $record->brand?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => TranslatableFields::search('name')($q, $search)
                        ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', '%'.$search.'%')))),
                TextColumn::make('status')
                    ->label(__('admin.products.fields.status'))
                    ->badge()
                    ->color(fn (ProductStatus $state): string => match ($state) {
                        ProductStatus::Active => 'success',
                        ProductStatus::Draft => 'gray',
                        ProductStatus::Archived => 'warning',
                    })
                    ->formatStateUsing(fn (ProductStatus $state): string => __("admin.products.statuses.{$state->value}")),
                TextColumn::make('price_range')
                    ->label(__('admin.products.fields.price'))
                    ->state(fn (Product $record): string => self::priceRange($record)),
                TextColumn::make('stock_total')
                    ->label(__('admin.products.fields.stock'))
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => (int) $state === 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->label(__('admin.products.fields.variants_count'))
                    ->numeric()
                    ->alignCenter(),
                IconColumn::make('is_featured')
                    ->label(__('admin.products.fields.is_featured'))
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.products.fields.status'))
                    ->options(collect(ProductStatus::cases())->mapWithKeys(
                        fn (ProductStatus $s): array => [$s->value => __("admin.products.statuses.{$s->value}")],
                    )),
                SelectFilter::make('categories')
                    ->label(__('admin.products.fields.categories'))
                    ->relationship('categories', 'slug')
                    ->getOptionLabelFromRecordUsing(fn (Category $c): string => $c->name)
                    ->multiple()
                    ->preload(),
                SelectFilter::make('brand')
                    ->label(__('admin.products.fields.brand'))
                    ->relationship('brand', 'name')
                    ->preload(),
                Filter::make('out_of_stock')
                    ->label(__('admin.products.filters.out_of_stock'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('variants', fn (Builder $q) => $q
                        ->where('is_active', true)->where('track_inventory', true)->where('stock_quantity', 0))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ]);
    }

    private static function priceRange(Product $record): string
    {
        $min = $record->variants_min_price;
        $max = $record->variants_max_price;

        if ($min === null) {
            return '—';
        }

        $format = fn (int $piastres): string => number_format($piastres / 100, 2).' '.config('ishop.currency');

        return $min === $max ? $format((int) $min) : $format((int) $min).' – '.$format((int) $max);
    }
}
