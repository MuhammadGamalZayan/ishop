<?php

namespace App\Filament\Admin\Resources\ContactMessages;

use App\Filament\Admin\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Messages from the Contact page form. Read-only; staff mark them read. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.messages.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.messages.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.home.group');
    }

    public static function getNavigationBadge(): ?string
    {
        $unread = ContactMessage::query()->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(__('admin.messages.fields.name')),
            TextEntry::make('email')->label(__('admin.messages.fields.email'))->copyable(),
            TextEntry::make('phone')->label(__('admin.messages.fields.phone'))->placeholder('—'),
            TextEntry::make('created_at')->label(__('admin.messages.fields.received'))->dateTime(),
            TextEntry::make('message')->label(__('admin.messages.fields.message'))->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('read_at')->label(__('admin.messages.fields.read'))->boolean()->getStateUsing(fn (ContactMessage $m): bool => $m->read_at !== null),
                TextColumn::make('name')->label(__('admin.messages.fields.name'))->searchable()->weight(fn (ContactMessage $m): ?string => $m->read_at ? null : 'bold'),
                TextColumn::make('email')->label(__('admin.messages.fields.email'))->searchable(),
                TextColumn::make('message')->label(__('admin.messages.fields.message'))->limit(60),
                TextColumn::make('created_at')->label(__('admin.messages.fields.received'))->since()->sortable(),
            ])
            ->filters([TernaryFilter::make('read_at')->label(__('admin.messages.fields.read'))->nullable()])
            ->recordActions([
                ViewAction::make()->after(fn (ContactMessage $m) => $m->read_at ?? $m->forceFill(['read_at' => now()])->save()),
                Action::make('read')->label(__('admin.messages.mark_read'))->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (ContactMessage $m): bool => $m->read_at === null)
                    ->action(fn (ContactMessage $m) => $m->forceFill(['read_at' => now()])->save()),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
