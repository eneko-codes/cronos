<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proofhub_time_entry_id' => fake()->unique()->numberBetween(10000, 99999),
            'user_id' => null, // To be set when creating entries
            'proofhub_project_id' => null, // To be set when creating entries
            'proofhub_task_id' => null, // Will be set for task-related entries
            'status' => fake()->randomElement(['active', 'deleted']),
            'description' => fake()->optional(0.7)->sentence(6), // 70% chance of having a description
            'date' => now(), // This will be overridden in the seeder
            'duration_seconds' => fake()->numberBetween(30 * 60, 8 * 60 * 60), // 30 mins to 8 hours
            'proofhub_created_at' => now(), // Will be properly set in the seeder
        ];
    }

    /**
     * Create a time entry for a specific date
     *
     * @return static
     */
    public function forDate(Carbon $date)
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    /**
     * Create a time entry for a specific task
     *
     * @return static
     */
    public function forTask(string $taskId)
    {
        return $this->state(fn (array $attributes) => [
            'proofhub_task_id' => $taskId,
        ]);
    }

    /**
     * Create a time entry with a specific duration
     *
     * @return static
     */
    public function withDuration(int $minutes)
    {
        return $this->state(fn (array $attributes) => [
            'duration_seconds' => $minutes * 60,
        ]);
    }
}
