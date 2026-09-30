<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\CourseContentExtractionFailedException;
use App\Services\CourseContentExtractor;
use App\Services\CourseContentParser;
use App\Support\DocxReader;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithCourseContent;
use Tests\TestCase;

/**
 * The learning area: reading lessons, sitting papers, seeing the result.
 *
 * Most tests here build a small fixture course, so a test states exactly the
 * lesson and paper it is about and cannot be broken by an edit to the real
 * course material. See InteractsWithCourseContent.
 *
 * The tests at the end read the real course the site actually serves, and the
 * ones after those read the original .docx documents, because the thing most
 * likely to be wrong is how a Word file is read and a hand-built fixture cannot
 * catch that.
 */
class LearningAreaTest extends TestCase
{
    use InteractsWithCourseContent;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseSeeder::class);
    }

    /* -----------------------------------------------------------------
     | Access control
     |
     | Access to the learning area must be exactly as strict as access to
     | the downloads. A URL is never enough.
     | ----------------------------------------------------------------- */

    public function test_a_guest_is_sent_to_login(): void
    {
        $course = $this->course();

        $this->get(route('learn.index', $course))->assertRedirect(route('login'));
    }

    public function test_a_visitor_without_the_purchase_gets_a_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('learn.index', $this->course()))
            ->assertForbidden();
    }

    public function test_an_unpaid_purchase_does_not_unlock_the_learning_area(): void
    {
        $user = User::factory()->create();
        $course = $this->course();

        Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->get(route('learn.index', $course))
            ->assertForbidden();
    }

    public function test_buying_the_course_does_not_unlock_the_mock_pack(): void
    {
        $buyer = $this->buyer($this->course());

        $this->actingAs($buyer)
            ->get(route('learn.index', $this->course('24-mock-tests')))
            ->assertForbidden();
    }

    public function test_buying_the_mock_pack_does_not_unlock_the_course_lessons(): void
    {
        $buyer = $this->buyer($this->course('24-mock-tests'));

        $this->actingAs($buyer)
            ->get(route('learn.index', $this->course()))
            ->assertForbidden();
    }

    public function test_a_purchaser_can_open_the_learning_area(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $this->makeLessons($course, 3);
        $this->makeQuiz($course, 'knowledge_check', 1, ['a', 'b', 'c', 'd']);

        $this->actingAs($buyer)
            ->get(route('learn.index', $course))
            ->assertOk()
            ->assertSee('Lesson 1')
            ->assertSee('Knowledge Checks');
    }

    /* -----------------------------------------------------------------
     | Lessons
     | ----------------------------------------------------------------- */

    public function test_a_lesson_is_paged_rather_than_dumped_on_one_page(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1, 45);

        $response = $this->actingAs($buyer)->get(route('learn.lessons.show', [$course, $lesson]));

        $response->assertOk();

        // Twenty cards on the first page of forty-five, not all forty-five.
        $this->assertCount(20, $response->viewData('items'));
        $this->assertSame(3, $response->viewData('items')->lastPage());
        $this->assertSame(45, $response->viewData('lesson')->itemCount());
    }

    public function test_a_page_past_the_end_of_a_lesson_shows_the_last_page(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1, 45);

        $response = $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson, 'page' => 99]));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('items')->currentPage());
        $this->assertCount(5, $response->viewData('items')->items());
    }

    public function test_a_lesson_shows_the_question_and_the_answer(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1, 1);

        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee('What are the four fundamental values?')
            ->assertSee('Democracy, the rule of law, individual liberty and mutual respect.');
    }

    public function test_a_lesson_can_be_marked_as_read(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);

        $this->actingAs($buyer)
            ->from(route('learn.lessons.show', [$course, $lesson]))
            ->post(route('learn.lessons.complete', [$course, $lesson]))
            ->assertRedirect(route('learn.lessons.show', [$course, $lesson]));

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $buyer->id,
            'course_slug' => $course->slug,
            'lesson_slug' => $lesson,
        ]);
    }

    public function test_marking_a_lesson_read_twice_does_not_duplicate_it(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);

        $url = route('learn.lessons.complete', [$course, $lesson]);

        $this->actingAs($buyer)->post($url);
        $this->actingAs($buyer)->post($url);
        $this->actingAs($buyer)->post($url);

        $this->assertSame(1, LessonProgress::where('user_id', $buyer->id)->count());
    }

    public function test_lesson_progress_is_per_user(): void
    {
        $course = $this->course();
        [$lesson] = $this->makeLessons($course, 1);

        $one = $this->buyer($course);
        $two = $this->buyer($course);

        $this->actingAs($one)->post(route('learn.lessons.complete', [$course, $lesson]));

        $this->assertSame(1, LessonProgress::where('user_id', $one->id)->count());
        $this->assertSame(0, LessonProgress::where('user_id', $two->id)->count());
    }

    public function test_a_lesson_from_another_course_cannot_be_reached(): void
    {
        $course = $this->course();
        $other = $this->course('24-mock-tests');
        $buyer = $this->buyer($course);

        // A lesson belonging to a course the buyer does not own.
        [$foreign] = $this->makeLessons($other, 1);

        // The buyer owns `course`, so passing `course` in the URL with the
        // other course's lesson must not resolve.
        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $foreign]))
            ->assertNotFound();
    }

    public function test_the_lesson_list_offers_the_matching_knowledge_check(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);
        $check = $this->makeQuiz($course, 'knowledge_check', 1, ['a', 'b', 'c', 'd']);

        // A knowledge check's number is the lesson it revises.
        $this->assertSame(
            $check,
            $this->store()->knowledgeCheck($course->slug, 1)?->slug
        );

        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee(route('learn.quizzes.play', [$course, $check]));
    }

    public function test_a_lesson_whose_number_has_no_knowledge_check_still_reads(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $this->makeLessons($course, 2);
        $this->makeQuiz($course, 'knowledge_check', 1, ['a', 'b', 'c', 'd']);

        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, 'lesson-2']))
            ->assertOk()
            ->assertDontSee(route('learn.quizzes.play', [$course, 'knowledge-check-1']));
    }

    /* -----------------------------------------------------------------
     | Sitting a paper
     | ----------------------------------------------------------------- */

    public function test_opening_a_paper_starts_an_attempt_on_the_first_question(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $response = $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('position'));
        $this->assertSame(0, $response->viewData('answered'));

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $buyer->id,
            'course_slug' => $course->slug,
            'quiz_slug' => $quiz,
            'status' => QuizAttempt::IN_PROGRESS,
        ]);
    }

    public function test_the_correct_answer_is_never_sent_to_the_browser_while_a_paper_is_in_progress(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['b', 'c', 'd', 'a'], 3);

        $explanation = $this->paper($course, $quiz)->questionAt(1)->explanation;

        $html = $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, $quiz]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            $explanation,
            $html,
            'The explanation reveals the right answer and must not appear on the play screen.'
        );
    }

    public function test_an_answer_is_saved_and_the_paper_moves_on(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 5);

        $this->actingAs($buyer)
            ->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => 1,
                'answer' => 'b',
            ])
            ->assertRedirect(route('learn.quizzes.play', [$course, $quiz, 'position' => 2]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame('b', $attempt->answerFor(1));
        $this->assertSame(2, $attempt->current_position);
        $this->assertSame(1, $attempt->answeredCount());
    }

    public function test_answers_survive_a_reload(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 5);

        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 1, 'answer' => 'a',
        ]);
        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 2, 'answer' => 'c',
        ]);

        // Reopening the paper resumes it rather than starting again.
        $response = $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $this->assertSame(2, $response->viewData('answered'));
        $this->assertSame(1, QuizAttempt::where('user_id', $buyer->id)->count());
    }

    public function test_an_answer_can_be_changed_before_finishing(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $url = route('learn.quizzes.answer', [$course, $quiz]);

        $this->actingAs($buyer)->post($url, ['position' => 1, 'answer' => 'a']);
        $this->actingAs($buyer)->post($url, ['position' => 1, 'answer' => 'd']);

        $this->assertSame(
            'd',
            QuizAttempt::where('user_id', $buyer->id)->firstOrFail()->answerFor(1)
        );
    }

    public function test_jumping_around_moves_the_cursor_without_recording_an_answer(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 6);

        $this->actingAs($buyer)
            ->post(route('learn.quizzes.jump', [$course, $quiz]), ['position' => 4])
            ->assertRedirect(route('learn.quizzes.play', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame(4, $attempt->current_position);
        $this->assertSame(0, $attempt->answeredCount());
    }

    public function test_an_answer_beyond_the_end_of_the_paper_is_rejected(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->actingAs($buyer)
            ->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => 99,
                'answer' => 'a',
            ])
            ->assertSessionHasErrors('position');
    }

    public function test_an_answer_that_is_not_a_b_c_d_is_rejected(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->actingAs($buyer)
            ->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => 1,
                'answer' => 'z',
            ])
            ->assertSessionHasErrors('answer');
    }

    /**
     * Answering the last question leaves the cursor pointing just past the end
     * (at "next"), which is the natural shape of a queue. A cursor that is
     * stale for any reason - a paper shortened between sittings, or a saved
     * queue that outran the paper - must come back to a real question when the
     * learner reopens the paper, never render a blank page.
     */
    public function test_a_cursor_past_the_end_of_the_paper_renders_the_last_question(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        // Saving the last answer points the queue at 4, past the end.
        $this->actingAs($buyer)
            ->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => 3,
                'answer' => 'a',
            ])
            ->assertRedirect(route('learn.quizzes.play', [$course, $quiz, 'position' => 4]));

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, $quiz]))
            ->assertOk()
            ->assertSee('Question 3 of 3');
    }

    /* -----------------------------------------------------------------
     | Marking
     | ----------------------------------------------------------------- */

    public function test_finishing_a_paper_scores_it_and_shows_every_answer(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);

        // Four questions, each with a different correct letter, so a wrong
        // answer cannot accidentally be right.
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        // Q1 right, Q2 wrong, Q3 wrong, Q4 left blank.
        foreach ([1, 2, 3] as $position) {
            $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => $position, 'answer' => 'a',
            ]);
        }

        $response = $this->actingAs($buyer)
            ->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $response->assertRedirect(route('learn.quizzes.result', [$course, $quiz, $attempt->id]));

        $this->assertSame(QuizAttempt::SUBMITTED, $attempt->status);
        $this->assertSame(1, $attempt->score);
        $this->assertSame(4, $attempt->total);
        $this->assertSame(25.0, (float) $attempt->percentage);
        $this->assertFalse($attempt->passed);
        $this->assertNotNull($attempt->submitted_at);

        // The result page shows each question, what was chosen and what was
        // right.
        $this->actingAs($buyer)
            ->get(route('learn.quizzes.result', [$course, $quiz, $attempt->id]))
            ->assertOk()
            ->assertSee('You scored')
            ->assertSee('<strong>25%</strong>', false)
            ->assertSee('Not passed yet')
            ->assertSee('2 more correct')
            ->assertSee('You left this blank')
            ->assertSee('You chose this');
    }

    public function test_the_time_taken_is_recorded(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        // Opening the paper is what starts the clock.
        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $this->travel(754)->seconds();

        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame(754, $attempt->time_taken_seconds);
    }

    public function test_a_clock_that_drifted_backwards_records_no_time_at_all(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $this->travel(-30)->seconds();

        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame(0, $attempt->time_taken_seconds);
    }

    public function test_submitting_twice_does_not_manufacture_a_second_score(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 1, 'answer' => 'a',
        ]);

        $url = route('learn.quizzes.submit', [$course, $quiz]);

        $this->actingAs($buyer)->post($url);
        $this->actingAs($buyer)->post($url);
        $this->actingAs($buyer)->post($url);

        $attempts = QuizAttempt::where('user_id', $buyer->id)->get();

        $this->assertCount(1, $attempts, 'A repeated submit must not open a second sitting.');
        $this->assertSame(1, $attempts->first()->score);
    }

    public function test_the_score_is_recomputed_from_the_content_not_from_the_browser(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 1, 'answer' => 'a',
        ]);

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        // Nothing but the answer was ever posted. A score cannot be supplied
        // by the client, and an answer for a question that is not in the paper
        // cannot be used to inflate the count.
        $this->actingAs($buyer)
            ->post(route('learn.quizzes.submit', [$course, $quiz]), ['score' => 100, 'total' => 1])
            ->assertRedirect();

        $attempt->refresh();

        $this->assertSame(1, $attempt->score);
        $this->assertSame(2, $attempt->total);
        $this->assertSame(50.0, (float) $attempt->percentage);
    }

    public function test_a_perfect_paper_passes(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4, 75);

        foreach (range(1, 4) as $position) {
            $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => $position,
                'answer' => ['a', 'b', 'c', 'd'][$position - 1],
            ]);
        }

        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->assertTrue($attempt->passed);
        $this->assertSame(4, $attempt->score);
        $this->assertSame(100.0, (float) $attempt->percentage);
    }

    public function test_the_pass_mark_is_75_percent(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);

        // 24 questions, pass at 75% = 18 correct.
        $paper = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 24, 75);

        $this->assertSame(18, $this->paper($course, $paper)->passMarkCount());

        $this->answerCorrectly($buyer, $course, $paper, 18);

        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $paper]));

        $this->assertTrue(QuizAttempt::where('user_id', $buyer->id)->firstOrFail()->passed);
    }

    public function test_one_answer_short_of_the_pass_mark_fails(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $paper = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 24, 75);

        $this->answerCorrectly($buyer, $course, $paper, 17);

        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $paper]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame(17, $attempt->score);
        $this->assertFalse($attempt->passed);
    }

    public function test_a_submitted_paper_cannot_be_answered_again(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 1, 'answer' => 'a',
        ]);
        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        // A second posting must not reopen the finished paper, and the
        // original score must stand.
        $this->actingAs($buyer)
            ->post(route('learn.quizzes.answer', [$course, $quiz]), [
                'position' => 2, 'answer' => 'b',
            ])
            ->assertStatus(422);

        $this->assertSame(QuizAttempt::SUBMITTED, $attempt->fresh()->status);
        $this->assertSame(1, $attempt->fresh()->score);
    }

    public function test_sitting_the_same_paper_again_makes_a_second_attempt(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 1, 'answer' => 'a',
        ]);
        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]))->assertOk();

        $this->assertSame(2, QuizAttempt::where('user_id', $buyer->id)->count());
    }

    public function test_one_learner_cannot_read_another_learners_result(): void
    {
        $course = $this->course();
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $owner = $this->buyer($course);
        $other = $this->buyer($course);

        $this->actingAs($owner)->post(route('learn.quizzes.answer', [$course, $quiz]), [
            'position' => 1, 'answer' => 'a',
        ]);
        $this->actingAs($owner)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)
            ->get(route('learn.quizzes.result', [$course, $quiz, $attempt->id]))
            ->assertNotFound();
    }

    public function test_a_result_from_a_different_paper_cannot_be_reached(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $one = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);
        $two = $this->makeQuiz($course, 'mock_test', 2, ['a', 'b', 'c', 'd'], 2);

        $this->actingAs($buyer)->post(route('learn.quizzes.answer', [$course, $one]), [
            'position' => 1, 'answer' => 'a',
        ]);
        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $one]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.result', [$course, $two, $attempt->id]))
            ->assertNotFound();
    }

    public function test_an_unsubmitted_attempt_has_no_result_page(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.result', [$course, $quiz, $attempt->id]))
            ->assertStatus(422);
    }

    public function test_a_paper_from_another_course_cannot_be_reached(): void
    {
        $course = $this->course();
        $other = $this->course('24-mock-tests');
        $buyer = $this->buyer($course);

        $foreign = $this->makeQuiz($other, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, $foreign]))
            ->assertNotFound();
    }

    public function test_the_best_score_is_shown_on_the_paper_list(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);
        $this->makeQuiz($course, 'classroom_mock', 1, ['a', 'b', 'c', 'd'], 2);

        $this->answerCorrectly($buyer, $course, $quiz, 2);
        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $this->actingAs($buyer)
            ->get(route('learn.index', $course))
            ->assertOk()
            ->assertSee('Best 100%')
            ->assertSee('Not sat');
    }

    /* -----------------------------------------------------------------
     | The real course the site serves
     |
     | Everything above uses a fixture. These use the two JSON files in
     | database/data/, which is what a buyer actually gets.
     | ----------------------------------------------------------------- */

    public function test_the_shipped_course_content_is_served(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);

        $this->actingAs($buyer)
            ->get(route('learn.index', $course))
            ->assertOk()
            ->assertSee('Lesson 1')
            ->assertSee('Knowledge Checks')
            ->assertSee('Classroom Mock Tests')
            ->assertSee('100 study cards');

        $lesson = $this->store()->lessons($course->slug)->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson->slug]))
            ->assertOk();

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, 'knowledge-check-1']))
            ->assertOk();
    }

    public function test_a_knowledge_check_result_links_back_to_its_lesson(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);

        $quiz = $this->makeQuiz($course, 'knowledge_check', 1, ['a', 'b', 'c', 'd'], 2);
        [$lesson] = $this->makeLessons($course, 1);

        $this->answerCorrectly($buyer, $course, $quiz, 2);
        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.result', [$course, $quiz, $attempt->id]))
            ->assertOk()
            ->assertSee(route('learn.lessons.show', [$course, $lesson]));
    }

    public function test_a_whole_course_paper_does_not_link_back_to_a_lesson(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);

        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);
        [$lesson] = $this->makeLessons($course, 1);

        $this->answerCorrectly($buyer, $course, $quiz, 2);
        $this->actingAs($buyer)->post(route('learn.quizzes.submit', [$course, $quiz]));

        $attempt = QuizAttempt::where('user_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.result', [$course, $quiz, $attempt->id]))
            ->assertOk()
            ->assertDontSee(route('learn.lessons.show', [$course, $lesson]));
    }

    /* -----------------------------------------------------------------
     | Reading the original .docx documents
     |
     | The JSON files cannot show whether a Word file is being read
     | correctly, so these read the actual source material.
     |
     | They skip when the .docx files are not on the machine. They were handed
     | over to a designer and are not part of the repository; what they
     | produced is the content in database/data/, which the tests above run
     | against instead.
     | ----------------------------------------------------------------- */

    public function test_the_lesson_document_holds_ten_lessons_of_study_cards(): void
    {
        $this->requireCourseDocuments();

        $parsed = (new CourseContentParser)->parse(base_path('course-files'));

        $this->assertSame([], $parsed['problems'], 'The course documents should parse without problems.');
        $this->assertCount(10, $parsed['lessons']);

        foreach ($parsed['lessons'] as $lesson) {
            $this->assertSame(100, $lesson['item_count'], "Lesson {$lesson['number']} should have 100 cards.");

            foreach ($lesson['items'] as $item) {
                $this->assertNotSame('', $item['question']);
                $this->assertNotSame('', $item['answer']);
            }
        }
    }

    public function test_every_paper_parses_with_a_complete_answer_key(): void
    {
        $this->requireCourseDocuments();

        $parsed = (new CourseContentParser)->parse(base_path('course-files'));

        $this->assertCount(10, $parsed['quizzes']['knowledge_check']);
        $this->assertCount(6, $parsed['quizzes']['classroom_mock']);
        $this->assertCount(24, $parsed['quizzes']['mock_test']);

        $expected = [
            'knowledge_check' => 10,
            'classroom_mock' => 24,
            'mock_test' => 24,
        ];

        foreach ($parsed['quizzes'] as $kind => $papers) {
            foreach ($papers as $number => $paper) {
                $this->assertCount(
                    $expected[$kind],
                    $paper['questions'],
                    "{$kind} {$number} should have {$expected[$kind]} questions."
                );

                foreach ($paper['questions'] as $question) {
                    $this->assertContains($question['correct_option'], ['a', 'b', 'c', 'd']);
                    $this->assertNotSame('', $question['explanation']);
                }
            }
        }
    }

    public function test_the_practice_pack_belongs_to_the_mock_course_and_nothing_else(): void
    {
        $this->requireCourseDocuments();

        $parsed = (new CourseContentParser)->parse(base_path('course-files'));

        $this->assertSame('life-in-the-uk-course', $parsed['quizzes']['knowledge_check']['1']['course']);
        $this->assertSame('life-in-the-uk-course', $parsed['quizzes']['classroom_mock']['1']['course']);
        $this->assertSame('24-mock-tests', $parsed['quizzes']['mock_test']['1']['course']);
    }

    public function test_the_extract_command_writes_files_that_read_back_the_same(): void
    {
        $this->requireCourseDocuments();

        $before = [
            config('course-content.lessons'),
            config('course-content.quizzes'),
        ];

        // Point the extractor at the real paths for the length of this test.
        config([
            'course-content.lessons' => $this->scratchPath('lesson-content.json'),
            'course-content.quizzes' => $this->scratchPath('quiz-content.json'),
        ]);

        try {
            $this->artisan('courses:extract')
                ->expectsOutputToContain('Lessons        10')
                ->expectsOutputToContain('Study cards    1000')
                ->expectsOutputToContain('Papers         40')
                ->expectsOutputToContain('Questions      820')
                ->expectsOutputToContain('Read back the same as written')
                ->assertSuccessful();

            $this->store()->flush();

            $summary = $this->store()->summary();

            $this->assertSame(10, $summary['lessons']);
            $this->assertSame(1000, $summary['cards']);
            $this->assertSame(40, $summary['quizzes']);
            $this->assertSame(820, $summary['questions']);
        } finally {
            config([
                'course-content.lessons' => $before[0],
                'course-content.quizzes' => $before[1],
            ]);

            $this->store()->flush();
        }
    }

    public function test_the_dry_run_reports_the_same_numbers_without_writing(): void
    {
        $this->requireCourseDocuments();

        $this->artisan('courses:extract --dry-run')
            ->expectsOutputToContain('10 (1000 study cards)')
            ->expectsOutputToContain('10 papers, 100 questions')
            ->expectsOutputToContain('6 papers, 144 questions')
            ->expectsOutputToContain('24 papers, 576 questions')
            ->expectsOutputToContain('No problems found')
            ->assertSuccessful();
    }

    public function test_the_extraction_refuses_to_write_when_a_document_is_broken(): void
    {
        $this->requireCourseDocuments();

        // The suite's earlier extraction test wrote real output to the same
        // scratch directory; a broken extraction must not inherit it.
        File::deleteDirectory($this->scratchPath(''));

        // A directory holding only part of the course material: the lesson
        // document is gone, so the parse is incomplete and nothing at all
        // should reach the files.
        $partial = $this->scratchPath('partial-source');
        @mkdir($partial, 0777, true);

        copy(
            base_path('course-files/Life in the UK Lesson 1-10 Final Knowledge Checks.docx'),
            $partial.'/Life in the UK Lesson 1-10 Final Knowledge Checks.docx'
        );

        $before = [
            config('course-content.lessons'),
            config('course-content.quizzes'),
        ];

        config([
            'course-content.lessons' => $this->scratchPath('lesson-content.json'),
            'course-content.quizzes' => $this->scratchPath('quiz-content.json'),
        ]);

        try {
            $failed = null;

            try {
                app(CourseContentExtractor::class)->extract($partial);
            } catch (CourseContentExtractionFailedException $e) {
                $failed = $e;
            }

            $this->assertNotNull($failed, 'A partial extraction should have been refused.');
            $this->assertNotEmpty($failed->problems());
            $this->assertStringContainsString('Nothing was written', $failed->getMessage());

            $this->assertFileDoesNotExist($this->scratchPath('lesson-content.json'));
            $this->assertFileDoesNotExist($this->scratchPath('quiz-content.json'));
        } finally {
            config([
                'course-content.lessons' => $before[0],
                'course-content.quizzes' => $before[1],
            ]);
        }
    }

    public function test_the_docx_reader_matches_a_plain_zip_read(): void
    {
        $this->requireCourseDocuments();

        // Guards the hand-rolled reader: every entry it extracts should be
        // byte-identical to what a real unzip gives.
        $path = base_path('course-files/Life in the UK Lesson 1-10.docx');

        $expected = shell_exec('unzip -p '.escapeshellarg($path).' word/document.xml 2>/dev/null');

        $this->assertIsString($expected);
        $this->assertNotSame('', $expected);

        $text = html_entity_decode(strip_tags(str_replace(
            ['</w:p>', '</w:tc>', '</w:tr>'],
            "\x02",
            preg_replace(['#<w:tab\s*/?>#', '#<w:(br|cr)\b[^>]*/?>#'], ["\t", "\n"], $expected)
        )), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $paragraphs = [];

        foreach (explode("\x02", $text) as $chunk) {
            foreach (preg_split('/\r\n|\r|\n/', $chunk) as $line) {
                if (trim($line) !== '') {
                    $paragraphs[] = trim($line);
                }
            }
        }

        $this->assertSame($paragraphs, DocxReader::paragraphs($path));
    }

    public function test_the_reader_refuses_a_file_that_is_not_a_zip(): void
    {
        $this->expectException(\RuntimeException::class);

        DocxReader::paragraphs(base_path('composer.json'));
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    /**
     * A path inside this test's own scratch directory.
     *
     * Nothing here is the real course: the extraction tests write to scratch
     * and the real files in database/data/ are only ever read.
     */
    protected function scratchPath(string $path): string
    {
        return storage_path('framework/testing/course-scratch/'.$path);
    }

    /**
     * Are the original .docx source documents still on this machine?
     *
     * They were handed over to a designer and are not expected to be part of
     * the repository. Tests that read them directly skip when they are absent,
     * rather than failing and hiding real problems.
     */
    protected static function courseDocumentsArePresent(): bool
    {
        return is_dir(base_path('course-files'))
            && is_file(base_path('course-files/Life in the UK Lesson 1-10.docx'));
    }

    /**
     * Skip the calling test when the .docx source documents are not present.
     */
    protected function requireCourseDocuments(): void
    {
        if (! self::courseDocumentsArePresent()) {
            $this->markTestSkipped(
                'The .docx source documents are not present; they were temporary. '
                .'What they produced is the content in database/data/, which is covered separately.'
            );
        }
    }

    protected function course(string $slug = 'life-in-the-uk-course'): Course
    {
        return Course::where('slug', $slug)->firstOrFail();
    }

    protected function buyer(Course $course): User
    {
        $user = User::factory()->create();

        Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $user->fresh();
    }
}
