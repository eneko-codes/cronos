<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleDetail>
 */
class ScheduleDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Morning shift by default
        return [
            'odoo_schedule_id' => null, // To be set when creating details
            'odoo_detail_id' => fake()->unique()->numberBetween(1000, 9999),
            'weekday' => fake()->numberBetween(0, 6), // 0 = Monday, 6 = Sunday
            'day_period' => 'morning',
            'start' => Carbon::createFromTime(9, 0, 0),
            'end' => Carbon::createFromTime(13, 0, 0),
        ];
    }

    /**
     * Morning shift from 9:00 to 13:00
     *
     * @return static
     */
    public function morning()
    {
        return $this->state(fn (array $attributes) => [
            'day_period' => 'morning',
            'start' => Carbon::createFromTime(9, 0, 0),
            'end' => Carbon::createFromTime(13, 0, 0),
        ]);
    }

    /**
     * Afternoon shift from 14:00 to 18:00
     *
     * @return static
     */
    public function afternoon()
    {
        return $this->state(fn (array $attributes) => [
            'day_period' => 'afternoon',
            'start' => Carbon::createFromTime(14, 0, 0),
            'end' => Carbon::createFromTime(18, 0, 0),
        ]);
    }

    /**
     * Set a specific weekday
     *
     * @param  int  $day  0=Monday, 6=Sunday
     * @return static
     */
    public function weekday(int $day)
    {
        return $this->state(fn (array $attributes) => [
            'weekday' => $day,
        ]);
    }
}
