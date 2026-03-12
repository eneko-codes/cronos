<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'proofhub_project_id' => fake()->unique()->numberBetween(1, 99999),
            'title' => fake()->sentence(3),
            'status' => ['name' => 'Active', 'color' => '#38bdf8'],
            'description' => fake()->paragraph(),
        ];
    }
}
