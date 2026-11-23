<?php

namespace App\Filament\Admin\Resources\Categories\Schemas;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Models\Category;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class CategoryForm
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Section::make(__('admin.categories.sections.content'))
                            ->schema([
                                TranslatableFields::tabs('content', fn (string $locale): array => [
                                    TextInput::make("name.{$locale}")
                                        ->label(__('admin.categories.fields.name'))
                                        ->required()
                                        ->maxLength(120),
                                    Textarea::make("description.{$locale}")
                                        ->label(__('admin.categories.fields.description'))
                                        ->rows(4)
                                        ->maxLength(2000),
                                ]),
                            ]),

                        Section::make(__('admin.categories.sections.seo'))
                            ->description(__('admin.categories.help.seo'))
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
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make(__('admin.categories.sections.settings'))
                            ->schema([
                                Toggle::make('is_active')
                                    ->label(__('admin.categories.fields.is_active'))
                                    ->default(true),

                                Select::make('parent_id')
                                    ->label(__('admin.categories.fields.parent'))
                                    ->placeholder(__('admin.categories.help.no_parent'))
                                    ->relationship(
                                        name: 'parent',
                                        modifyQueryUsing: fn (Builder $query, ?Category $record): Builder => $query
                                            ->when($record, fn (Builder $q) => $q->whereKeyNot([$record->getKey(), ...$record->descendantIds()]))
                                            ->orderBy('sort_order'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Category $category): string => $category->name)
                                    ->preload()
                                    ->searchable()
                                    // The option list already hides invalid parents; this rule
                                    // enforces it server-side against a tampered request.
                                    ->rule(fn (?Category $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                        if ($record && $value && in_array((int) $value, [$record->getKey(), ...$record->descendantIds()], true)) {
                                            $fail(__('admin.categories.errors.parent_cycle'));
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->label(__('admin.categories.fields.slug'))
                                    ->prefix('/')
                                    ->maxLength(120)
                                    ->required(fn (string $operation): bool => $operation === 'edit')
                                    ->helperText(fn (string $operation): string => $operation === 'edit'
                                        ? __('admin.categories.help.slug_edit')
                                        : __('admin.categories.help.slug_create'))
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtolower(trim($state)) : null)
                                    ->regex(self::SLUG_PATTERN)
                                    ->notIn(config('ishop.reserved_slugs'))
                                    ->validationMessages([
                                        'regex' => __('admin.categories.errors.slug_format'),
                                        'not_in' => __('admin.categories.errors.slug_reserved'),
                                    ])
                                    // Unique across trashed rows too: the DB index covers them.
                                    ->rule(fn (?Category $record) => Rule::unique('categories', 'slug')->ignore($record)),

                                SpatieMediaLibraryFileUpload::make('image')
                                    ->label(__('admin.categories.fields.image'))
                                    ->collection('image')
                                    ->image()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(2048)
                                    ->imageEditor(),
                            ]),
                    ]),
            ]);
    }
}
