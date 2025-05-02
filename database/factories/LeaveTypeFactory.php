<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'odoo_leave_type_id' => fake()->unique()->numberBetween(1, 100),
            'name' => fake()->unique()->randomElement([
                'Vacation',
                'Sick Leave',
                'Maternity Leave',
                'Paternity Leave',
                'Personal Leave',
                'Work From Home',
                'Training',
                'Bereavement Leave',
                'Public Holiday',
                'Unpaid Leave',
            ]),
            'active' => true,
        ];
    }
}
