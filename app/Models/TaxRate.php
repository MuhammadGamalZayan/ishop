<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['name', 'rate_basis_points', 'is_inclusive', 'is_active'])]
class TaxRate extends Model
{
    use HasTranslations, LogsActivity;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'rate_basis_points' => 'integer',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Tax already contained in a tax-inclusive amount, in piastres.
     * e.g. 11400 at 14% -> 1400. Integer math only, rounded half up.
     */
    public function taxIncludedIn(int $amount): int
    {
        $bp = $this->rate_basis_points;

        return intdiv($amount * $bp * 2 + (10_000 + $bp), 2 * (10_000 + $bp));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
