<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'odoo_leave_type_id' => fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->word(),
            'request_unit' => 'day',
            'active' => true,
            'is_unpaid' => false,
            'requires_allocation' => false,
            'validation_type' => 'hr',
            'limit' => false,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn () => ['is_unpaid' => true]);
    }
}
