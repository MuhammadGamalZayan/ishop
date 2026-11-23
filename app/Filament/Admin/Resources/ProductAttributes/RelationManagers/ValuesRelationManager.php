<?php

namespace App\Filament\Admin\Resources\ProductAttributes\RelationManagers;

use App\Enums\AttributeType;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Support\GuardedDelete;
use App\Filament\Admin\Support\SlugInput;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class ValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.attributes.values');
    }

    public static function getModelLabel(): string
    {
        return __('admin.attributes.value');
    }

    private function isColor(): bool
    {
        /** @var ProductAttribute $attribute */
        $attribute = $this->getOwnerRecord();

        return $attribute->type === AttributeType::Color;
    }

    public function form(Schema $schema): Schema
    {
        $attributeId = $this->getOwnerRecord()->getKey();

        return $schema
            ->components([
                Grid::make(2)->schema(array_map(
                    fn (string $locale): TextInput => TextInput::make("value.{$locale}")
                        ->label(__('admin.attributes.fields.value').' ('.__("admin.locales.{$locale}").')')
                        ->required()
                        ->maxLength(60),
                    TranslatableFields::locales(),
                )),
                Grid::make(2)->schema([
                    SlugInput::make('attribute_values', fn (Unique $rule): Unique => $rule->where('attribute_id', $attributeId)),
                    ColorPicker::make('color_hex')
                        ->label(__('admin.attributes.fields.color'))
                        ->helperText(__('admin.attributes.help.color'))
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->visible(fn (): bool => $this->isColor())
                        ->required(fn (): bool => $this->isColor()),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('variants'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordTitle(fn (ProductAttributeValue $record): string|Htmlable => $record->value)
            ->columns([
                ColorColumn::make('color_hex')
                    ->label(__('admin.attributes.fields.color'))
                    ->visible(fn (): bool => $this->isColor()),
                TextColumn::make('value')
                    ->label(__('admin.attributes.fields.value'))
                    ->weight('medium')
                    ->searchable(query: TranslatableFields::search('value')),
                TextColumn::make('slug')
                    ->label(__('admin.common.slug'))
                    ->color('gray'),
                TextColumn::make('variants_count')
                    ->label(__('admin.attributes.fields.variants_count'))
                    ->numeric()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => TranslatableFields::clean($data, ['value'])),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, ProductAttributeValue $record): array => TranslatableFields::fill($record, $data))
                    ->mutateDataUsing(fn (array $data): array => TranslatableFields::clean($data, ['value'])),
                GuardedDelete::make(fn (ProductAttributeValue $value): ?string => $value->variants()->exists()
                    ? __('admin.attributes.errors.value_in_use')
                    : null),
            ]);
    }
}
