<?php

namespace Database\Factories;

use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LoginTokenFactory extends Factory
{
    protected $model = LoginToken::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => Str::random(60),
            'expires_at' => now()->addHour(),
            'remember' => false,
        ];
    }
}
