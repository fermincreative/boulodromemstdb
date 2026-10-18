<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.3)->randomElement(['van', 'de', 'van der', 'van den']),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional(0.85)->safeEmail(),
            'phone_mobile' => fake()->optional()->numerify('06########'),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => 'Maastricht',
            'country' => 'Nederland',
            'birthday' => fake()->optional()->dateTimeBetween('-90 years', '-18 years'),
            'joined_at' => fake()->dateTimeBetween('-10 years'),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['cancelled_at' => today()->subMonth()]);
    }
}
