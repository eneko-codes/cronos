<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'odoo_department_id' => fake()->unique()->numberBetween(1, 100),
            'name' => fake()->unique()->randomElement([
                'Engineering',
                'Marketing',
                'Sales',
                'Finance',
                'Human Resources',
                'Product',
                'Design',
                'Customer Success',
            ]),
            'active' => true,
        ];
    }
}
