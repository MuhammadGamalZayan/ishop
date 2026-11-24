<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google / GitHub identity linked to a user (see SocialAuthController).
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $email
 */
#[Fillable(['provider', 'provider_id', 'email'])]
class SocialAccount extends Model
{
    /** Providers the store supports; each is shown only once its keys are set. */
    public const PROVIDERS = ['google', 'github'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $p): bool => filled(config("services.{$p}.client_id")) && filled(config("services.{$p}.client_secret")),
        ));
    }
}
