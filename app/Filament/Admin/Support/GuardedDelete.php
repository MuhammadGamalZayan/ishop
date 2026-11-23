<?php

namespace App\Filament\Admin\Support;

use Closure;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * A delete action that first asks why the record must stay.
 *
 * $reason returns a translated message when deleting is not allowed (the
 * admin sees it as a notice and nothing is deleted), or null to go ahead.
 * Models repeat the same rule in their deleting event as a last defence.
 */
final class GuardedDelete
{
    /** @param  Closure(Model): ?string  $reason */
    public static function make(Closure $reason): DeleteAction
    {
        return DeleteAction::make()
            ->before(function (Model $record, DeleteAction $action) use ($reason): void {
                $message = $reason($record);

                if ($message === null) {
                    return;
                }

                Notification::make()
                    ->danger()
                    ->title(__('admin.common.action_blocked'))
                    ->body($message)
                    ->persistent()
                    ->send();

                $action->cancel();
            });
    }
}
