<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * Picture cards (mega menus, home page): an image, a title and subtitle in
 * both languages, and a link. The storefront reads them through
 * Catalog::pictureCards(). Images go to the public disk; no SVG (scripts).
 */
class PictureCards
{
    public static function repeater(string $name, string $directory, int $max = 2): Repeater
    {
        return Repeater::make($name)
            ->label(__('admin.picture_cards.label'))
            ->schema([
                self::image('image', $directory)->required(),
                Grid::make(2)->schema([
                    TextInput::make('title.en')->label(__('admin.picture_cards.title_en'))->maxLength(60)->required(),
                    TextInput::make('title.ar')->label(__('admin.picture_cards.title_ar'))->maxLength(60)->required(),
                    TextInput::make('subtitle.en')->label(__('admin.picture_cards.subtitle_en'))->maxLength(60),
                    TextInput::make('subtitle.ar')->label(__('admin.picture_cards.subtitle_ar'))->maxLength(60),
                ]),
                self::link('link'),
            ])
            ->maxItems($max)
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['title']['en'] ?? null)
            ->defaultItems(0);
    }

    public static function image(string $name, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label(__('admin.picture_cards.image'))
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)
            ->imageEditor();
    }

    /** A category slug (e.g. "dresses"), a path ("/en/pages/faq") or a full URL. */
    public static function link(string $name): TextInput
    {
        return TextInput::make($name)
            ->label(__('admin.picture_cards.link'))
            ->helperText(__('admin.picture_cards.link_help'))
            ->maxLength(255)
            ->regex('/^(https?:\/\/\S+|\/\S*|(?:products\/|pages\/)?[a-z0-9]+(?:-[a-z0-9]+)*)$/')
            ->validationMessages(['regex' => __('admin.picture_cards.link_invalid')]);
    }
}
