<?php

namespace Database\Factories;

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
      'is_admin' => false,
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
      fn(array $attributes) => [
        'is_admin' => true,
      ]
    );
  }
}
