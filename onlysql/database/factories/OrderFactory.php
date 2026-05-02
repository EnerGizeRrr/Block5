<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Рандомные статусы
        $status = $this->faker->randomElement(['paid', 'paid', 'paid', 'paid', 'paid', 'paid', 'paid', 'cancelled', 'new', 'new']);

        return [
            'status' => $status,
            'total_amount' => $this->faker->randomFloat(2, 10, 5000),
            'created_at' => $this->faker->dateTimeBetween('-2 years'),
        ];
    }
}