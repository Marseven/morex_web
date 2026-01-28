<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    public function definition(): array
    {
        $initialAmount = fake()->numberBetween(10000, 500000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Prêt ami',
                'Dette famille',
                'Crédit téléphone',
                'Avance collègue',
            ]),
            'type' => fake()->randomElement(['debt', 'credit']),
            'initial_amount' => $initialAmount,
            'current_amount' => $initialAmount,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'description' => fake()->optional()->sentence(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'status' => 'active',
            'color' => fake()->hexColor(),
        ];
    }

    public function debt(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debt',
            'name' => 'Je dois à ' . fake()->name(),
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
            'name' => fake()->name() . ' me doit',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'current_amount' => 0,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'status' => 'active',
        ]);
    }

    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'initial_amount' => $amount,
            'current_amount' => $amount,
        ]);
    }
}
