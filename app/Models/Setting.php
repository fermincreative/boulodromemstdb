<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Key/value settings that admins can change (e.g. the annual fee).
 */
class Setting extends Model
{
    use LogsActivity;

    public const ANNUAL_FEE_CENTS = 'annual_fee_cents';

    public const BANK_IBAN = 'bank_iban';

    public const BANK_ACCOUNT_HOLDER = 'bank_account_holder';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::find($key)?->value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $setting = static::findOrNew($key);
        $setting->key = $key;
        $setting->value = $value;
        $setting->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly(['value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
