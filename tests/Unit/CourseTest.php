<?php

namespace Tests\Unit;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_formatted_price_in_gbp(): void
    {
        $course = Course::create([
            'slug' => 'test-course',
            'name' => 'Test Course',
            'description' => 'Test',
            'price' => 9900, // £99.00 in pence
            'currency' => 'gbp',
            'is_active' => true,
        ]);

        $this->assertEquals('£99', $course->formattedPrice());
    }

    public function test_formatted_price_with_pence(): void
    {
        $course = Course::create([
            'slug' => 'test-course-pence',
            'name' => 'Test Course with Pence',
            'description' => 'Test',
            'price' => 4950, // £49.50 in pence
            'currency' => 'gbp',
            'is_active' => true,
        ]);

        $this->assertEquals('£49.50', $course->formattedPrice());
    }

    public function test_formatted_price_handles_zero(): void
    {
        $course = Course::create([
            'slug' => 'free-course',
            'name' => 'Free Course',
            'description' => 'Test',
            'price' => 0,
            'currency' => 'gbp',
            'is_active' => true,
        ]);

        $this->assertEquals('£0', $course->formattedPrice());
    }

    public function test_formatted_price_handles_non_zero_cents(): void
    {
        $course = Course::create([
            'slug' => 'test-course-99',
            'name' => 'Test Course',
            'description' => 'Test',
            'price' => 9999, // £99.99
            'currency' => 'gbp',
            'is_active' => true,
        ]);

        $this->assertEquals('£99.99', $course->formattedPrice());
    }
}