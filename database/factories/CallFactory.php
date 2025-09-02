<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'call_id' => 'CALL-' . fake()->unique()->numberBetween(10000, 99999),
            'status' => fake()->randomElement(Call::STATUSES),
            'total_attempts' => fake()->numberBetween(0, 5),
            'created_by' => User::inRandomOrder()->first()?->id ?? 1,
        ];
    }

    /**
     * Create pending calls
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Call::STATUS_PENDING,
            'assigned_to' => null,
            'assigned_by' => null,
            'assigned_at' => null,
        ]);
    }

    /**
     * Create assigned calls
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Call::STATUS_ASSIGNED,
            'assigned_to' => User::inRandomOrder()->first()?->id ?? 1,
            'assigned_by' => User::inRandomOrder()->first()?->id ?? 1,
            'assigned_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create completed calls
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Call::STATUS_COMPLETED,
            'assigned_to' => User::inRandomOrder()->first()?->id ?? 1,
            'assigned_by' => User::inRandomOrder()->first()?->id ?? 1,
            'assigned_at' => fake()->dateTimeBetween('-1 week', '-1 day'),
            'total_attempts' => fake()->numberBetween(1, 3),
            'last_attempt_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'last_attempt_by' => User::inRandomOrder()->first()?->id ?? 1,
        ]);
    }
}
