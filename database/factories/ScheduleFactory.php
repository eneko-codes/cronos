<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'odoo_schedule_id' => fake()->unique()->numberBetween(1, 99999),
            'description' => 'Standard 40 hours/week',
            'average_hours_day' => 8.0,
            'two_weeks_calendar' => false,
            'two_weeks_explanation' => null,
            'flexible_hours' => false,
            'active' => true,
        ];
    }

    public function flexible(): static
    {
        return $this->state(fn () => [
            'description' => 'Flexible 37.5 hours/week',
            'average_hours_day' => 7.5,
            'flexible_hours' => true,
        ]);
    }

    public function partTime(): static
    {
        return $this->state(fn () => [
            'description' => 'Part-Time 20 hours/week',
            'average_hours_day' => 4.0,
        ]);
    }
}
