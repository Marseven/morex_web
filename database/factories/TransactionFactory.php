<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'amount' => fake()->numberBetween(1000, 100000),
            'type' => fake()->randomElement(['expense', 'income']),
            'beneficiary' => fake()->company(),
            'description' => fake()->optional()->sentence(),
            'date' => fake()->dateTimeBetween('-1 month', 'now'),
            'transfer_to_account_id' => null,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
            'transfer_to_account_id' => Account::factory(),
            'category_id' => null,
        ]);
    }

    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
