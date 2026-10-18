<?php

namespace App\Actions\Cards;

use App\Models\Member;
use App\Models\Membership;

/**
 * Result of scanning a membership card.
 */
final readonly class CardVerification
{
    public function __construct(
        public bool $valid,
        public string $title,
        public ?string $message = null,
        public ?Member $member = null,
        public ?Membership $membership = null,
        public ?string $notice = null,
    ) {}
}
