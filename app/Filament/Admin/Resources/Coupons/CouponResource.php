<?php

namespace App\Filament\Admin\Resources\Coupons;

use App\Enums\CouponType;
use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Admin\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Admin\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Admin\Support\MoneyInput;
use App\Models\Coupon;
use BackedEnum;
use Closure;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Discount codes. The admin types a percentage or an amount in pounds;
 * the form converts to basis points / piastres (see CouponType).
 * Whether a code applies to a cart is decided at checkout, server-side.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('admin.coupons.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.coupons.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.sales');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make(__('admin.coupons.sections.discount'))->schema([
                    TextInput::make('code')
                        ->label(__('admin.coupons.fields.code'))
                        ->helperText(__('admin.coupons.help.code'))
                        ->required()
                        ->maxLength(50)
                        ->regex('/^[A-Za-z0-9_-]+$/')
                        ->dehydrateStateUsing(fn (string $state): string => mb_strtoupper(trim($state)))
                        // Unique across deleted codes too, so an old code never comes back by mistake.
                        // Compared as stored (trimmed, uppercase), whatever the database collation.
                        ->rule(fn (?Coupon $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $taken = Coupon::withTrashed()
                                ->where('code', mb_strtoupper(trim((string) $value)))
                                ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                                ->exists();

                            if ($taken) {
                                $fail(__('admin.coupons.errors.code_taken'));
                            }
                        })
                        ->suffixAction(Action::make('random')
                            ->label(__('admin.coupons.actions.random'))
                            ->icon(Heroicon::OutlinedSparkles)
                            ->action(fn (Set $set) => $set('code', strtoupper(Str::random(8))))),
                    Select::make('type')
                        ->label(__('admin.coupons.fields.type'))
                        ->options(collect(CouponType::cases())->mapWithKeys(
                            fn (CouponType $t): array => [$t->value => __("admin.coupons.types.{$t->value}")],
                        ))
                        ->default(CouponType::Percentage->value)
                        ->required()
                        ->native(false)
                        ->live(),
                    Grid::make(2)->schema([
                        TextInput::make('percent')
                            ->label(__('admin.coupons.fields.percent'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(fn (Get $get): bool => $get('type') === CouponType::Percentage->value)
                            ->visible(fn (Get $get): bool => $get('type') === CouponType::Percentage->value)
                            ->dehydrated(false),
                        MoneyInput::make('max_discount')
                            ->label(__('admin.coupons.fields.max_discount'))
                            ->helperText(__('admin.coupons.help.max_discount'))
                            ->visible(fn (Get $get): bool => $get('type') === CouponType::Percentage->value),
                        MoneyInput::make('amount')
                            ->label(__('admin.coupons.fields.amount'))
                            ->minValue(0.01)
                            ->required(fn (Get $get): bool => $get('type') === CouponType::Fixed->value)
                            ->visible(fn (Get $get): bool => $get('type') === CouponType::Fixed->value)
                            ->dehydrated(false),
                    ]),
                    MoneyInput::make('min_subtotal')
                        ->label(__('admin.coupons.fields.min_subtotal'))
                        ->helperText(__('admin.coupons.help.min_subtotal')),
                    TranslatableFields::tabs('description', fn (string $locale): array => [
                        Textarea::make("description.{$locale}")
                            ->label(__('admin.coupons.fields.description'))
                            ->helperText(__('admin.coupons.help.description'))
                            ->rows(2)
                            ->maxLength(255),
                    ]),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make(__('admin.coupons.sections.limits'))->schema([
                    Toggle::make('is_active')
                        ->label(__('admin.coupons.fields.is_active'))
                        ->default(true),
                    DateTimePicker::make('starts_at')
                        ->label(__('admin.coupons.fields.starts_at'))
                        ->seconds(false),
                    DateTimePicker::make('expires_at')
                        ->label(__('admin.coupons.fields.expires_at'))
                        ->seconds(false)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $start = $get('starts_at');
                            if (filled($start) && filled($value) && Carbon::parse($value)->lte(Carbon::parse($start))) {
                                $fail(__('admin.coupons.errors.ends_before_start'));
                            }
                        }),
                    TextInput::make('usage_limit')
                        ->label(__('admin.coupons.fields.usage_limit'))
                        ->helperText(__('admin.coupons.help.unlimited'))
                        ->integer()
                        ->minValue(1),
                    TextInput::make('usage_limit_per_user')
                        ->label(__('admin.coupons.fields.usage_limit_per_user'))
                        ->helperText(__('admin.coupons.help.unlimited'))
                        ->integer()
                        ->minValue(1)
                        ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $total = $get('usage_limit');
                            if (filled($total) && filled($value) && (int) $value > (int) $total) {
                                $fail(__('admin.coupons.errors.per_user_over_total'));
                            }
                        }),
                ]),
            ]),
        ]);
    }

    /**
     * Turn the form's percent / amount into the stored `value`.
     *
     * @param  array<string, mixed>  $data   dehydrated data
     * @param  array<string, mixed>  $raw    raw form state
     * @return array<string, mixed>
     */
    public static function valueFromForm(array $data, array $raw): array
    {
        $type = CouponType::from($data['type']);

        $data['value'] = match ($type) {
            CouponType::Percentage => MoneyInput::toPiastres($raw['percent'] ?? null) ?? 0, // 12.5% → 1250 basis points
            CouponType::Fixed => MoneyInput::toPiastres($raw['amount'] ?? null) ?? 0,
            CouponType::FreeShipping => 0,
        };

        if ($type !== CouponType::Percentage) {
            $data['max_discount'] = null;
        }

        return TranslatableFields::clean($data, ['description']);
    }

    /** The other way, for the edit form. */
    public static function valueToForm(Coupon $coupon, array $data): array
    {
        $data = TranslatableFields::fill($coupon, $data);
        $asDecimal = fn (int $v): string => number_format($v / 100, 2, '.', '');

        match ($coupon->type) {
            CouponType::Percentage => $data['percent'] = $asDecimal($coupon->value),
            CouponType::Fixed => $data['amount'] = $coupon->value,
            CouponType::FreeShipping => null,
        };

        return $data;
    }

    public static function describeValue(Coupon $coupon): string
    {
        return match ($coupon->type) {
            CouponType::Percentage => rtrim(rtrim(number_format($coupon->value / 100, 2), '0'), '.').'%'
                .($coupon->max_discount ? ' '.__('admin.coupons.up_to', ['amount' => number_format($coupon->max_discount / 100, 2)]) : ''),
            CouponType::Fixed => number_format($coupon->value / 100, 2).' '.config('ishop.currency'),
            CouponType::FreeShipping => __('admin.coupons.types.free_shipping'),
        };
    }

    /** One word for where a coupon stands right now. */
    public static function status(Coupon $coupon): string
    {
        return match (true) {
            $coupon->trashed() => 'deleted',
            ! $coupon->is_active => 'disabled',
            $coupon->starts_at?->isFuture() ?? false => 'scheduled',
            $coupon->expires_at?->isPast() ?? false => 'expired',
            $coupon->usage_limit !== null && ($coupon->redemptions_count ?? $coupon->redemptions()->count()) >= $coupon->usage_limit => 'used_up',
            default => 'active',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('redemptions'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.coupons.fields.code'))
                    ->fontFamily('mono')
                    ->weight('medium')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('value')
                    ->label(__('admin.coupons.fields.discount'))
                    ->state(fn (Coupon $record): string => self::describeValue($record))
                    ->description(fn (Coupon $record): ?string => $record->min_subtotal
                        ? __('admin.coupons.min', ['amount' => number_format($record->min_subtotal / 100, 2)])
                        : null),
                TextColumn::make('redemptions_count')
                    ->label(__('admin.coupons.fields.used'))
                    ->formatStateUsing(fn ($state, Coupon $record): string => $state.($record->usage_limit ? ' / '.$record->usage_limit : '')),
                TextColumn::make('status')
                    ->label(__('admin.coupons.fields.status'))
                    ->state(fn (Coupon $record): string => self::status($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        'expired', 'used_up' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => __("admin.coupons.statuses.{$state}")),
                TextColumn::make('expires_at')
                    ->label(__('admin.coupons.fields.expires_at'))
                    ->dateTime()
                    ->placeholder(__('admin.coupons.never'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.coupons.fields.type'))
                    ->options(collect(CouponType::cases())->mapWithKeys(
                        fn (CouponType $t): array => [$t->value => __("admin.coupons.types.{$t->value}")],
                    )),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription(__('admin.coupons.help.delete')),
                RestoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
