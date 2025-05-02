<?php

declare(strict_types=1);

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
            'type' => fake()->randomElement(['employee', 'department', 'category']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => fake()->randomElement(['validate', 'confirm', 'refuse', 'validate1', 'draft', 'cancel']),
            'duration_days' => $durationDays,
            'user_id' => null, // To be set when creating leaves
            'department_id' => null, // Will be set for department leaves
            'category_id' => null, // Will be set for category leaves
            'leave_type_id' => null, // To be set when creating leaves
            'request_hour_from' => null, // Will be set for half-day leaves
            'request_hour_to' => null, // Will be set for half-day leaves
        ];
    }

    /**
     * Create a leave with approved status
     *
     * @return static
     */
    public function approved()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validate',
        ]);
    }

    /**
     * Create a leave with pending approval status
     *
     * @return static
     */
    public function pending()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirm',
        ]);
    }

    /**
     * Create a leave with refused status
     *
     * @return static
     */
    public function refused()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refuse',
        ]);
    }

    /**
     * Create a regular user leave
     *
     * @return static
     */
    public function regular()
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'employee',
            'department_id' => null,
            'category_id' => null,
        ]);
    }

    /**
     * Create a department leave
     *
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
     * @return static
     */
    public function forDateRange(Carbon $start, Carbon $end)
    {
        $durationDays = $start->diffInDays($end) + 1;

        return $this->state(fn (array $attributes) => [
            'start_date' => $start->startOfDay(),
            'end_date' => $end->endOfDay(),
            'duration_days' => $durationDays,
        ]);
    }

    /**
     * Create a half-day leave in the morning
     *
     * @return static
     */
    public function halfDayMorning()
    {
        // Standard 4-hour morning leave (8:00-12:00)
        return $this->state(fn (array $attributes) => [
            'duration_days' => 0.5,
            'request_hour_from' => 8.0,
            'request_hour_to' => 12.0,
            // For half-day leaves, we need to make sure the start and end date are the same day
            'end_date' => isset($attributes['start_date']) ? Carbon::parse($attributes['start_date'])->endOfDay() : now()->endOfDay(),
        ]);
    }

    /**
     * Create a half-day leave in the afternoon
     *
     * @return static
     */
    public function halfDayAfternoon()
    {
        // Standard 4-hour afternoon leave (13:00-17:00)
        return $this->state(fn (array $attributes) => [
            'duration_days' => 0.5,
            'request_hour_from' => 13.0,
            'request_hour_to' => 17.0,
            // For half-day leaves, we need to make sure the start and end date are the same day
            'end_date' => isset($attributes['start_date']) ? Carbon::parse($attributes['start_date'])->endOfDay() : now()->endOfDay(),
        ]);
    }

    /**
     * Create a full-day leave
     *
     * @return static
     */
    public function fullDay()
    {
        return $this->state(fn (array $attributes) => [
            'duration_days' => 1.0,
            'request_hour_from' => null,
            'request_hour_to' => null,
            'end_date' => isset($attributes['start_date']) ? Carbon::parse($attributes['start_date'])->endOfDay() : now()->endOfDay(),
        ]);
    }

    /**
     * Create a leave with first approval status
     *
     * @return static
     */
    public function firstApproved()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validate1',
        ]);
    }

    /**
     * Create a leave with draft status
     *
     * @return static
     */
    public function draft()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Create a leave with cancelled status
     *
     * @return static
     */
    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancel',
        ]);
    }
}
