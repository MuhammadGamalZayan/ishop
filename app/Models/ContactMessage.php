<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A message from the Contact page form. Staff read it in the admin panel.
 *
 * @property CarbonInterface|null $read_at
 */
#[Fillable(['name', 'email', 'phone', 'message', 'locale'])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
