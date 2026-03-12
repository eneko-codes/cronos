<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'odoo_category_id' => fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->words(2, true),
            'active' => true,
        ];
    }
}
