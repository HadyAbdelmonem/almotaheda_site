<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true).' '.fake()->bothify('??-####');

        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => 'منتج كهرباء '.$name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'model_code' => strtoupper(fake()->bothify('??#####')),
            'short_description' => 'وصف مختصر تجريبي للمنتج',
            'description' => 'وصف تجريبي قابل للتعديل من لوحة التحكم.',
            'unit_code' => 'piece',
            'min_order_quantity' => 1,
            'quantity_step' => 1,
            'cost_price' => 100,
            'price' => 250,
            'stock_quantity' => 20,
            'track_stock' => true,
            'is_active' => true,
            'sales_count' => fake()->numberBetween(0, 120),
        ];
    }

    public function onSale(): static
    {
        return $this->state(fn () => ['sale_price' => 199, 'sale_ends_at' => now()->addDays(10)]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 3, 'low_stock_threshold' => 5]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }

    public function byMeter(): static
    {
        return $this->state(fn () => ['unit_code' => 'meter', 'quantity_step' => 0.25, 'min_order_quantity' => 1]);
    }
}
