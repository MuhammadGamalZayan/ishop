<?php

namespace App\Filament\Admin\Resources\Categories\Actions;

use App\Exceptions\CategoryInUseException;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;

/**
 * Delete / restore with the category rules applied up front, so the admin
 * sees a clear message instead of an error. The model enforces the same
 * rules again in its deleting / restoring events.
 *
 * There is deliberately no force-delete: a category is only ever soft
 * deleted, so old orders and activity logs keep a valid reference.
 */
final class CategoryActions
{
    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(__('admin.categories.help.delete'))
            ->before(function (Category $record, DeleteAction $action): void {
                $error = match (true) {
                    $record->children()->exists() => CategoryInUseException::hasChildren(),
                    $record->products()->exists() => CategoryInUseException::hasProducts(),
                    default => null,
                };

                if ($error) {
                    self::fail($error->getMessage());
                    $action->cancel();
                }
            });
    }

    public static function restore(): RestoreAction
    {
        return RestoreAction::make()
            ->before(function (Category $record, RestoreAction $action): void {
                if ($record->parent_id !== null && $record->parent()->withTrashed()->first()?->trashed()) {
                    self::fail(CategoryInUseException::parentTrashed()->getMessage());
                    $action->cancel();
                }
            });
    }

    private static function fail(string $message): void
    {
        Notification::make()
            ->danger()
            ->title(__('admin.categories.errors.action_blocked'))
            ->body($message)
            ->persistent()
            ->send();
    }
}
