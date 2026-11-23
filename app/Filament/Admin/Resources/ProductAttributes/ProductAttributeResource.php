<?php

namespace App\Filament\Admin\Resources\ProductAttributes;

use App\Enums\AttributeType;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\ProductAttributes\Pages\CreateProductAttribute;
use App\Filament\Admin\Resources\ProductAttributes\Pages\EditProductAttribute;
use App\Filament\Admin\Resources\ProductAttributes\Pages\ListProductAttributes;
use App\Filament\Admin\Resources\ProductAttributes\RelationManagers\ValuesRelationManager;
use App\Filament\Admin\Support\GuardedDelete;
use App\Filament\Admin\Support\SlugInput;
use App\Models\ProductAttribute;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Option types such as Colour or Size, and their values (Black, Navy…).
 * Product variants are built from these values.
 */
class ProductAttributeResource extends Resource
{
    protected static ?string $model = ProductAttribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'attributes';

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('admin.attributes.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.attributes.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.catalog');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof ProductAttribute ? $record->name : null;
    }

    public static function deleteAction(): DeleteAction
    {
        return GuardedDelete::make(fn (ProductAttribute $attribute): ?string => $attribute->values()->whereHas('variants')->exists()
            ? __('admin.attributes.errors.in_use')
            : null);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TranslatableFields::tabs('name', fn (string $locale): array => [
                            TextInput::make("name.{$locale}")
                                ->label(__('admin.attributes.fields.name'))
                                ->required()
                                ->maxLength(60),
                        ])->columnSpanFull(),
                        Select::make('type')
                            ->label(__('admin.attributes.fields.type'))
                            ->options(collect(AttributeType::cases())->mapWithKeys(
                                fn (AttributeType $type): array => [$type->value => __("admin.attributes.types.{$type->value}")],
                            ))
                            ->helperText(__('admin.attributes.help.type'))
                            ->default(AttributeType::Select->value)
                            ->required()
                            ->native(false)
                            ->live(),
                        SlugInput::make('attributes'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('values'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.attributes.fields.name'))
                    ->weight('medium')
                    ->searchable(query: TranslatableFields::search('name')),
                TextColumn::make('type')
                    ->label(__('admin.attributes.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (AttributeType $state): string => __("admin.attributes.types.{$state->value}")),
                TextColumn::make('slug')
                    ->label(__('admin.common.slug'))
                    ->color('gray'),
                TextColumn::make('values_count')
                    ->label(__('admin.attributes.fields.values_count'))
                    ->numeric()
                    ->alignCenter(),
            ])
            ->recordActions([
                EditAction::make(),
                self::deleteAction(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductAttributes::route('/'),
            'create' => CreateProductAttribute::route('/create'),
            'edit' => EditProductAttribute::route('/{record}/edit'),
        ];
    }
}
