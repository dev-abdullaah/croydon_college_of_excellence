<?php

namespace Database\Factories;

use App\Models\LessonProgress;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_slug' => $this->faker->slug(2),
            'lesson_slug' => $this->faker->slug(2),
            'completed_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
        ]);
    }
}