<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'timezone' => 'Europe/Madrid',
            'department_id' => null,
            'job_title' => fake()->jobTitle(),
            'user_type' => RoleType::User,
            'do_not_track' => false,
            'muted_notifications' => false,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['user_type' => RoleType::Admin]);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['user_type' => RoleType::Maintenance]);
    }

    public function withoutAccount(): static
    {
        return $this->state(fn () => ['password' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'manually_archived_at' => now(),
        ]);
    }

    public function doNotTrack(): static
    {
        return $this->state(fn () => ['do_not_track' => true]);
    }
}
