<?php

namespace Tests\Unit;

use App\Content\CourseContent;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_content_loads_from_json_files(): void
    {
        $content = app(CourseContent::class);

        $this->assertTrue($content->hasContent('life-in-the-uk-course'));
        $this->assertTrue($content->hasContent('24-mock-tests'));
    }

    public function test_lesson_count_matches_json(): void
    {
        $content = app(CourseContent::class);

        $lessons = $content->lessons('life-in-the-uk-course');
        $this->assertGreaterThan(0, $lessons->count());

        // Each lesson should have required fields
        foreach ($lessons as $lesson) {
            $this->assertObjectHasProperty('slug', $lesson);
            $this->assertObjectHasProperty('title', $lesson);
            $this->assertObjectHasProperty('number', $lesson);
            $this->assertObjectHasProperty('items', $lesson);
        }
    }

    public function test_paper_count_matches_json(): void
    {
        $content = app(CourseContent::class);

        $papers = $content->quizzes('life-in-the-uk-course');
        $this->assertGreaterThan(0, $papers->count());

        foreach ($papers as $paper) {
            $this->assertObjectHasProperty('slug', $paper);
            $this->assertObjectHasProperty('title', $paper);
            $this->assertObjectHasProperty('number', $paper);
            $this->assertObjectHasProperty('questions', $paper);
            $this->assertGreaterThan(0, $paper->questions->count());
        }
    }

    public function test_each_question_has_required_fields(): void
    {
        $content = app(CourseContent::class);

        $papers = $content->quizzes('life-in-the-uk-course');
        foreach ($papers as $paper) {
            foreach ($paper->questions as $question) {
                $this->assertObjectHasProperty('position', $question);
                $this->assertObjectHasProperty('prompt', $question);
                $this->assertObjectHasProperty('options', $question);
                $this->assertObjectHasProperty('correct', $question);
                $this->assertObjectHasProperty('explanation', $question);
                $this->assertCount(4, $question->options);
                $this->assertContains($question->correct, ['a', 'b', 'c', 'd']);
            }
        }
    }
}