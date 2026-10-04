<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\User;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'stripe_checkout_session_id' => 'cs_test_' . $this->faker->unique()->lexify('????????????????????????'),
            'stripe_payment_intent_id' => 'pi_' . $this->faker->unique()->lexify('????????????????????????'),
            'stripe_customer_id' => 'cus_' . $this->faker->unique()->lexify('????????????????????'),
            'stripe_event_id' => 'evt_' . $this->faker->unique()->lexify('????????????????????????'),
            'customer_email' => $this->faker->safeEmail(),
            'customer_name' => $this->faker->name(),
            'amount' => $this->faker->numberBetween(1000, 50000),
            'currency' => 'gbp',
            'status' => $this->faker->randomElement(['pending', 'paid', 'failed']),
            'paid_at' => $this->faker->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }
}