<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduleDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleDetail>
 */
class ScheduleDetailFactory extends Factory
{
    protected $model = ScheduleDetail::class;

    public function definition(): array
    {
        return [
            'odoo_schedule_id' => Schedule::factory(),
            'odoo_detail_id' => fake()->unique()->numberBetween(1, 99999),
            'weekday' => fake()->numberBetween(0, 4),
            'day_period' => 'morning',
            'week_type' => 0,
            'date_from' => null,
            'date_to' => null,
            'start' => '09:00:00',
            'end' => '13:00:00',
            'name' => null,
            'active' => true,
        ];
    }

    public function afternoon(): static
    {
        return $this->state(fn () => [
            'day_period' => 'afternoon',
            'start' => '14:00:00',
            'end' => '18:00:00',
        ]);
    }
}
