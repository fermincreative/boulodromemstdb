<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A member's login for the ledenportaal (separate from staff users, who use /admin).
 * Members who share an e-mail address share one account; members without one have none.
 * There is no password: members log in with a link sent to this address.
 */
#[Fillable(['email'])]
#[Hidden(['remember_token'])]
class MemberAccount extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class)->orderBy('first_name');
    }

    /**
     * @return HasMany<MemberLoginToken, $this>
     */
    public function loginTokens(): HasMany
    {
        return $this->hasMany(MemberLoginToken::class);
    }

    /**
     * No passwords: logging in only works through a login link.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * Still a member: at least one linked member has not left.
     */
    public function hasCurrentMembers(): bool
    {
        return $this->members->contains(fn (Member $member) => ! $member->hasLeft());
    }

    /**
     * "Jan en Marie": how the account is addressed in the login e-mail.
     */
    public function greetingName(): string
    {
        $names = $this->members->pluck('first_name');

        return $names->count() > 1 ? $names->slice(0, -1)->implode(', ').' en '.$names->last() : (string) $names->first();
    }
}
