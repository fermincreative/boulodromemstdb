<?php

namespace App\Models;

use App\Actions\Accounts\LinkMemberAccount;
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
            'renew_early_for' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Member numbers are permanent: new members get the next free number, existing ones keep theirs.
        static::creating(function (Member $member) {
            $member->member_number ??= static::nextMemberNumber();
        });

        // Keep the member linked to the ledenportaal login of their e-mail address.
        static::created(fn (Member $member) => app(LinkMemberAccount::class)->forMember($member));
        static::updated(fn (Member $member) => $member->wasChanged('email') ? app(LinkMemberAccount::class)->emailChanged($member) : null);
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
     * The login (ledenportaal) this member belongs to, if any.
     *
     * @return BelongsTo<MemberAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MemberAccount::class, 'member_account_id');
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
     * The active period running today: the card to show at the entrance.
     */
    public function membershipToday(): ?Membership
    {
        return $this->memberships->first(fn (Membership $membership) => $membership->isValidOn());
    }

    /**
     * The end of the latest active period (the member's "current expiry"), e.g. 31-12-2027 when next year is paid already.
     */
    public function paidUntil(): ?CarbonInterface
    {
        return $this->memberships
            ->filter(fn (Membership $membership) => $membership->isActive())
            ->max(fn (Membership $membership) => $membership->ends_at);
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
     * Paid for by their club (e.g. Mosa Petanca): no individual payment requests.
     */
    public function paysCollectively(): bool
    {
        return (bool) $this->club?->pays_collectively;
    }

    /**
     * Can be renewed for $year: active in the year before, no notice given, and no period in $year yet.
     */
    #[Scope]
    protected function renewable(Builder $query, int $year): void
    {
        $query->whereNull('cancelled_at')
            ->whereHas('memberships', fn (Builder $memberships) => $memberships
                ->where('status', MembershipStatus::Active)
                ->whereDate('ends_at', '>=', ($year - 1).'-01-01')
                ->whereDate('ends_at', '<=', ($year - 1).'-12-31'))
            ->whereDoesntHave('memberships', fn (Builder $memberships) => $memberships
                ->where('status', '!=', MembershipStatus::Cancelled)
                ->whereDate('ends_at', '>=', $year.'-01-01'));
    }

    /**
     * Renewable for $year and up for it now: the membership ends on or before $cutoff, or was moved up by hand.
     */
    #[Scope]
    protected function dueForRenewal(Builder $query, int $year, CarbonInterface $cutoff): void
    {
        $query->renewable($year)->where(fn (Builder $query) => $query
            ->where('renew_early_for', $year)
            ->orWhereDoesntHave('memberships', fn (Builder $memberships) => $memberships
                ->where('status', MembershipStatus::Active)
                ->whereDate('ends_at', '>', $cutoff)));
    }

    /**
     * Renewable for $year, but the membership runs past $cutoff and nobody moved it up yet.
     */
    #[Scope]
    protected function notYetDueForRenewal(Builder $query, int $year, CarbonInterface $cutoff): void
    {
        $query->renewable($year)
            ->where(fn (Builder $query) => $query->whereNull('renew_early_for')->orWhere('renew_early_for', '!=', $year))
            ->whereHas('memberships', fn (Builder $memberships) => $memberships
                ->where('status', MembershipStatus::Active)
                ->whereDate('ends_at', '>', $cutoff));
    }

    /**
     * Has a membership starting in $year with this status; optionally only with (true) or without (false) a payment.
     */
    #[Scope]
    protected function withMembershipIn(Builder $query, int $year, MembershipStatus $status, ?bool $withPayment = null): void
    {
        $query->whereHas('memberships', fn (Builder $memberships) => $memberships
            ->where('status', $status)
            ->whereDate('starts_at', '>=', $year.'-01-01')
            ->whereDate('starts_at', '<=', $year.'-12-31')
            ->when($withPayment === true, fn (Builder $query) => $query->whereNotNull('payment_id'))
            ->when($withPayment === false, fn (Builder $query) => $query->whereNull('payment_id')));
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
