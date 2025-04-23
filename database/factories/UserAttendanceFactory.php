<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserAttendance>
 */
class UserAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isRemote = fake()->boolean(70); // 70% chance of remote work

        return [
            'user_id' => null, // To be set when creating records
            'date' => now(), // This will be overridden in the seeder
            'presence_seconds' => fake()->numberBetween(4 * 3600, 9 * 3600), // 4-9 hours in seconds
            'is_remote' => $isRemote,
            // Only used for non-remote attendance
            'start' => $isRemote ? null : Carbon::createFromTime(9, 0, 0),
            'end' => $isRemote ? null : Carbon::createFromTime(17, 0, 0),
        ];
    }

    /**
     * Create a remote work attendance record
     *
     * @return static
     */
    public function remote()
    {
        return $this->state(fn (array $attributes) => [
            'is_remote' => true,
            'start' => null,
            'end' => null,
        ]);
    }

    /**
     * Create an in-office attendance record
     *
     * @return static
     */
    public function inOffice()
    {
        return $this->state(fn (array $attributes) => [
            'is_remote' => false,
            'start' => Carbon::createFromTime(9, 0, 0),
            'end' => Carbon::createFromTime(17, 0, 0),
        ]);
    }

    /**
     * Create an attendance record for a specific date
     *
     * @return static
     */
    public function forDate(Carbon $date)
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
