<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'description' => 'Contributie '.today()->year,
            'amount_cents' => 9000,
            'status' => PaymentStatus::Open,
        ];
    }
}
