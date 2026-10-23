<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A club (vereniging). Members of a club that pays collectively don't pay their membership themselves:
 * the club pays for them, so they get no payment request.
 */
#[Fillable(['name', 'pays_collectively', 'is_signup_club'])]
class Club extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'pays_collectively' => 'boolean',
            'is_signup_club' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one club is the sign-up club.
        static::saved(function (Club $club) {
            if ($club->is_signup_club) {
                static::whereKeyNot($club->getKey())->where('is_signup_club', true)->update(['is_signup_club' => false]);
            }
        });
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * The club people join through the public sign-up form.
     */
    public static function signUpClub(): ?self
    {
        return static::where('is_signup_club', true)->first();
    }

    /**
     * "JBC Amby en Mosa Petanca" (or with "of"): the clubs that pay for their members.
     */
    public static function collectiveNames(string $conjunction = 'en'): string
    {
        $names = static::where('pays_collectively', true)->orderBy('name')->pluck('name');

        return $names->count() > 1
            ? $names->slice(0, -1)->implode(', ')." {$conjunction} ".$names->last()
            : (string) $names->first();
    }
}
