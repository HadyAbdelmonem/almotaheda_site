<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = 'قسم '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug(fake()->unique()->words(2, true)),
            'icon' => 'zap',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
