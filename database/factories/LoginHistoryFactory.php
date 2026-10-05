<?php

namespace Database\Factories;

use App\Models\LoginHistory;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginHistoryFactory extends Factory
{
    protected $model = LoginHistory::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'email' => $this->faker->safeEmail(),
            'ip_address' => $this->faker->ipv4(),
            'device_type' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'operating_system' => $this->faker->randomElement(['Windows 10', 'macOS', 'iOS', 'Android', 'Linux']),
            'user_agent' => $this->faker->userAgent(),
            'session_id' => $this->faker->uuid(),
            'status' => $this->faker->randomElement(['success', 'failed', 'revoked']),
            'login_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'logout_at' => $this->faker->optional(0.3)->dateTimeBetween('-29 days', 'now'),
        ];
    }

    public function success(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'logout_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
            'logout_at' => now(),
        ]);
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
            'logout_at' => null,
            'login_at' => now()->subMinutes($this->faker->numberBetween(1, 120)),
        ]);
    }
}