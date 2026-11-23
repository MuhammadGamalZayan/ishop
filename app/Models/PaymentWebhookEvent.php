<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw gateway callbacks, stored once each (unique gateway + event_key). */
class PaymentWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
