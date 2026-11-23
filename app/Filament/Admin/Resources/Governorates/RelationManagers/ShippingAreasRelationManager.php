<?php

namespace App\Filament\Admin\Resources\Governorates\RelationManagers;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Support\GuardedDelete;
use App\Filament\Admin\Support\MoneyInput;
use App\Models\Address;
use App\Models\Governorate;
use App\Models\ShippingArea;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class ShippingAreasRelationManager extends RelationManager
{
    protected static string $relationship = 'shippingAreas';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.shipping.areas');
    }

    public static function getModelLabel(): string
    {
        return __('admin.shipping.area');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema(array_map(
                fn (string $locale): TextInput => TextInput::make("name.{$locale}")
                    ->label(__('admin.shipping.fields.area').' ('.__("admin.locales.{$locale}").')')
                    ->required()
                    ->maxLength(80),
                TranslatableFields::locales(),
            )),
            Grid::make(2)->schema([
                MoneyInput::make('shipping_price')
                    ->label(__('admin.shipping.fields.price'))
                    ->helperText(__('admin.shipping.help.price'))
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('admin.shipping.fields.delivers_here'))
                    ->default(true)
                    ->inline(false),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordTitle(fn (ShippingArea $record): string|Htmlable => $record->name)
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.shipping.fields.area'))
                    ->weight('medium')
                    ->searchable(query: TranslatableFields::search('name')),
                TextColumn::make('shipping_price')
                    ->label(__('admin.shipping.fields.price'))
                    ->formatStateUsing(fn (int $state): string => $state === 0
                        ? __('admin.shipping.free')
                        : number_format($state / 100, 2).' '.config('ishop.currency')),
                IconColumn::make('is_active')
                    ->label(__('admin.shipping.fields.delivers_here'))
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => TranslatableFields::clean($data, ['name'])),
                $this->samePriceAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, ShippingArea $record): array => TranslatableFields::fill($record, $data))
                    ->mutateDataUsing(fn (array $data): array => TranslatableFields::clean($data, ['name'])),
                GuardedDelete::make(fn (ShippingArea $area): ?string => Address::query()->where('shipping_area_id', $area->getKey())->exists()
                    ? __('admin.shipping.errors.area_in_use')
                    : null),
            ]);
    }

    /** Most governorates charge one price everywhere: set it once. */
    private function samePriceAction(): Action
    {
        return Action::make('samePrice')
            ->label(__('admin.shipping.actions.same_price'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
            ->visible(fn (): bool => $this->getOwnerRecord()->shippingAreas()->exists())
            ->modalDescription(__('admin.shipping.help.same_price'))
            ->schema([
                MoneyInput::make('shipping_price')
                    ->label(__('admin.shipping.fields.price'))
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var Governorate $governorate */
                $governorate = $this->getOwnerRecord();

                // One by one so each change is in the activity log.
                $governorate->shippingAreas()->get()->each(fn (ShippingArea $area) => $area->update(['shipping_price' => (int) $data['shipping_price']]));

                Notification::make()->success()->title(__('admin.shipping.notifications.prices_updated'))->send();
            });
    }
}
