<?php

namespace App\Filament\Admin\Resources\Pages;

use App\Filament\Admin\Concerns\TranslatableFields;
use App\Filament\Admin\Resources\Pages\Pages\CreatePage;
use App\Filament\Admin\Resources\Pages\Pages\EditPage;
use App\Filament\Admin\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use UnitEnum;

/**
 * Content pages (Our story, FAQ, Contact…). Published pages with a menu
 * column appear in the About menu and the footer; "contact" adds the form.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.pages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pages.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.home.group');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['lg' => 2])->schema([
                Section::make()->schema([
                    TranslatableFields::tabs('content', fn (string $locale): array => [
                        TextInput::make("title.{$locale}")->label(__('admin.pages.fields.title'))->required()->maxLength(120),
                        RichEditor::make("body.{$locale}")
                            ->label(__('admin.pages.fields.body'))
                            ->toolbarButtons(['h2', 'h3', 'bold', 'italic', 'link', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo']),
                        Textarea::make("meta_description.{$locale}")->label(__('admin.pages.fields.meta_description'))->rows(2)->maxLength(160),
                    ]),
                ]),
            ]),
            Grid::make(1)->columnSpan(['lg' => 1])->schema([
                Section::make()->schema([
                    Toggle::make('is_published')->label(__('admin.pages.fields.is_published')),
                    Select::make('menu_group')
                        ->label(__('admin.pages.fields.menu_group'))
                        ->options(collect(Page::MENU_GROUPS)->mapWithKeys(fn (string $g): array => [$g => __("admin.pages.groups.{$g}")])->all())
                        ->placeholder(__('admin.pages.groups.none')),
                    Select::make('template')
                        ->label(__('admin.pages.fields.template'))
                        ->options(collect(Page::TEMPLATES)->mapWithKeys(fn (string $t): array => [$t => __("admin.pages.templates.{$t}")])->all())
                        ->default('default')->required(),
                    TextInput::make('sort_order')->label(__('admin.pages.fields.sort_order'))->numeric()->minValue(0)->default(0),
                    TextInput::make('slug')
                        ->label(__('admin.pages.fields.slug'))
                        ->helperText(__('admin.pages.help.slug'))
                        ->maxLength(120)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->rule(fn (?Model $record) => Rule::unique('pages', 'slug')->ignore($record)),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->label(__('admin.pages.fields.title'))->weight('medium'),
                TextColumn::make('slug')->label(__('admin.pages.fields.slug'))->color('gray'),
                TextColumn::make('menu_group')->label(__('admin.pages.fields.menu_group'))
                    ->formatStateUsing(fn (?string $state): string => __('admin.pages.groups.'.($state ?? 'none')))->badge(),
                IconColumn::make('is_published')->label(__('admin.pages.fields.is_published'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
