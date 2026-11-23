<?php

namespace App\Filament\Admin\Resources\Governorates;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Governorates\Pages\CreateGovernorate;
use App\Filament\Admin\Resources\Governorates\Pages\EditGovernorate;
use App\Filament\Admin\Resources\Governorates\Pages\ListGovernorates;
use App\Filament\Admin\Resources\Governorates\RelationManagers\ShippingAreasRelationManager;
use App\Filament\Admin\Support\GuardedDelete;
use App\Models\Address;
use App\Models\Governorate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Where we deliver and what it costs: governorate → areas, each area with
 * its own shipping price. Checkout shows governorate then area.
 */
class GovernorateResource extends Resource
{
    protected static ?string $model = Governorate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'shipping';

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('admin.shipping.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.shipping.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.shipping.nav');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.sales');
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Governorate ? $record->name : null;
    }

    public static function deleteAction(): DeleteAction
    {
        return GuardedDelete::make(fn (Governorate $g): ?string => $g->shippingAreas()->exists() || Address::query()->where('governorate_id', $g->getKey())->exists()
            ? __('admin.shipping.errors.governorate_in_use')
            : null);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->schema([
                TranslatableFields::tabs('name', fn (string $locale): array => [
                    TextInput::make("name.{$locale}")
                        ->label(__('admin.shipping.fields.governorate'))
                        ->required()
                        ->maxLength(80),
                ]),
                Toggle::make('is_active')
                    ->label(__('admin.shipping.fields.delivers_here'))
                    ->helperText(__('admin.shipping.help.governorate_active'))
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['shippingAreas', 'shippingAreas as active_areas_count' => fn (Builder $q) => $q->where('is_active', true)])
                ->withMin(['shippingAreas as min_price' => fn (Builder $q) => $q->where('is_active', true)], 'shipping_price')
                ->withMax(['shippingAreas as max_price' => fn (Builder $q) => $q->where('is_active', true)], 'shipping_price'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.shipping.fields.governorate'))
                    ->weight('medium')
                    ->searchable(query: TranslatableFields::search('name')),
                TextColumn::make('active_areas_count')
                    ->label(__('admin.shipping.fields.areas'))
                    ->formatStateUsing(fn ($state, Governorate $record): string => $state.' / '.$record->shipping_areas_count)
                    ->badge()
                    ->color(fn ($state): string => (int) $state === 0 ? 'warning' : 'gray'),
                TextColumn::make('price_range')
                    ->label(__('admin.shipping.fields.price'))
                    ->state(fn (Governorate $record): string => self::priceRange($record->min_price, $record->max_price)),
                IconColumn::make('is_active')
                    ->label(__('admin.shipping.fields.delivers_here'))
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin.shipping.fields.delivers_here')),
            ])
            ->recordActions([
                EditAction::make(),
                self::deleteAction(),
            ]);
    }

    public static function priceRange(mixed $min, mixed $max): string
    {
        if ($min === null) {
            return '—';
        }

        $format = fn ($p): string => (int) $p === 0 ? __('admin.shipping.free') : number_format($p / 100, 2).' '.config('ishop.currency');

        return (int) $min === (int) $max ? $format($min) : $format($min).' – '.$format($max);
    }

    public static function getRelations(): array
    {
        return [ShippingAreasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGovernorates::route('/'),
            'create' => CreateGovernorate::route('/create'),
            'edit' => EditGovernorate::route('/{record}/edit'),
        ];
    }
}
