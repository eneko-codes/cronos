<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\User;
use App\Models\UserSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSchedule>
 */
class UserScheduleFactory extends Factory
{
    protected $model = UserSchedule::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'odoo_schedule_id' => Schedule::factory(),
            'effective_from' => now()->subMonths(6),
            'effective_until' => null,
        ];
    }
}
