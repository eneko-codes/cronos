<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'proofhub_task_id' => fake()->unique()->numberBetween(1, 99999),
            'proofhub_project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'status' => 'active',
            'due_date' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'description' => fake()->optional()->sentence(),
            'tags' => null,
        ];
    }
}
