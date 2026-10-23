<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A login link that was e-mailed. Only the SHA-256 hash of the token is stored.
 */
#[Fillable(['member_account_id', 'token_hash', 'expires_at', 'used_at', 'requested_ip'])]
class MemberLoginToken extends Model
{
    use MassPrunable;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MemberAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MemberAccount::class, 'member_account_id');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Old links are removed daily (model:prune).
     *
     * @return Builder<MemberLoginToken>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
