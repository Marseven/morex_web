<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        $targetAmount = fake()->numberBetween(100000, 5000000);

        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement([
                'Fonds d\'urgence',
                'Vacances',
                'Nouvelle voiture',
                'Apport immobilier',
            ]),
            'type' => fake()->randomElement(['savings', 'investment', 'custom']),
            'target_amount' => $targetAmount,
            'current_amount' => fake()->numberBetween(0, $targetAmount),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'account_id' => null,
            'status' => 'active',
            'color' => fake()->hexColor(),
            'icon' => 'target',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'current_amount' => $attributes['target_amount'],
        ]);
    }

    public function withProgress(int $percentage): static
    {
        return $this->state(function (array $attributes) use ($percentage) {
            $target = $attributes['target_amount'];
            return [
                'current_amount' => (int) ($target * $percentage / 100),
            ];
        });
    }

    public function withTarget(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'target_amount' => $amount,
            'current_amount' => 0,
        ]);
    }
}
