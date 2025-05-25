<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'timezone' => fake()->randomElement([
                'UTC',
                'Europe/Madrid',
                'Europe/London',
                'America/New_York',
            ]),
            'odoo_id' => fake()->unique()->numberBetween(1000, 9999),
            'desktime_id' => fake()->unique()->numberBetween(1000, 9999),
            'proofhub_id' => fake()->unique()->uuid(),
            'systempin_id' => fake()->unique()->numberBetween(100, 999),
            'user_type' => RoleType::User,
            'do_not_track' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user is an admin.
     *
     * @return static
     */
    public function admin()
    {
        return $this->state(
            fn (array $attributes) => [
                'user_type' => RoleType::Admin,
            ]
        );
    }
}
