<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Colours of the year badge on the card for one year. Years without a row use the default accent colours.
 */
#[Fillable(['year', 'badge_color', 'text_color'])]
class CardYearStyle extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return ['year' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
