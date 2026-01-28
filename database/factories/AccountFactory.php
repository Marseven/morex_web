<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Compte Courant', 'Épargne', 'Espèces', 'Carte Crédit']),
            'type' => fake()->randomElement(['current', 'savings', 'cash', 'credit']),
            'initial_balance' => fake()->numberBetween(0, 1000000),
            'balance' => fn (array $attributes) => $attributes['initial_balance'],
            'color' => fake()->hexColor(),
            'icon' => 'wallet',
            'is_default' => false,
            'order_index' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function withBalance(int $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'initial_balance' => $balance,
            'balance' => $balance,
        ]);
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'current',
            'name' => 'Compte Courant',
        ]);
    }

    public function savings(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'savings',
            'name' => 'Épargne',
        ]);
    }
}
