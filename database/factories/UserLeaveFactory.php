<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserLeave>
 */
class UserLeaveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now();
        $durationDays = fake()->numberBetween(1, 5);
        $endDate = $startDate->copy()->addDays($durationDays - 1);
        
        return [
            'odoo_leave_id' => fake()->unique()->numberBetween(1000, 9999),
            'type' => fake()->randomElement(['regular', 'department', 'category']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'validate',
            'duration_days' => $durationDays,
            'user_id' => null, // To be set when creating leaves
            'department_id' => null, // Will be set for department leaves
            'category_id' => null, // Will be set for category leaves
            'leave_type_id' => null, // To be set when creating leaves
        ];
    }
    
    /**
     * Create a regular user leave
     *
     * @return static
     */
    public function regular()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'regular',
            'department_id' => null,
            'category_id' => null,
        ]);
    }
    
    /**
     * Create a department leave
     *
     * @param int $departmentId
     * @return static
     */
    public function forDepartment(int $departmentId)
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'department',
            'department_id' => $departmentId,
            'category_id' => null,
        ]);
    }
    
    /**
     * Create a category leave
     *
     * @param int $categoryId
     * @return static
     */
    public function forCategory(int $categoryId)
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'category',
            'department_id' => null,
            'category_id' => $categoryId,
        ]);
    }
    
    /**
     * Create a leave for a specific date range
     *
     * @param Carbon $start
     * @param Carbon $end
     * @return static
     */
    public function forDateRange(Carbon $start, Carbon $end)
    {
        $durationDays = $start->diffInDays($end) + 1;
        return $this->state(fn (array $attributes) => [
            'start_date' => $start,
            'end_date' => $end,
            'duration_days' => $durationDays,
        ]);
    }
} 