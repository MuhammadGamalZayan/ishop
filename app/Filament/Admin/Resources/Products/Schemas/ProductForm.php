<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Enums\AttributeType;
use App\Enums\ProductStatus;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Support\MoneyInput;
use App\Filament\Admin\Support\SlugInput;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        self::content(),
                        self::images(),
                        self::firstVariant(),
                        self::seo(),
                    ]),
                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        self::publishing(),
                        self::organisation(),
                    ]),
            ]);
    }

    private static function content(): Section
    {
        return Section::make(__('admin.products.sections.content'))
            ->schema([
                TranslatableFields::tabs('content', fn (string $locale): array => [
                    TextInput::make("name.{$locale}")
                        ->label(__('admin.products.fields.name'))
                        ->required()
                        ->maxLength(160),
                    Textarea::make("short_description.{$locale}")
                        ->label(__('admin.products.fields.short_description'))
                        ->helperText(__('admin.products.help.short_description'))
                        ->rows(2)
                        ->maxLength(300),
                    Textarea::make("description.{$locale}")
                        ->label(__('admin.products.fields.description'))
                        ->rows(8)
                        ->maxLength(10000),
                ]),
            ]);
    }

    private static function images(): Section
    {
        $upload = fn (string $name, string $collection): SpatieMediaLibraryFileUpload => SpatieMediaLibraryFileUpload::make($name)
            ->collection($collection)
            ->multiple()
            ->reorderable()
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)
            ->maxFiles(12)
            ->panelLayout('grid');

        return Section::make(__('admin.products.sections.images'))
            ->description(__('admin.products.help.images'))
            ->schema(fn (?Product $record): array => [
                $upload('images', 'images')->label(__('admin.products.fields.general_images')),
                // One group per colour the product is sold in (appears once variants exist).
                ...self::colourValues($record)->map(
                    fn (ProductAttributeValue $colour) => $upload('images_colour_'.$colour->getKey(), 'color-'.$colour->getKey())
                        ->label(__('admin.products.fields.colour_images', ['colour' => $colour->value]))
                )->all(),
            ]);
    }

    /** Only on create: price/SKU/stock for a simple product, or the options to generate. */
    private static function firstVariant(): Section
    {
        return Section::make(__('admin.products.sections.pricing'))
            ->visibleOn('create')
            ->schema([
                Toggle::make('has_options')
                    ->label(__('admin.products.fields.has_options'))
                    ->helperText(__('admin.products.help.has_options'))
                    ->live()
                    ->dehydrated(false),

                Grid::make(2)->schema([
                    MoneyInput::make('first.price')
                        ->label(fn (Get $get): string => $get('has_options') ? __('admin.products.fields.price_all') : __('admin.products.fields.price'))
                        ->required()
                        ->minValue(0.01),
                    MoneyInput::make('first.compare_at_price')
                        ->label(__('admin.variants.fields.compare_at_price'))
                        ->helperText(__('admin.variants.help.compare_at_price'))
                        ->gt('first.price'),
                    TextInput::make('first.sku')
                        ->label(fn (Get $get): string => $get('has_options') ? __('admin.products.fields.sku_prefix') : __('admin.variants.fields.sku'))
                        ->helperText(__('admin.products.help.sku'))
                        ->maxLength(40)
                        ->regex('/^[A-Za-z0-9-]+$/')
                        ->unique('product_variants', 'sku'),
                    TextInput::make('first.stock')
                        ->label(__('admin.products.fields.initial_stock'))
                        ->helperText(__('admin.products.help.initial_stock'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100000)
                        ->default(0)
                        ->hidden(fn (Get $get): bool => (bool) $get('has_options')),
                ]),

                Group::make(fn (): array => ProductAttribute::query()->with('values')->orderBy('sort_order')->get()
                    ->map(fn (ProductAttribute $attribute): CheckboxList => CheckboxList::make('options.'.$attribute->getKey())
                        ->label($attribute->name)
                        ->options($attribute->values->mapWithKeys(fn (ProductAttributeValue $v): array => [$v->getKey() => $v->value]))
                        ->columns(4)
                        ->bulkToggleable())
                    ->all())
                    ->visible(fn (Get $get): bool => (bool) $get('has_options')),
            ]);
    }

    private static function seo(): Section
    {
        return Section::make(__('admin.categories.sections.seo'))
            ->description(__('admin.products.help.seo'))
            ->collapsible()
            ->collapsed()
            ->schema([
                TranslatableFields::tabs('seo', fn (string $locale): array => [
                    TextInput::make("meta_title.{$locale}")
                        ->label(__('admin.categories.fields.meta_title'))
                        ->maxLength(70),
                    Textarea::make("meta_description.{$locale}")
                        ->label(__('admin.categories.fields.meta_description'))
                        ->rows(2)
                        ->maxLength(160),
                ]),
            ]);
    }

    private static function publishing(): Section
    {
        return Section::make(__('admin.products.sections.publishing'))
            ->schema([
                Select::make('status')
                    ->label(__('admin.products.fields.status'))
                    ->options(collect(ProductStatus::cases())->mapWithKeys(
                        fn (ProductStatus $s): array => [$s->value => __("admin.products.statuses.{$s->value}")],
                    ))
                    ->default(ProductStatus::Draft->value)
                    ->required()
                    ->native(false),
                DateTimePicker::make('published_at')
                    ->label(__('admin.products.fields.published_at'))
                    ->helperText(__('admin.products.help.published_at'))
                    ->seconds(false),
                Toggle::make('is_featured')
                    ->label(__('admin.products.fields.is_featured'))
                    ->helperText(__('admin.products.help.is_featured')),
            ]);
    }

    private static function organisation(): Section
    {
        return Section::make(__('admin.products.sections.organisation'))
            ->schema([
                Select::make('categories')
                    ->label(__('admin.products.fields.categories'))
                    ->relationship('categories', 'slug', fn (Builder $query): Builder => $query->orderBy('sort_order'))
                    ->getOptionLabelFromRecordUsing(fn (Category $c): string => $c->parent_id ? $c->parent?->name.' › '.$c->name : $c->name)
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Select::make('brand_id')
                    ->label(__('admin.products.fields.brand'))
                    ->relationship('brand', 'name', fn (Builder $query): Builder => $query->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(fn (Brand $b): string => $b->name)
                    ->preload()
                    ->searchable(),
                Select::make('related')
                    ->label(__('admin.products.fields.related'))
                    ->helperText(__('admin.products.help.related'))
                    ->relationship('related', 'slug', fn (Builder $query, ?Product $record): Builder => $query
                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey())))
                    ->getOptionLabelFromRecordUsing(fn (Product $p): string => $p->name)
                    ->multiple()
                    ->maxItems(8)
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search, ?Product $record): array => TranslatableFields::search('name')(Product::query(), $search)
                        ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                        ->limit(20)
                        ->get()
                        ->mapWithKeys(fn (Product $p): array => [$p->getKey() => $p->name])
                        ->all()),
                SlugInput::make('products'),
            ]);
    }

    /** @return \Illuminate\Support\Collection<int, ProductAttributeValue> */
    public static function colourValues(?Product $product): \Illuminate\Support\Collection
    {
        if (! $product?->exists) {
            return collect();
        }

        return ProductAttributeValue::query()
            ->whereHas('productAttribute', fn (Builder $q) => $q->where('type', AttributeType::Color))
            ->whereHas('variants', fn (Builder $q) => $q->where('product_id', $product->getKey()))
            ->orderBy('sort_order')
            ->get();
    }
}
