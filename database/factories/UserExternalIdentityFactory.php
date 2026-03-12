<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserExternalIdentity>
 */
class UserExternalIdentityFactory extends Factory
{
    protected $model = UserExternalIdentity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => fake()->randomElement(Platform::cases()),
            'external_id' => (string) fake()->unique()->numberBetween(1000, 99999),
            'external_email' => fake()->safeEmail(),
            'is_manual_link' => false,
            'linked_by' => null,
        ];
    }
}
