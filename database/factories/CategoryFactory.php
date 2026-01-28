<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Alimentation', 'Transport', 'Loisirs', 'Santé', 'Salaire']),
            'type' => fake()->randomElement(['expense', 'income']),
            'icon' => 'tag',
            'color' => fake()->hexColor(),
            'order_index' => 0,
            'is_system' => false,
            'budget_limit' => null,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'name' => fake()->randomElement(['Alimentation', 'Transport', 'Loisirs', 'Santé']),
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
            'name' => fake()->randomElement(['Salaire', 'Freelance', 'Investissements']),
        ]);
    }

    public function withBudget(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'budget_limit' => $limit,
            'type' => 'expense',
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }
}
