<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_slug' => $this->faker->slug(2),
            'quiz_slug' => $this->faker->slug(2),
            'status' => 'in_progress',
            'current_position' => 1,
            'answers' => [],
            'score' => null,
            'total' => null,
            'percentage' => null,
            'passed' => false,
            'started_at' => now(),
            'submitted_at' => null,
            'time_taken_seconds' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'answers' => ['a', 'b', 'c', 'd'],
            'submitted_at' => null,
        ]);
    }

    public function completed(int $score = 80, bool $passed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'answers' => ['a', 'b', 'c', 'd'],
            'score' => $score,
            'total' => 10,
            'percentage' => $score * 10,
            'passed' => $passed,
            'submitted_at' => now(),
            'time_taken_seconds' => $this->faker->numberBetween(300, 1800),
        ]);
    }

    public function knowledgeCheck(): static
    {
        return $this->state(fn (array $attributes) => [
            'quiz_slug' => 'knowledge-check-' . $this->faker->numberBetween(1, 10),
        ]);
    }

    public function classroomMock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quiz_slug' => 'classroom-mock-' . $this->faker->numberBetween(1, 10),
        ]);
    }

    public function mockTest(): static
    {
        return $this->state(fn (array $attributes) => [
            'quiz_slug' => 'mock-test-' . $this->faker->numberBetween(1, 24),
        ]);
    }
}