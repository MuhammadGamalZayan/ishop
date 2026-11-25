<?php

namespace App\Filament\Admin\Resources\NewsletterSubscribers;

use App\Filament\Admin\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** E-mails from the newsletter forms. Staff can remove one on request. */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('admin.subscribers.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.subscribers.plural');
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->label(__('admin.subscribers.fields.email'))->searchable()->copyable(),
                TextColumn::make('locale')->label(__('admin.subscribers.fields.locale'))->badge(),
                TextColumn::make('created_at')->label(__('admin.subscribers.fields.subscribed'))->dateTime()->sortable(),
            ])
            ->recordActions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscribers::route('/')];
    }
}
