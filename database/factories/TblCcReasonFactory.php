<?php

namespace Database\Factories;

use App\Models\TblCcReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TblCcReason>
 */
class TblCcReasonFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TblCcReason::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasonTypes = ['Not Paying', 'Paying', 'Partial Payment', 'Promise to Pay'];

        return [
            'reasonType' => fake()->randomElement($reasonTypes),
            'reasonName' => fake()->sentence(4),
            'reasonActive' => fake()->boolean(80), // 80% chance of being active
            'reasonRemark' => fake()->optional(0.7)->paragraph(), // 70% chance of having remark
            'personCreated' => null, // Will be set when user system is ready
            'personUpdated' => null,
            'personDeleted' => null,
        ];
    }

    /**
     * Indicate that the reason is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'reasonActive' => true,
        ]);
    }

    /**
     * Indicate that the reason is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'reasonActive' => false,
        ]);
    }

    /**
     * Indicate that the reason is for "Not Paying" type.
     */
    public function notPaying(): static
    {
        return $this->state(fn (array $attributes) => [
            'reasonType' => 'Not Paying',
        ]);
    }
}
