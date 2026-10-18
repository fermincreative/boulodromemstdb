<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Editable e-mail text with {placeholders}. Rows are identified by a fixed `key` used in code.
 */
#[Fillable(['subject', 'body'])]
class MailTemplate extends Model
{
    use LogsActivity;

    public const MEMBERSHIP_CARD = 'membership_card';

    public const WELCOME = 'welcome';

    public const PAYMENT_CONFIRMATION = 'payment_confirmation';

    public const CANCELLATION = 'cancellation';

    public static function forKey(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
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
