<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proofhub_task_id' => Str::uuid(),
            'proofhub_project_id' => null, // To be set when creating tasks
            'name' => fake()->randomElement([
                'Design homepage mockup',
                'Implement user authentication',
                'Create content for blog',
                'Fix responsive layout issues',
                'Optimize database queries',
                'Setup analytics tracking',
                'Create user documentation',
                'Conduct user testing',
                'Set up CI/CD pipeline',
                'Add multilingual support',
                'Debug payment gateway',
                'Review pull requests',
                'Create release notes',
                'Design social media assets',
                'Implement API endpoints',
            ]),
        ];
    }
}
