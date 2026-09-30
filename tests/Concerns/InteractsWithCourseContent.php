<?php

namespace Tests\Concerns;

use App\Content\CourseContent;
use App\Content\Lesson;
use App\Content\Quiz;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * Lets a test decide what course material the site sees.
 *
 * The real material is two JSON files in `database/data/`, read at request
 * time. A test that needs "a course with one lesson of forty-five cards and a
 * four-question paper" can say exactly that, and the site is pointed at a
 * fixture instead. That keeps such a test from being broken by an edit to the
 * real course, which is the point of a fixture.
 *
 * The real files are never touched: the store is pointed at a temporary
 * directory instead, and that directory is removed when the test ends.
 */
trait InteractsWithCourseContent
{
    /**
     * The fixture being built: one entry per course slug, holding the lessons
     * and papers written so far.
     *
     * @var array<string, array{lessons?: array<int, array>, papers?: array<int, array>}>
     */
    private array $contentFixture = [];

    private ?string $contentFixtureDirectory = null;

    /**
     * Start building a fixture. Nothing is visible to the site until a lesson
     * or a paper is added.
     */
    protected function useCourseContent(): static
    {
        if ($this->contentFixtureDirectory !== null) {
            return $this;
        }

        $this->contentFixtureDirectory = storage_path('framework/testing/course-content');

        File::deleteDirectory($this->contentFixtureDirectory);
        File::ensureDirectoryExists($this->contentFixtureDirectory);

        $this->contentFixture = [];

        config([
            'course-content.lessons' => $this->contentFixtureDirectory.'/lesson-content.json',
            'course-content.quizzes' => $this->contentFixtureDirectory.'/quiz-content.json',
        ]);

        $this->store()->flush();

        $this->beforeApplicationDestroyed(function () {
            File::deleteDirectory($this->contentFixtureDirectory);
        });

        return $this;
    }

    protected function store(): CourseContent
    {
        return app(CourseContent::class);
    }

    /**
     * One lesson from the fixture, as the site would hand it to a view.
     */
    protected function lesson(Course $course, string $slug): ?Lesson
    {
        return $this->store()->lesson($course->slug, $slug);
    }

    /**
     * One paper from the fixture, as the site would hand it to a view.
     */
    protected function paper(Course $course, string $slug): ?Quiz
    {
        return $this->store()->quiz($course->slug, $slug);
    }

    /**
     * Add lessons with predictable content, and return their slugs.
     *
     * The first card of every lesson says the same thing, so a test can assert
     * on it without repeating the text.
     *
     * @return array<int, string>
     */
    protected function makeLessons(Course $course, int $count, int $cardsEach = 3, int $from = 1): array
    {
        $this->useCourseContent();

        $slugs = [];

        for ($offset = 0; $offset < $count; $offset++) {
            $number = $from + $offset;
            $slug = "lesson-{$number}";

            $items = [];

            for ($position = 1; $position <= $cardsEach; $position++) {
                $items[] = [
                    'question' => $position === 1
                        ? 'What are the four fundamental values?'
                        : "Study card {$position} of lesson {$number}",
                    'answer' => $position === 1
                        ? 'Democracy, the rule of law, individual liberty and mutual respect.'
                        : "The answer to card {$position}.",
                ];
            }

            $this->contentFixture[$course->slug]['lessons'][] = [
                'number' => $number,
                'slug' => $slug,
                'title' => "Topic {$number}",
                'summary' => null,
                'items' => $items,
            ];

            $slugs[] = $slug;
        }

        $this->writeCourseContentFixture();

        return $slugs;
    }

    /**
     * Add a paper, and return its slug.
     *
     * `$correct` gives the right letter for each question, in order, so a test
     * can arrange a specific score. The letters cycle, so a paper of any length
     * is built from a short list.
     *
     * @param  array<int, string>  $correct
     */
    protected function makeQuiz(
        Course $course,
        string $kind,
        int $number,
        array $correct,
        ?int $questionCount = null,
        int $passMark = 75
    ): string {
        $this->useCourseContent();

        $questionCount ??= count($correct);
        $slug = str_replace('_', '-', $kind)."-{$number}";
        $questions = [];

        for ($position = 1; $position <= $questionCount; $position++) {
            $right = $correct[($position - 1) % count($correct)];
            $options = [];

            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $options[$letter] = $letter === $right
                    ? "The right answer to question {$position}."
                    : "A wrong answer to question {$position} ({$letter}).";
            }

            $questions[] = [
                'prompt' => "Question {$position}?",
                'options' => $options,
                'correct' => $right,
                'explanation' => "Question {$position} answer {$right} is correct.",
            ];
        }

        $this->contentFixture[$course->slug]['papers'][] = [
            'kind' => $kind,
            'number' => $number,
            'slug' => $slug,
            'title' => ucfirst(str_replace('_', ' ', $kind))." {$number}",
            'description' => "Paper {$number} for testing.",
            'time_limit_minutes' => $kind === 'knowledge_check' ? 10 : 45,
            'pass_mark_percent' => $passMark,
            'questions' => $questions,
        ];

        $this->writeCourseContentFixture();

        return $slug;
    }

    /**
     * Answer the first $howMany questions of a paper correctly, leaving it in
     * progress for the test to submit or inspect.
     */
    protected function answerCorrectly(User $user, Course $course, string $slug, int $howMany): void
    {
        $this->actingAs($user);

        $questions = $this->paper($course, $slug)?->questions ?? collect();

        foreach ($questions->where('position', '<=', $howMany) as $question) {
            $this->post(route('learn.quizzes.answer', [$course, $slug]), [
                'position' => $question->position,
                'answer' => $question->correct,
            ]);
        }
    }

    /**
     * Write the fixture out as the two files the site reads, and make the store
     * read them again.
     *
     * Called after every addition rather than once at the end, so the content is
     * live as soon as a test adds it.
     */
    private function writeCourseContentFixture(): void
    {
        $this->writeContentFile('lessons', 'lesson');
        $this->writeContentFile('papers', 'quiz');

        $this->store()->flush();
    }

    private function writeContentFile(string $key, string $name): void
    {
        $courses = [];

        foreach ($this->contentFixture as $slug => $content) {
            // A course is left out of a file it has nothing in, rather than
            // written with an empty array. This is the shape the real files
            // have, and the site relies on it.
            if (($content[$key] ?? []) === []) {
                continue;
            }

            $courses[$slug] = ['name' => $slug, $key => $content[$key]];
        }

        File::put(
            $this->contentFixtureDirectory."/{$name}-content.json",
            (string) json_encode(
                ['version' => 1, 'courses' => $courses],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }
}
