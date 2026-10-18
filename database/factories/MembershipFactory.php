<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Member;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'starts_at' => today()->startOfYear(),
            'ends_at' => today()->endOfYear()->startOfDay(),
            'status' => MembershipStatus::Active,
        ];
    }

    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => "{$year}-01-01",
            'ends_at' => "{$year}-12-31",
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MembershipStatus::Pending]);
    }
}
