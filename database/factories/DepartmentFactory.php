<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'odoo_department_id' => fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->company().' '.fake()->randomElement(['Engineering', 'Marketing', 'Sales', 'Operations']),
            'active' => true,
            'odoo_manager_id' => null,
            'odoo_parent_department_id' => null,
        ];
    }
}
