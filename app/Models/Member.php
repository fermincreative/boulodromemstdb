<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Support\MemberNumber;
use Carbon\CarbonInterface;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'member_number', 'first_name', 'middle_name', 'last_name',
    'email', 'phone_mobile', 'phone_home',
    'street', 'postal_code', 'city', 'country',
    'birthday', 'club_id', 'contact_for', 'joined_at', 'cancelled_at', 'notes',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'member_number' => 'integer',
            'birthday' => 'date:Y-m-d',
            'joined_at' => 'date:Y-m-d',
            'cancelled_at' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        // Member numbers are permanent: new members get the next free number, existing ones keep theirs.
        static::creating(function (Member $member) {
            $member->member_number ??= static::nextMemberNumber();
        });
    }

    public static function nextMemberNumber(): int
    {
        return ((int) static::max('member_number')) + 1;
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The latest active membership: the one the card is made for. Its ends_at is the member's "current expiry".
     *
     * @return HasOne<Membership, $this>
     */
    public function currentMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->ofMany(
            ['ends_at' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('status', MembershipStatus::Active),
        );
    }

    /**
     * The member number as shown to people: "SBM-0261-91". The stored number (261) is what the QR code signs.
     */
    protected function displayNumber(): Attribute
    {
        return Attribute::get(fn () => $this->member_number ? MemberNumber::format($this->member_number) : null);
    }

    /**
     * "Jan van der Berg": first name, tussenvoegsel, last name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter(fn (?string $part) => filled($part))
            ->implode(' '));
    }

    /**
     * Has given notice (the cancellation date may still be in the future).
     */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Has actually left: the cancellation date has passed. A member stays a member up to and including that date.
     */
    public function hasLeft(?CarbonInterface $on = null): bool
    {
        return $this->cancelled_at !== null && $this->cancelled_at->lt(($on ?? today())->copy()->startOfDay());
    }

    /**
     * Members today, including those who gave notice for a later date.
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('cancelled_at')->orWhereDate('cancelled_at', '>=', today()));
    }

    #[Scope]
    protected function former(Builder $query): void
    {
        $query->whereDate('cancelled_at', '<', today());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('members')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
