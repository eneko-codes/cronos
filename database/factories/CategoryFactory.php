<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'odoo_category_id' => fake()->unique()->numberBetween(1, 100),
            'name' => fake()->unique()->randomElement([
                'Developers',
                'Designers',
                'Project Managers',
                'QA Engineers',
                'DevOps',
                'Administrative',
                'C-Level',
                'Product Owners',
                'Marketing Team',
                'Support Staff',
            ]),
        ];
    }
}
