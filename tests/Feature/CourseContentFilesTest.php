<?php

namespace Tests\Feature;

use App\Content\CourseContent;
use App\Content\CourseContentException;
use App\Content\Quiz;
use App\Support\CourseFiles;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The course content: two JSON files in the repository.
 *
 * The lessons and papers are the product, and this is the only copy of them
 * anywhere - not a database, not the .docx files the text came from. These
 * tests are the reason to trust that copy.
 *
 * They are in three parts: that the files hold a complete, sane course; that
 * the site reads it; and that a broken file says what is wrong with it rather
 * than reaching a learner.
 */
class CourseContentFilesTest extends TestCase
{
    /* -----------------------------------------------------------------
     | The files themselves
     | ----------------------------------------------------------------- */

    public function test_both_content_files_are_committed_and_readable(): void
    {
        foreach ([CourseFiles::LESSONS, CourseFiles::PAPERS] as $relative) {
            $path = base_path($relative);

            $this->assertFileExists($path, "{$relative} must be kept in the repository: it is the course.");

            $decoded = json_decode((string) file_get_contents($path), true);

            $this->assertIsArray($decoded, "{$relative} must be valid JSON.");
            $this->assertSame(CourseContent::VERSION, $decoded['version'], "{$relative} is the wrong format version.");
            $this->assertArrayHasKey('courses', $decoded);
        }
    }

    public function test_the_files_hold_the_whole_course(): void
    {
        $lessons = $this->read(CourseFiles::LESSONS);
        $papers = $this->read(CourseFiles::PAPERS);

        $kinds = [];

        $cards = 0;

        foreach ($lessons['courses'] as $slug => $course) {
            $this->assertArrayNotHasKey('papers', $course, "{$slug} has no papers in the lessons file.");
            $this->assertCount(10, $course['lessons'], "{$slug} should have ten lessons.");

            foreach ($course['lessons'] as $lesson) {
                $cards += count($lesson['items']);
            }
        }

        $questions = 0;

        foreach ($papers['courses'] as $slug => $course) {
            $this->assertArrayNotHasKey('lessons', $course, "{$slug} has no lessons in the papers file.");

            foreach ($course['papers'] as $paper) {
                $kinds[$paper['kind']] = ($kinds[$paper['kind']] ?? 0) + 1;
                $questions += count($paper['questions']);
            }
        }

        $this->assertSame(1000, $cards, 'The course is ten lessons of a hundred study cards.');
        $this->assertSame(820, $questions);

        ksort($kinds);

        $this->assertSame(
            ['classroom_mock' => 6, 'knowledge_check' => 10, 'mock_test' => 24],
            $kinds,
            'The £99 course sells 10 knowledge checks and 6 classroom papers; the £49 pack sells 24 mocks.'
        );
    }

    public function test_every_question_is_sittable(): void
    {
        $count = 0;

        foreach ($this->read(CourseFiles::PAPERS)['courses'] as $slug => $course) {
            foreach ($course['papers'] as $paper) {
                $where = "{$slug}/{$paper['slug']}";

                $this->assertContains($paper['kind'], Quiz::kindOrder(), "{$where} is not a kind of paper.");
                $this->assertNotSame('', trim((string) $paper['title']), "{$where} has no title.");
                $this->assertGreaterThan(0, $paper['time_limit_minutes'], "{$where} has no time limit.");
                $this->assertGreaterThan(0, $paper['pass_mark_percent'], "{$where} has no pass mark.");

                foreach ($paper['questions'] as $index => $question) {
                    $at = "{$where} question ".($index + 1);

                    $this->assertNotSame('', trim((string) $question['prompt']), "{$at} has no question text.");
                    $this->assertNotSame('', trim((string) $question['explanation']), "{$at} has no explanation.");

                    $this->assertSame(
                        ['a', 'b', 'c', 'd'],
                        array_keys($question['options']),
                        "{$at} should offer options a to d, in that order."
                    );

                    foreach ($question['options'] as $letter => $text) {
                        $this->assertNotSame('', trim((string) $text), "{$at} option {$letter} is empty.");
                    }

                    // The answer key has to point at an option that exists, or
                    // the question cannot be marked and a learner finds out at
                    // the results page.
                    $this->assertArrayHasKey(
                        $question['correct'],
                        $question['options'],
                        "{$at} says the answer is \"{$question['correct']}\", which is not one of its options."
                    );

                    $count++;
                }
            }
        }

        $this->assertSame(820, $count);
    }

    public function test_every_study_card_has_both_halves(): void
    {
        $count = 0;

        foreach ($this->read(CourseFiles::LESSONS)['courses'] as $slug => $course) {
            foreach ($course['lessons'] as $lesson) {
                $this->assertNotSame('', trim((string) $lesson['title']), "{$slug}/{$lesson['slug']} has no title.");
                $this->assertNotEmpty($lesson['items'], "{$slug}/{$lesson['slug']} has no study cards.");

                foreach ($lesson['items'] as $index => $item) {
                    $at = "{$slug}/{$lesson['slug']} card ".($index + 1);

                    $this->assertNotSame('', trim((string) $item['question']), "{$at} has no question.");
                    $this->assertNotSame('', trim((string) $item['answer']), "{$at} has no answer.");

                    $count++;
                }
            }
        }

        $this->assertSame(1000, $count);
    }

    /**
     * Every number a paper shows a learner is worth checking against the real
     * thing: 10 questions on a knowledge check, 24 on a mock, 75% to pass, and
     * 45 minutes for a mock against 10 for a check.
     */
    public function test_the_papers_are_the_shape_they_are_sold_as(): void
    {
        foreach ($this->read(CourseFiles::PAPERS)['courses'] as $course) {
            foreach ($course['papers'] as $paper) {
                $where = "{$paper['slug']}";

                $this->assertSame(
                    $paper['kind'] === Quiz::KIND_KNOWLEDGE_CHECK ? 10 : 24,
                    count($paper['questions']),
                    "{$where} has the wrong number of questions for a {$paper['kind']}."
                );

                $this->assertSame(75, $paper['pass_mark_percent'], "{$where} should pass at 75%, as the real test does.");
                $this->assertSame(
                    $paper['kind'] === Quiz::KIND_KNOWLEDGE_CHECK ? 10 : 45,
                    $paper['time_limit_minutes'],
                    "{$where} has the wrong time limit for a {$paper['kind']}."
                );
            }
        }
    }

    /**
     * A knowledge check's number is the lesson it revises. That is the only
     * thing linking a lesson to its check, and the lesson page relies on it.
     */
    public function test_every_knowledge_check_lines_up_with_a_lesson(): void
    {
        $content = app(CourseContent::class);

        $checked = 0;

        foreach ($this->read(CourseFiles::PAPERS)['courses'] as $slug => $course) {
            foreach ($course['papers'] as $paper) {
                if ($paper['kind'] !== Quiz::KIND_KNOWLEDGE_CHECK) {
                    continue;
                }

                $checked++;

                $this->assertNotNull(
                    $content->lessonByNumber($slug, $paper['number']),
                    "{$paper['slug']} revises lesson {$paper['number']}, which does not exist."
                );
            }
        }

        $this->assertSame(10, $checked);
    }

    /* -----------------------------------------------------------------
     | Reading it
     | ----------------------------------------------------------------- */

    public function test_site_loads_the_whole_course_from_the_files(): void
    {
        $summary = app(CourseContent::class)->summary();

        $this->assertSame(
            ['lessons' => 10, 'cards' => 1000, 'quizzes' => 40, 'questions' => 820],
            $summary
        );
    }

    public function test_the_two_courses_get_their_own_material(): void
    {
        $content = app(CourseContent::class);

        // The £99 course: ten lessons, ten knowledge checks, six classroom
        // papers. The £49 pack: twenty-four mocks and nothing else.
        $this->assertSame(10, $content->lessons('life-in-the-uk-course')->count());
        $this->assertSame(16, $content->quizzes('life-in-the-uk-course')->count());

        $this->assertSame(0, $content->lessons('24-mock-tests')->count(), 'The £49 pack has no lessons.');
        $this->assertSame(24, $content->quizzes('24-mock-tests')->count());

        // A pack paper is not reachable through the course, or the other way
        // round: the purchase of one course must not open the other.
        $this->assertNull($content->quiz('life-in-the-uk-course', 'mock-test-1'));
        $this->assertNull($content->quiz('24-mock-tests', 'knowledge-check-1'));
    }

    public function test_a_course_with_no_content_is_simply_empty(): void
    {
        $content = app(CourseContent::class);

        $this->assertSame([], $content->lessons('not-a-course')->all());
        $this->assertSame([], $content->quizzes('not-a-course')->all());
        $this->assertNull($content->lesson('not-a-course', 'lesson-1'));
        $this->assertNull($content->quiz('not-a-course', 'mock-test-1'));
        $this->assertFalse($content->hasContent('not-a-course'));
    }

    public function test_papers_are_handed_over_in_the_order_a_learner_works_them(): void
    {
        $groups = app(CourseContent::class)->quizzesByKind('life-in-the-uk-course');

        // The £99 course sells knowledge checks and classroom papers; the
        // mocks are the other course's pack. The kinds present come back in
        // the order a learner works through them.
        $this->assertSame(
            array_slice(Quiz::kindOrder(), 0, 2),
            array_keys($groups)
        );
        $this->assertCount(10, $groups[Quiz::KIND_KNOWLEDGE_CHECK]);
        $this->assertCount(6, $groups[Quiz::KIND_CLASSROOM_MOCK]);

        $numbers = $groups[Quiz::KIND_KNOWLEDGE_CHECK]->pluck('number')->all();

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $numbers);
    }

    public function test_a_question_is_identified_by_its_place_on_the_paper(): void
    {
        $paper = app(CourseContent::class)->quiz('24-mock-tests', 'mock-test-1');

        $this->assertNotNull($paper);
        $this->assertSame(24, $paper->questionCount());
        $this->assertSame(18, $paper->passMarkCount(), '75% of 24 questions is 18.');
        $this->assertSame(45, $paper->time_limit_minutes);

        // Positions run 1 to 24 with no gap, because a saved answer is keyed
        // by them.
        $this->assertSame(
            range(1, 24),
            $paper->questions->pluck('position')->all()
        );

        $this->assertNull($paper->questionAt(0), 'Question 0 does not exist.');
        $this->assertNull($paper->questionAt(25), 'Question 25 does not exist.');
        $this->assertSame($paper->questions->first(), $paper->questionAt(1));
    }

    public function test_a_question_knows_whether_it_was_answered_correctly(): void
    {
        $paper = app(CourseContent::class)->quiz('24-mock-tests', 'mock-test-1');
        $question = $paper->questionAt(1);

        $this->assertTrue($question->isCorrect($question->correct));
        $this->assertTrue($question->isCorrect(strtoupper($question->correct)), 'Case should not matter.');
        $this->assertFalse($question->isCorrect('z'));
        $this->assertFalse($question->isCorrect(null), 'A blank answer is not a correct one.');
        $this->assertFalse($question->wasAnswered(null));

        $this->assertSame(
            $question->options[$question->correct],
            $question->optionText($question->correct)
        );
        $this->assertNull($question->optionText('z'), 'There is no such option.');
    }

    /* -----------------------------------------------------------------
     | A broken file
     |
     | The content is edited by hand and produced by a command that reads Word
     | documents, so it will one day be wrong. When it is, the site must say
     | what is wrong and where, rather than serving a paper that cannot be
     | finished.
     | ----------------------------------------------------------------- */

    public function test_a_missing_file_says_where_it_should_be(): void
    {
        // No lessons file at all. The store has to say where it expected one
        // rather than guessing.
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/lesson-content\.json/');

        $this->usingContent([], fn () => $this->content()->lessons('anything'));
    }

    public function test_a_file_that_is_not_json_says_so(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/not valid JSON/');

        $this->usingContent(
            ['lessons' => '{ this is not json'],
            fn () => $this->content()->lessons('x')
        );
    }

    public function test_a_file_from_a_future_format_is_refused_rather_than_misread(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/version 9.*reads version 1/');

        $this->usingContent(
            ['lessons' => json_encode(['version' => 9, 'courses' => []])],
            fn () => $this->content()->lessons('x')
        );
    }

    public function test_a_lesson_missing_its_title_is_named_in_the_error(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/lesson-content\.json.*lessons 1.*has no "title"/s');

        $this->usingContent(
            ['lessons' => json_encode(['version' => 1, 'courses' => [
                'c' => ['name' => 'C', 'lessons' => [
                    ['number' => 1, 'slug' => 'lesson-1', 'items' => []],
                ]],
            ]])],
            fn () => $this->content()->lessons('c')
        );
    }

    public function test_a_study_card_missing_its_answer_is_named_in_the_error(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/lessons 1.*card 2.*has no "answer"/s');

        $this->usingContent(
            ['lessons' => json_encode(['version' => 1, 'courses' => [
                'c' => ['name' => 'C', 'lessons' => [
                    [
                        'number' => 1, 'slug' => 'lesson-1', 'title' => 'One',
                        'items' => [
                            ['question' => 'Q1', 'answer' => 'A1'],
                            ['question' => 'Q2'],
                        ],
                    ],
                ]],
            ]])],
            fn () => $this->content()->lessons('c')
        );
    }

    public function test_a_paper_of_an_unknown_kind_is_refused(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/not a kind of paper.*mock_test/s');

        $this->usingContent(
            ['papers' => $this->papersFile(['c' => $this->paper(['kind' => 'pop_quiz'])])],
            fn () => $this->content()->quizzes('c')
        );
    }

    public function test_an_answer_key_pointing_at_no_option_is_refused(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches("/question 1.*correct answer is 'e'/s");

        $this->usingContent(
            ['papers' => $this->papersFile(['c' => $this->paper(['questions' => [[
                'prompt' => 'Q1',
                'options' => ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'],
                'correct' => 'e',
                'explanation' => 'Because.',
            ]]])])],
            fn () => $this->content()->quizzes('c')
        );
    }

    public function test_a_question_missing_an_option_is_refused(): void
    {
        $this->expectException(CourseContentException::class);
        $this->expectExceptionMessageMatches('/question 1.*option d is missing or empty/s');

        $this->usingContent(
            ['papers' => $this->papersFile(['c' => $this->paper(['questions' => [[
                'prompt' => 'Q1',
                'options' => ['a' => 'A', 'b' => 'B', 'c' => 'C'],
                'correct' => 'a',
                'explanation' => 'Because.',
            ]]])])],
            fn () => $this->content()->quizzes('c')
        );
    }

    /**
     * A file edited by hand can be out of order. The order a learner meets the
     * material is settled when it is read, not trusted from the file, so a
     * lesson filed out of sequence still reads in sequence.
     */
    public function test_the_order_a_learner_meets_the_material_is_settled_on_reading(): void
    {
        $levels = $this->usingContent(
            ['lessons' => json_encode(['version' => 1, 'courses' => [
                'c' => ['name' => 'C', 'lessons' => [
                    ['number' => 3, 'slug' => 'lesson-3', 'title' => 'Three', 'items' => []],
                    ['number' => 1, 'slug' => 'lesson-1', 'title' => 'One', 'items' => []],
                    ['number' => 2, 'slug' => 'lesson-2', 'title' => 'Two', 'items' => []],
                ]],
            ]])],
            fn () => $this->content()->lessons('c')
        );

        $this->assertSame([1, 2, 3], $levels->pluck('number')->all());
    }

    /**
     * Positions are not stored, they are read off the order in the file. So a
     * question moved in the file becomes a different question at that place -
     * and a half-finished attempt's answers follow the place, not the text.
     */
    public function test_a_questions_place_is_its_line_in_the_file(): void
    {
        $paper = $this->usingContent(
            ['papers' => $this->papersFile(['c' => $this->paper(['slug' => 'mock-test-1', 'questions' => [
                ['prompt' => 'First', 'options' => $this->answerOptions(), 'correct' => 'a', 'explanation' => 'x'],
                ['prompt' => 'Second', 'options' => $this->answerOptions(), 'correct' => 'b', 'explanation' => 'x'],
            ]])])],
            fn () => $this->content()->quiz('c', 'mock-test-1')
        );

        $this->assertNotNull($paper);
        $this->assertSame('First', $paper->questionAt(1)->prompt);
        $this->assertSame(1, $paper->questionAt(1)->position);
        $this->assertSame('Second', $paper->questionAt(2)->prompt);
        $this->assertSame(2, $paper->questionAt(2)->position);
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    private function content(): CourseContent
    {
        return app(CourseContent::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $relative): array
    {
        $decoded = json_decode((string) file_get_contents(base_path($relative)), true);

        $this->assertIsArray($decoded, "{$relative} must be valid JSON.");

        return $decoded;
    }

    /**
     * Point the store at a fixture, run a body, then put the real files back.
     *
     * `$files` holds the two files' contents, keyed 'lessons' and 'papers'. A
     * key left out leaves that file absent, which is how a missing-file test
     * is made. The store is always put back on the real content afterwards,
     * including when the body fails part way through - the real files are the
     * only copy of the course, and neither test ever writes to them.
     *
     * @param  array{lessons?: string|null, papers?: string|null}  $files
     * @param  callable  $body
     * @return mixed  whatever the body returns
     */
    private function usingContent(array $files, callable $body): mixed
    {
        $directory = storage_path('framework/testing/course-content-files');

        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);

        config([
            'course-content.lessons' => $directory.'/lesson-content.json',
            'course-content.quizzes' => $directory.'/quiz-content.json',
        ]);

        foreach (['lessons' => 'lesson', 'papers' => 'quiz'] as $key => $name) {
            if (isset($files[$key])) {
                File::put($directory."/{$name}-content.json", (string) $files[$key]);
            }
        }

        $this->content()->flush();

        try {
            return $body();
        } finally {
            File::deleteDirectory($directory);

            config([
                'course-content.lessons' => CourseFiles::LESSONS,
                'course-content.quizzes' => CourseFiles::PAPERS,
            ]);

            $this->content()->flush();
        }
    }

    /**
     * A papers file holding one paper per course, with sensible defaults.
     *
     * @param  array<string, array<string, mixed>>  $courses  slug => paper overrides
     */
    private function papersFile(array $courses): string
    {
        $rows = [];

        foreach ($courses as $slug => $overrides) {
            $rows[$slug] = ['name' => $slug, 'papers' => [$this->paper($overrides)]];
        }

        return (string) json_encode(['version' => 1, 'courses' => $rows]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function paper(array $overrides = []): array
    {
        return $overrides + [
            'kind' => Quiz::KIND_MOCK_TEST,
            'number' => 1,
            'slug' => 'mock-test-1',
            'title' => 'Mock Test 1',
            'description' => null,
            'time_limit_minutes' => 45,
            'pass_mark_percent' => 75,
            'questions' => [[
                'prompt' => 'Q1',
                'options' => $this->answerOptions(),
                'correct' => 'a',
                'explanation' => 'Because.',
            ]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function answerOptions(): array
    {
        return ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'];
    }
}