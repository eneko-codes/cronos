<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'odoo_schedule_id' => fake()->unique()->numberBetween(100, 999),
            'description' => fake()->randomElement([
                'Full-Time, 40h/week',
                'Part-Time, 20h/week',
            ]),
            'average_hours_day' => fake()->randomElement([8, 4]),
        ];
    }

    /**
     * Full-time 40-hour week schedule
     *
     * @return static
     */
    public function fullTime()
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Full-Time, 40h/week',
            'average_hours_day' => 8,
        ]);
    }

    /**
     * Part-time 20-hour week schedule
     *
     * @return static
     */
    public function partTime()
    {
        return $this->state(fn (array $attributes) => [
            'description' => 'Part-Time, 20h/week',
            'average_hours_day' => 4,
        ]);
    }
}
