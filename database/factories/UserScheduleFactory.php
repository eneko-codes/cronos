<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSchedule>
 */
class UserScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null, // To be set when creating assignments
            'odoo_schedule_id' => null, // To be set when creating assignments
            'effective_from' => now(), // Will be set in the DemoSeeder
            'effective_until' => null,
        ];
    }

    /**
     * Set a specific effective date range
     *
     * @param  Carbon  $from  Start date
     * @param  Carbon|null  $until  End date (null for ongoing)
     * @return static
     */
    public function effectiveRange(Carbon $from, ?Carbon $until = null)
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => $from,
            'effective_until' => $until,
        ]);
    }

    /**
     * Create a schedule assignment with an end date
     *
     * @return static
     */
    public function withEndDate()
    {
        return $this->state(fn (array $attributes) => [
            'effective_until' => Carbon::parse($attributes['effective_from'])->addMonths(fake()->numberBetween(1, 6)),
        ]);
    }

    /**
     * Create a current/ongoing schedule assignment
     *
     * @return static
     */
    public function current()
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => now(), // Will be set in the DemoSeeder
            'effective_until' => null,
        ]);
    }
}
