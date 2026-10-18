<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a new member pays when signing up from `starts_on` (MM-DD) until the next rate starts.
 * With `includes_next_year`, the full annual fee for next year is added to the same payment.
 */
#[Fillable(['starts_on', 'amount_cents', 'includes_next_year'])]
class JoiningRate extends Model
{
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'includes_next_year' => 'boolean',
        ];
    }

    /**
     * "1 april"
     */
    public function startsOnLabel(): string
    {
        [$month, $day] = array_map('intval', explode('-', $this->starts_on));

        return $day.' '.now()->setDate(2000, $month, 1)->translatedFormat('F');
    }
}
