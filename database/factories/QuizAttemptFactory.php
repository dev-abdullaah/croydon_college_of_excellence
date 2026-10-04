<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_slug' => $this->faker->slug(2),
            'paper_slug' => $this->faker->slug(2),
            'paper_kind' => $this->faker->randomElement(['knowledge_check', 'classroom_mock', 'mock_test']),
            'paper_number' => $this->faker->numberBetween(1, 10),
            'answers' => [],
            'score' => null,
            'passed' => false,
            'started_at' => now(),
            'submitted_at' => null,
            'time_taken_seconds' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'answers' => ['a', 'b', 'c', 'd'],
            'submitted_at' => null,
        ]);
    }

    public function completed(int $score = 80, bool $passed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'answers' => ['a', 'b', 'c', 'd'],
            'score' => $score,
            'passed' => $passed,
            'submitted_at' => now(),
            'time_taken_seconds' => $this->faker->numberBetween(300, 1800),
        ]);
    }

    public function knowledgeCheck(): static
    {
        return $this->state(fn (array $attributes) => [
            'paper_kind' => 'knowledge_check',
        ]);
    }

    public function classroomMock(): static
    {
        return $this->state(fn (array $attributes) => [
            'paper_kind' => 'classroom_mock',
        ]);
    }

    public function mockTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'paper_kind' => 'mock_test',
        ]);
    }
}