<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLeave>
 */
class UserLeaveFactory extends Factory
{
    protected $model = UserLeave::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-30 days', '+30 days');
        $endDate = (clone $startDate)->modify('+1 day');

        return [
            'odoo_leave_id' => fake()->unique()->numberBetween(1, 99999),
            'type' => 'employee',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'validate',
            'duration_days' => 1.0,
            'user_id' => User::factory(),
            'department_id' => null,
            'category_id' => null,
            'leave_type_id' => LeaveType::factory(),
            'request_hour_from' => null,
            'request_hour_to' => null,
        ];
    }

    public function halfDay(): static
    {
        return $this->state(fn () => [
            'duration_days' => 0.5,
            'request_hour_from' => 8.0,
            'request_hour_to' => 12.0,
        ]);
    }

    public function upcoming(): static
    {
        $startDate = now()->addDays(fake()->numberBetween(3, 14));

        return $this->state(fn () => [
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(fake()->numberBetween(1, 5)),
        ]);
    }

    public function past(): static
    {
        $startDate = now()->subDays(fake()->numberBetween(5, 25));

        return $this->state(fn () => [
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(fake()->numberBetween(1, 3)),
        ]);
    }
}
