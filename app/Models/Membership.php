<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['member_id', 'payment_id', 'starts_at', 'ends_at', 'status', 'card_sent_at'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'status' => MembershipStatus::class,
            'card_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * The year printed on the card and signed in the QR code.
     */
    public function cardYear(): int
    {
        return $this->starts_at->year;
    }

    /**
     * The member may download this card themselves: an active period that has not ended, of a member who has not left.
     */
    public function cardAvailable(): bool
    {
        return $this->isActive() && $this->ends_at->gte(today()) && ! $this->member->hasLeft();
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function isValidOn(?Carbon $date = null): bool
    {
        $date ??= today();

        return $this->status === MembershipStatus::Active
            && $this->starts_at->lte($date)
            && $this->ends_at->gte($date);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', MembershipStatus::Active);
    }

    #[Scope]
    protected function validOn(Builder $query, ?Carbon $date = null): void
    {
        $date ??= today();

        $query->where('status', MembershipStatus::Active)
            ->whereDate('starts_at', '<=', $date)
            ->whereDate('ends_at', '>=', $date);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('memberships')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
