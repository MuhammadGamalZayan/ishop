<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Filament\Admin\Support\GuardedDelete;
use App\Filament\Admin\Support\MoneyInput;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\Catalog\VariantGenerator;
use App\Services\Inventory;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.variants.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.variants.label');
    }

    private function product(): Product
    {
        /** @var Product */
        return $this->getOwnerRecord();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('sku')
                    ->label(__('admin.variants.fields.sku'))
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[A-Za-z0-9-]+$/')
                    ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))
                    ->unique('product_variants', 'sku', ignoreRecord: true),
                TextInput::make('barcode')
                    ->label(__('admin.variants.fields.barcode'))
                    ->maxLength(64),
                MoneyInput::make('price')
                    ->label(__('admin.variants.fields.price'))
                    ->required()
                    ->minValue(0.01),
                MoneyInput::make('compare_at_price')
                    ->label(__('admin.variants.fields.compare_at_price'))
                    ->helperText(__('admin.variants.help.compare_at_price'))
                    ->gt('price'),
                MoneyInput::make('cost_price')
                    ->label(__('admin.variants.fields.cost_price'))
                    ->helperText(__('admin.variants.help.cost_price')),
                TextInput::make('weight_grams')
                    ->label(__('admin.variants.fields.weight'))
                    ->integer()
                    ->minValue(0)
                    ->suffix('g'),
                TextInput::make('low_stock_threshold')
                    ->label(__('admin.variants.fields.low_stock_threshold'))
                    ->integer()
                    ->minValue(0),
            ]),
            Grid::make(2)->schema([
                Toggle::make('is_active')->label(__('admin.variants.fields.is_active')),
                Toggle::make('track_inventory')
                    ->label(__('admin.variants.fields.track_inventory'))
                    ->helperText(__('admin.variants.help.track_inventory')),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('attributeValues.productAttribute')
                ->withoutGlobalScopes([SoftDeletingScope::class]))
            ->defaultSort('id')
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('options')
                    ->label(__('admin.variants.fields.options'))
                    ->state(fn (ProductVariant $record): string => self::optionsLabel($record))
                    ->weight('medium'),
                TextColumn::make('sku')
                    ->label(__('admin.variants.fields.sku'))
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('admin.variants.fields.price'))
                    ->money(config('ishop.currency'), divideBy: 100)
                    ->description(fn (ProductVariant $record): ?string => $record->isOnSale()
                        ? __('admin.variants.was', ['price' => number_format($record->compare_at_price / 100, 2)])
                        : null),
                TextColumn::make('stock_quantity')
                    ->label(__('admin.variants.fields.stock'))
                    ->badge()
                    ->state(fn (ProductVariant $record): string => $record->track_inventory ? (string) $record->stock_quantity : '∞')
                    ->color(fn (ProductVariant $record): string => match (true) {
                        ! $record->track_inventory => 'gray',
                        $record->stock_quantity === 0 => 'danger',
                        $record->low_stock_threshold !== null && $record->stock_quantity <= $record->low_stock_threshold => 'warning',
                        default => 'success',
                    }),
                IconColumn::make('is_default')
                    ->label(__('admin.variants.fields.is_default'))
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon('')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label(__('admin.variants.fields.is_active'))
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                $this->generateAction(),
            ])
            ->recordActions([
                $this->adjustStockAction(),
                $this->historyAction(),
                EditAction::make(),
                $this->makeDefaultAction(),
                $this->deleteAction(),
                RestoreAction::make(),
            ]);
    }

    public static function optionsLabel(ProductVariant $variant): string
    {
        $values = $variant->attributeValues
            ->sortBy(fn (ProductAttributeValue $v): int => $v->productAttribute->sort_order)
            ->map(fn (ProductAttributeValue $v): string => $v->value);

        return $values->isEmpty() ? __('admin.variants.single') : $values->implode(' / ');
    }

    private function generateAction(): Action
    {
        return Action::make('generate')
            ->label(__('admin.variants.actions.generate'))
            ->icon(Heroicon::OutlinedSquares2x2)
            ->modalDescription(__('admin.variants.help.generate'))
            ->authorize(fn (): bool => auth()->user()->can('create', ProductVariant::class))
            ->schema(fn (): array => [
                ...ProductAttribute::query()->with('values')->orderBy('sort_order')->get()
                    ->map(fn (ProductAttribute $attribute): CheckboxList => CheckboxList::make('options.'.$attribute->getKey())
                        ->label($attribute->name)
                        ->options($attribute->values->mapWithKeys(fn (ProductAttributeValue $v): array => [$v->getKey() => $v->value]))
                        ->columns(4)
                        ->bulkToggleable())
                    ->all(),
                Grid::make(2)->schema([
                    MoneyInput::make('price')
                        ->label(__('admin.products.fields.price_all'))
                        ->required()
                        ->minValue(0.01)
                        ->default(fn (): ?int => $this->product()->variants()->min('price')),
                    TextInput::make('sku_prefix')
                        ->label(__('admin.products.fields.sku_prefix'))
                        ->maxLength(40)
                        ->regex('/^[A-Za-z0-9-]+$/'),
                ]),
            ])
            ->action(function (array $data, Action $action): void {
                $options = array_filter(array_map(fn ($ids) => array_map('intval', (array) $ids), (array) ($data['options'] ?? [])));

                try {
                    $created = app(VariantGenerator::class)->generate($this->product(), $options, (int) $data['price'], $data['sku_prefix'] ?? null);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title(__('admin.common.action_blocked'))->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('admin.products.notifications.variants_generated', ['count' => $created->count()]))
                    ->body($created->isEmpty() ? __('admin.variants.nothing_new') : __('admin.products.notifications.set_stock'))
                    ->send();
            });
    }

    private function adjustStockAction(): Action
    {
        return Action::make('adjustStock')
            ->label(__('admin.stock.adjust'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->modalHeading(fn (ProductVariant $record): string => __('admin.stock.adjust').' — '.$record->sku)
            ->modalDescription(fn (ProductVariant $record): string => __('admin.stock.current', ['count' => $record->stock_quantity]))
            ->authorize(fn (ProductVariant $record): bool => auth()->user()->can('adjustStock', $record))
            ->visible(fn (ProductVariant $record): bool => $record->track_inventory && ! $record->trashed())
            ->schema([
                Radio::make('reason')
                    ->label(__('admin.stock.reason'))
                    ->options([
                        'restock' => __('admin.stock.reasons.restock'),
                        'count' => __('admin.stock.reasons.count'),
                        'return' => __('admin.stock.reasons.return'),
                        'remove' => __('admin.stock.reasons.remove'),
                    ])
                    ->default('restock')
                    ->required()
                    ->live(),
                TextInput::make('quantity')
                    ->label(fn (Get $get): string => $get('reason') === 'count' ? __('admin.stock.counted') : __('admin.stock.quantity'))
                    ->integer()
                    ->required()
                    ->minValue(fn (Get $get): int => $get('reason') === 'count' ? 0 : 1)
                    ->maxValue(100000),
                Textarea::make('note')
                    ->label(__('admin.stock.note'))
                    ->rows(2)
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => in_array($get('reason'), ['count', 'remove'], true)),
            ])
            ->action(function (array $data, ProductVariant $record, Action $action): void {
                $inventory = app(Inventory::class);
                $user = auth()->user();
                $quantity = (int) $data['quantity'];
                $note = $data['note'] ?? null;

                try {
                    $movement = match ($data['reason']) {
                        'restock' => $inventory->adjust($record, $quantity, StockMovementType::Restock, $user, $note),
                        'return' => $inventory->adjust($record, $quantity, StockMovementType::Return, $user, $note),
                        'remove' => $inventory->adjust($record, -$quantity, StockMovementType::Adjustment, $user, $note),
                        'count' => $inventory->setCount($record, $quantity, $user, $note),
                    };
                } catch (InsufficientStockException $e) {
                    Notification::make()->danger()->title(__('admin.common.action_blocked'))->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($movement
                        ? __('admin.stock.saved', ['count' => $movement->stock_after])
                        : __('admin.stock.unchanged'))
                    ->send();
            });
    }

    private function historyAction(): Action
    {
        return Action::make('stockHistory')
            ->label(__('admin.stock.history'))
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->authorize(fn (ProductVariant $record): bool => auth()->user()->can('viewStock', $record))
            ->modalHeading(fn (ProductVariant $record): string => __('admin.stock.history').' — '.$record->sku)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.stock.close'))
            ->modalContent(fn (ProductVariant $record) => view('filament.admin.stock-history', [
                'movements' => $record->stockMovements()->with('user:id,name')->latest('id')->limit(50)->get(),
            ]));
    }

    private function makeDefaultAction(): Action
    {
        return Action::make('makeDefault')
            ->label(__('admin.variants.actions.make_default'))
            ->icon(Heroicon::OutlinedStar)
            ->color('gray')
            ->authorize(fn (ProductVariant $record): bool => auth()->user()->can('update', $record))
            ->visible(fn (ProductVariant $record): bool => ! $record->is_default && $record->is_active && ! $record->trashed())
            ->action(function (ProductVariant $record): void {
                DB::transaction(function () use ($record): void {
                    $record->product->variants()->where('is_default', true)->update(['is_default' => false]);
                    $record->update(['is_default' => true]);
                });
            });
    }

    private function deleteAction(): Action
    {
        return GuardedDelete::make(function (ProductVariant $variant): ?string {
            $product = $variant->product;
            $othersActive = $product->variants()->whereKeyNot($variant->getKey())->where('is_active', true)->exists();

            return $product->status === ProductStatus::Active && ! $othersActive
                ? __('admin.variants.errors.last_active')
                : null;
        })
            ->modalDescription(__('admin.variants.help.delete'))
            ->after(function (ProductVariant $record): void {
                // Keep exactly one default while there is anything left to show.
                if ($record->is_default) {
                    $record->update(['is_default' => false]);
                    $record->product->variants()->where('is_active', true)->orderBy('id')->first()?->update(['is_default' => true]);
                }
            });
    }
}
