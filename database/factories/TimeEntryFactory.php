<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'proofhub_time_entry_id' => fake()->unique()->numberBetween(1, 99999),
            'user_id' => User::factory(),
            'proofhub_project_id' => Project::factory(),
            'proofhub_task_id' => null,
            'status' => 'approved',
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'duration_seconds' => fake()->numberBetween(1800, 14400),
            'billable' => fake()->boolean(70),
            'tags' => null,
        ];
    }
}
