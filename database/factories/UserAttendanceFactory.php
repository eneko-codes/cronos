<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAttendance>
 */
class UserAttendanceFactory extends Factory
{
    protected $model = UserAttendance::class;

    public function definition(): array
    {
        $clockIn = fake()->dateTimeBetween('-30 days', 'now');
        $durationSeconds = fake()->numberBetween(3600, 28800);

        return [
            'user_id' => User::factory(),
            'date' => $clockIn->format('Y-m-d'),
            'clock_in' => $clockIn,
            'clock_out' => (clone $clockIn)->modify("+{$durationSeconds} seconds"),
            'duration_seconds' => $durationSeconds,
            'is_remote' => fake()->boolean(60),
        ];
    }
}
