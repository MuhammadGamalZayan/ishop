<?php

namespace App\Filament\Admin\Resources\Brands;

use App\Filament\Admin\Resources\Brands\Pages\CreateBrand;
use App\Filament\Admin\Resources\Brands\Pages\EditBrand;
use App\Filament\Admin\Resources\Brands\Pages\ListBrands;
use App\Filament\Admin\Resources\Brands\Schemas\BrandForm;
use App\Filament\Admin\Resources\Brands\Tables\BrandsTable;
use App\Filament\Admin\Support\GuardedDelete;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.brands.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.brands.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.catalog');
    }

    /** Brands are only soft deleted, and never while products still use them. */
    public static function deleteAction(): DeleteAction
    {
        return GuardedDelete::make(fn (Brand $brand): ?string => $brand->products()->exists()
            ? __('admin.brands.errors.has_products')
            : null);
    }

    public static function form(Schema $schema): Schema
    {
        return BrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
