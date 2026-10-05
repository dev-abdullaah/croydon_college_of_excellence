<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\QuizAttempt;
use App\Models\Student;
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
     |
     | A completed Stripe payment is the only thing that opens the material
     | and there is no switch that relaxes it, so these tests describe the
     | site as it actually behaves.
     | ----------------------------------------------------------------- */

    public function test_a_guest_is_sent_to_login(): void
    {
        $course = $this->course();

        $this->get(route('learn.index', $course))->assertRedirect(route('login'));
    }

    public function test_a_visitor_without_the_purchase_gets_a_403(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student)
            ->get(route('learn.index', $this->course()))
            ->assertForbidden();
    }

    public function test_an_unpaid_purchase_does_not_unlock_the_learning_area(): void
    {
        $student = Student::factory()->create();
        $course = $this->course();

        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PENDING,
        ]);

        $this->actingAs($student)
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
            'student_id' => $buyer->id,
            'course_slug' => $course->slug,
            'lesson_slug' => $lesson,
        ]);
    }

    public function test_a_lesson_can_be_marked_as_unread(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);

        $this->actingAs($buyer)->post(route('learn.lessons.complete', [$course, $lesson]));

        $this->assertDatabaseHas('lesson_progress', [
            'student_id' => $buyer->id,
            'course_slug' => $course->slug,
            'lesson_slug' => $lesson,
        ]);

        $this->actingAs($buyer)
            ->from(route('learn.lessons.show', [$course, $lesson]))
            ->post(route('learn.lessons.unread', [$course, $lesson]))
            ->assertRedirect(route('learn.lessons.show', [$course, $lesson]));

        $this->assertDatabaseMissing('lesson_progress', [
            'student_id' => $buyer->id,
            'course_slug' => $course->slug,
            'lesson_slug' => $lesson,
        ]);
    }

    public function test_marking_a_lesson_unread_means_it_counts_as_unread_again(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);

        $this->actingAs($buyer)->post(route('learn.lessons.complete', [$course, $lesson]));
        $this->actingAs($buyer)->post(route('learn.lessons.unread', [$course, $lesson]));

        // The course progress list is drawn from the same table, so a row that
        // survives the delete would leave the lesson marked read there while
        // the lesson page claimed otherwise.
        $this->assertSame([], LessonProgress::readSlugsFor($buyer, $course->slug)->all());

        // And the reader offers "Mark as read" rather than "Mark as unread".
        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee('Mark as read')
            ->assertDontSee('Mark as unread');
    }

    public function test_a_lesson_offers_mark_as_unread_only_once_it_is_read(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);

        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee('Mark as read')
            ->assertDontSee('Mark as unread');

        $this->actingAs($buyer)->post(route('learn.lessons.complete', [$course, $lesson]));

        $this->actingAs($buyer)
            ->get(route('learn.lessons.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee('Mark as unread')
            ->assertDontSee('Mark as read');
    }

    public function test_marking_an_already_unread_lesson_as_unread_does_nothing(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$lesson] = $this->makeLessons($course, 1);

        $url = route('learn.lessons.unread', [$course, $lesson]);

        // Never read in the first place, and then un-read twice. Neither is an
        // error: the button can be double-clicked and a request can be replayed,
        // which is the same reasoning as the unique index keeping a double-clicked
        // "mark as read" to one row.
        $this->actingAs($buyer)->post($url)->assertRedirect();
        $this->actingAs($buyer)->post($url)->assertRedirect();
        $this->actingAs($buyer)->post($url)->assertRedirect();

        $this->assertSame(0, LessonProgress::where('student_id', $buyer->id)->count());
    }

    public function test_marking_a_lesson_unread_leaves_the_other_lessons_alone(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        [$first, $second] = $this->makeLessons($course, 2);

        $this->actingAs($buyer)->post(route('learn.lessons.complete', [$course, $first]));
        $this->actingAs($buyer)->post(route('learn.lessons.complete', [$course, $second]));

        $this->actingAs($buyer)->post(route('learn.lessons.unread', [$course, $first]));

        $this->assertSame(1, LessonProgress::where('student_id', $buyer->id)->count());
        $this->assertDatabaseHas('lesson_progress', [
            'student_id' => $buyer->id,
            'course_slug' => $course->slug,
            'lesson_slug' => $second,
        ]);
    }

    public function test_marking_a_lesson_unread_leaves_another_learner_alone(): void
    {
        $course = $this->course();
        [$lesson] = $this->makeLessons($course, 1);

        $one = $this->buyer($course);
        $two = $this->buyer($course);

        // Both have read it. One takes it back; the other's progress must survive,
        // because the delete is scoped to the signed-in learner and not just to
        // the course and lesson the URL names.
        $this->actingAs($one)->post(route('learn.lessons.complete', [$course, $lesson]));
        $this->actingAs($two)->post(route('learn.lessons.complete', [$course, $lesson]));

        $this->actingAs($one)->post(route('learn.lessons.unread', [$course, $lesson]));

        $this->assertSame(0, LessonProgress::where('student_id', $one->id)->count());
        $this->assertSame(1, LessonProgress::where('student_id', $two->id)->count());
    }

    public function test_marking_a_lesson_unread_in_a_course_you_do_not_own_is_refused(): void
    {
        $course = $this->course();
        [$lesson] = $this->makeLessons($course, 1);

        $owner = $this->buyer($course);
        $stranger = $this->buyer($this->course('24-mock-tests'));

        $this->actingAs($owner)->post(route('learn.lessons.complete', [$course, $lesson]));

        $this->actingAs($stranger)
            ->post(route('learn.lessons.unread', [$course, $lesson]))
            ->assertForbidden();

        // Refused, and no collateral damage to the actual owner.
        $this->assertSame(1, LessonProgress::where('student_id', $owner->id)->count());
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

        $this->assertSame(1, LessonProgress::where('student_id', $buyer->id)->count());
    }

    public function test_lesson_progress_is_per_student(): void
    {
        $course = $this->course();
        [$lesson] = $this->makeLessons($course, 1);

        $one = $this->buyer($course);
        $two = $this->buyer($course);

        $this->actingAs($one)->post(route('learn.lessons.complete', [$course, $lesson]));

        $this->assertSame(1, LessonProgress::where('student_id', $one->id)->count());
        $this->assertSame(0, LessonProgress::where('student_id', $two->id)->count());
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

    public function test_opening_a_paper_starts_an_attempt_but_writes_no_answers(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $response = $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('position'));

        $this->assertDatabaseHas('quiz_attempts', [
            'student_id' => $buyer->id,
            'course_slug' => $course->slug,
            'quiz_slug' => $quiz,
            'status' => QuizAttempt::IN_PROGRESS,
        ]);

        // The whole paper is answered in the browser, so opening it must not
        // cost a write. This is the guarantee that makes a question free.
        $this->assertSame(
            0,
            QuizAttempt::where('student_id', $buyer->id)->firstOrFail()->answeredCount()
        );
    }

    /**
     * The whole paper is on the page at once. That is what lets the browser
     * move between questions without asking the server for the next one, so it
     * is the thing that makes "no request per question" possible at all.
     */
    public function test_the_whole_paper_is_on_the_page_at_once(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $html = $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, $quiz]))
            ->assertOk()
            ->getContent();

        foreach (range(1, 4) as $position) {
            $this->assertStringContainsString(
                "Question {$position}?",
                $html,
                "Question {$position} has to be on the page for the browser to move between them."
            );
        }

        // One form, so the browser posts every answer in a single go.
        $this->assertSame(1, substr_count($html, 'id="paper-form"'));

        // The theme's main.js cancels the submit of any form called
        // `quiz-form`, which it uses for a demo quiz elsewhere on the site.
        // Sharing that id would stop the paper ever being marked, and nothing
        // would say why: the page looks right and the learner just lands back
        // where they started.
        $this->assertStringNotContainsString('id="quiz-form"', $html);
    }

    public function test_the_correct_answer_is_never_sent_to_the_browser_while_a_paper_is_in_progress(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['b', 'c', 'd', 'a'], 3);

        $paper = $this->paper($course, $quiz);

        $html = $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, $quiz]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(
            $paper->questionAt(1)->explanation,
            $html,
            'The explanation reveals the right answer and must not appear on the play screen.'
        );

        // The page is built by the browser as well as read by it, so a correct
        // letter must not be sitting there already chosen either. A pre-checked
        // radio would hand the answers over with the markup.
        foreach ($paper->questions as $question) {
            $this->assertStringNotContainsString(
                "value=\"{$question->correct}\" checked",
                $html,
                "Question {$question->position} gives its answer away in the markup."
            );
        }
    }

    public function test_finishing_a_paper_posts_every_answer_at_once_and_marks_it(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        // The one request that carries the sitting: right, wrong, wrong, blank.
        $this->submitPaper($buyer, $course, $quiz, [1 => 'a', 2 => 'a', 3 => 'a'])
            ->assertRedirect();

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(QuizAttempt::SUBMITTED, $attempt->status);
        $this->assertSame(['1' => 'a', '2' => 'a', '3' => 'a'], $attempt->answers);
        $this->assertSame(1, $attempt->score);
        $this->assertSame(4, $attempt->total);
    }

    public function test_a_question_the_learner_never_answered_is_stored_as_blank(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->submitPaper($buyer, $course, $quiz, [1 => 'a']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertNull($attempt->answerFor(2));
        $this->assertNull($attempt->answerFor(3));
        $this->assertSame(1, $attempt->score, 'The two blanks count as wrong, as the paper says they do.');
    }

    /**
     * The map that arrives is the whole sitting, so a question missing from it
     * is one the learner has nothing chosen for. That is what makes a cleared
     * answer clear: there is no earlier pick left behind on the server to keep
     * a mark it had before.
     */
    public function test_a_question_missing_from_the_map_counts_as_blank(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->submitPaper($buyer, $course, $quiz, [2 => 'b']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertNull($attempt->answerFor(1));
        $this->assertSame('b', $attempt->answerFor(2));
        $this->assertNull($attempt->answerFor(3));
        $this->assertSame(1, $attempt->score);
    }

    /**
     * A crafted map cannot invent a question to be credited with. Anything
     * keyed at a position the paper does not have is dropped, so the total is
     * still the length of the paper.
     */
    public function test_an_answer_for_a_question_that_is_not_on_the_paper_is_ignored(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->submitPaper($buyer, $course, $quiz, [1 => 'a', 2 => 'b', 99 => 'c']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(3, $attempt->total);
        $this->assertSame(2, $attempt->score);
        $this->assertSame(['1' => 'a', '2' => 'b'], $attempt->answers);
    }

    public function test_a_letter_that_is_not_a_b_c_d_is_rejected(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->submitPaper($buyer, $course, $quiz, [1 => 'z'])
            ->assertSessionHasErrors('answers.1');

        // The paper is left open rather than marked, so a refused submit does
        // not cost the learner their sitting.
        $this->assertSame(
            QuizAttempt::IN_PROGRESS,
            QuizAttempt::where('student_id', $buyer->id)->firstOrFail()->status
        );
    }

    public function test_a_paper_with_no_answers_at_all_still_marks_as_all_wrong(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->submitPaper($buyer, $course, $quiz);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(0, $attempt->score);
        $this->assertSame(3, $attempt->total);
        $this->assertNull($attempt->answers);
    }

    /**
     * A form that has had nothing ticked in it posts no `answers` field at all,
     * rather than an empty one. That has to be read as a paper of blanks, not
     * turned away as a malformed request, or a learner who finishes having
     * chosen nothing loses their sitting to a 422.
     */
    public function test_finishing_without_any_answers_posts_nothing_and_still_marks(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $this->actingAs($buyer)
            ->post(route('learn.quizzes.submit', [$course, $quiz]))
            ->assertRedirect();

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(QuizAttempt::SUBMITTED, $attempt->status);
        $this->assertSame(0, $attempt->score);
        $this->assertSame(3, $attempt->total);
    }

    /**
     * The cursor is only a starting hint now - the browser remembers where the
     * learner really is - but a stale value for any reason, such as a paper
     * shortened between sittings, must still land on a real question rather
     * than render nothing.
     */
    public function test_a_cursor_past_the_end_of_the_paper_renders_the_last_question(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        QuizAttempt::where('student_id', $buyer->id)->firstOrFail()
            ->forceFill(['current_position' => 99])
            ->save();

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

        // Q1 right, Q2 wrong, Q3 wrong, Q4 left blank. All of it in the one
        // request that finishes the paper.
        $response = $this->submitPaper($buyer, $course, $quiz, [1 => 'a', 2 => 'a', 3 => 'a']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

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

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(754, $attempt->time_taken_seconds);
    }

    public function test_a_clock_that_drifted_backwards_records_no_time_at_all(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $this->travel(-30)->seconds();

        $this->submitPaper($buyer, $course, $quiz);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(0, $attempt->time_taken_seconds);
    }

    public function test_submitting_twice_does_not_manufacture_a_second_score(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 4);

        $answers = [1 => 'a'];

        $this->submitPaper($buyer, $course, $quiz, $answers);
        $this->submitPaper($buyer, $course, $quiz, $answers);
        $this->submitPaper($buyer, $course, $quiz, $answers);

        $attempts = QuizAttempt::where('student_id', $buyer->id)->get();

        $this->assertCount(1, $attempts, 'A repeated submit must not open a second sitting.');
        $this->assertSame(1, $attempts->first()->score);
    }

    public function test_the_score_is_recomputed_from_the_content_not_from_the_browser(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]));

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        // Nothing but answers is ever read. A score cannot be supplied by the
        // client, and an answer for a question that is not in the paper cannot
        // be used to inflate the count.
        $this->actingAs($buyer)
            ->post(route('learn.quizzes.submit', [$course, $quiz]), [
                'answers' => [1 => 'a', 99 => 'b'],
                'score' => 100,
                'total' => 1,
                'percentage' => 100,
                'passed' => true,
            ])
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

        $this->submitPaper($buyer, $course, $quiz, [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

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

        $this->submitPaper($buyer, $course, $paper, $this->correctAnswers($course, $paper, 18));

        $this->assertTrue(QuizAttempt::where('student_id', $buyer->id)->firstOrFail()->passed);
    }

    public function test_one_answer_short_of_the_pass_mark_fails(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $paper = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 24, 75);

        $this->submitPaper($buyer, $course, $paper, $this->correctAnswers($course, $paper, 17));

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(17, $attempt->score);
        $this->assertFalse($attempt->passed);
    }

    /**
     * Answers live in the browser now, so it is easy to have the same paper
     * open twice. A tab that submits after the paper is already marked must not
     * reopen it or quietly replace the score the learner has already been
     * shown. To sit a paper again they open it, which is an explicit act.
     */
    public function test_a_stale_tab_cannot_overwrite_a_finished_result(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 3);

        $this->submitPaper($buyer, $course, $quiz, [1 => 'a', 2 => 'b', 3 => 'c']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->assertSame(3, $attempt->score);

        // A second tab, holding a different set of answers, submits late.
        $this->submitPaper($buyer, $course, $quiz, [1 => 'd', 2 => 'd', 3 => 'd'])
            ->assertRedirect();

        $this->assertSame(1, QuizAttempt::where('student_id', $buyer->id)->count());
        $this->assertSame(QuizAttempt::SUBMITTED, $attempt->fresh()->status);
        $this->assertSame(3, $attempt->fresh()->score);
        $this->assertSame(['1' => 'a', '2' => 'b', '3' => 'c'], $attempt->fresh()->answers);
    }

    public function test_sitting_the_same_paper_again_makes_a_second_attempt(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $this->submitPaper($buyer, $course, $quiz, [1 => 'a', 2 => 'b']);

        $this->actingAs($buyer)->get(route('learn.quizzes.play', [$course, $quiz]))->assertOk();

        $this->assertSame(2, QuizAttempt::where('student_id', $buyer->id)->count());
    }

    public function test_one_learner_cannot_read_another_learners_result(): void
    {
        $course = $this->course();
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);

        $owner = $this->buyer($course);
        $other = $this->buyer($course);

        $this->submitPaper($owner, $course, $quiz, [1 => 'a', 2 => 'b']);

        $attempt = QuizAttempt::where('student_id', $owner->id)->firstOrFail();

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

        $this->submitPaper($buyer, $course, $one, [1 => 'a', 2 => 'b']);

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

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

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

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

        $this->submitPaper($buyer, $course, $quiz, $this->correctAnswers($course, $quiz, 2));

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

        $this->submitPaper($buyer, $course, $quiz, $this->correctAnswers($course, $quiz, 2));

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

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

        $this->submitPaper($buyer, $course, $quiz, $this->correctAnswers($course, $quiz, 2));

        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        $this->actingAs($buyer)
            ->get(route('learn.quizzes.result', [$course, $quiz, $attempt->id]))
            ->assertOk()
            ->assertDontSee(route('learn.lessons.show', [$course, $lesson]));
    }

    /* -----------------------------------------------------------------
     | Which buttons are which

     | Colour here is not decoration. These pages are used one-handed on a
     | phone, and the colour is the fastest way to say what a button does:
     | blue for the thing you came here to do, green for the thing that
     | finishes something, grey for the way back.
     |
     | They also all have to be the same size. They were a mix - some at 45px,
     | some at 31px - which is what made the sign-out button on the account
     | page look like a stray label.
     * ----------------------------------------------------------------- */

    public function test_the_learning_buttons_are_large_and_coloured_by_what_they_do(): void
    {
        $course = $this->course();
        $buyer = $this->buyer($course);

        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 2);
        [$lesson] = $this->makeLessons($course, 1);

        $this->submitPaper($buyer, $course, $quiz, $this->correctAnswers($course, $quiz, 2));
        $attempt = QuizAttempt::where('student_id', $buyer->id)->firstOrFail();

        // Blue: the action the page exists for. Green: finishing something.
        // Outlined grey: getting somewhere else.
        $expected = [
            route('learn.index', $course) => ['btn-outline-secondary', 'btn-primary'],
            route('learn.lessons.show', [$course, $lesson]) => ['btn-outline-secondary', 'btn-primary', 'btn-success'],
            route('learn.quizzes.play', [$course, $quiz]) => ['btn-outline-secondary', 'btn-primary', 'btn-success'],
            route('learn.quizzes.result', [$course, $quiz, $attempt->id]) => ['btn-outline-secondary', 'btn-primary'],
        ];

        foreach ($expected as $url => $colours) {
            $html = $this->actingAs($buyer)->get($url)->assertOk()->getContent();

            preg_match_all('/class="(btn btn-[^"]*)"/', $html, $matches);

            $found = $matches[1];

            $this->assertNotEmpty($found, "no bootstrap buttons on {$url}");

            foreach ($found as $class) {
                $this->assertStringContainsString(
                    'btn-lg',
                    $class,
                    "a small button on {$url}: {$class}",
                );

                $this->assertNotEmpty(
                    array_filter($colours, fn ($c) => str_contains($class, $c)),
                    "unexpected colour on {$url}: {$class}",
                );
            }
        }
    }

    public function test_no_learning_button_is_left_on_the_old_theme_button(): void
    {
        // .rbt-btn and .btn-lg mean two different things in this stylesheet -
        // the theme's own button, which is 45px, and Bootstrap's large one.
        // Mixing them on the same page is how the sizes ended up different.
        //
        // Read from the views rather than from a rendered page, because the
        // shared header and footer are full of .rbt-btn and are not what is
        // under test; the learning pages' own buttons are.
        $views = [
            'index', 'lesson', 'play', 'result',
        ];

        foreach ($views as $view) {
            $source = (string) file_get_contents(
                resource_path("views/website/pages/learn/{$view}.blade.php")
            );

            $this->assertStringNotContainsString(
                'rbt-btn',
                $source,
                "the {$view} page still has a theme button on it",
            );
        }
    }

    public function test_the_question_jump_buttons_are_big_enough_to_hit(): void
    {
        // These are reached for one-handed, mid-paper, without necessarily
        // looking straight at them. They were 23px squares with 8px type -
        // smaller than the buttons on the same page.
        //
        // Read from the stylesheet, because a rendered button carries no size
        // of its own; there is nothing in the markup to assert on.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        preg_match('/\.lz-jump button\s*\{([^}]*)\}/', $css, $block);
        $rules = $block[1] ?? '';

        $this->assertNotSame('', $rules, 'no rule found for the jump buttons');

        // `html { font-size: 10px }` in this theme, so 1rem is 10px.
        preg_match('/width:\s*([\d.]+)rem/', $rules, $width);
        preg_match('/height:\s*([\d.]+)rem/', $rules, $height);
        preg_match('/font-size:\s*([\d.]+)rem/', $rules, $font);

        $this->assertGreaterThanOrEqual(40, (float) ($width[1] ?? 0) * 10, 'jump button too narrow');
        $this->assertGreaterThanOrEqual(40, (float) ($height[1] ?? 0) * 10, 'jump button too short');

        // A two digit question number has to be legible in it, not a smudge.
        $this->assertGreaterThanOrEqual(12, (float) ($font[1] ?? 0) * 10, 'jump number too small');
    }

    public function test_the_navigator_keeps_its_flex_layout_while_a_paper_is_playing(): void
    {
        // `gap` does nothing at all to a block box.
        //
        // The navigator was switched to `display: block` by the very rule that
        // reveals it - three classes there against one class on `.lz-jump` - so
        // the gap was silently inert and the buttons fell back to wrapping as
        // text, one row butting up against the next. Nothing in the markup was
        // wrong; only the cascade was.
        //
        // So the rule is that nothing may put the navigator on anything but
        // `flex`, or on `none` while it is meant to be hidden.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // Comments first. This stylesheet explains itself at length, and the
        // explanation of this very bug contains the words "display: block" and
        // ".lz-jump" - which a naive scan happily reports as a rule.
        $css = (string) preg_replace('!/\*.*?\*/!s', '', $css);

        preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $displays = [];
        $base = null;

        foreach ($rules as [, $selector, $body]) {
            foreach (explode(',', $selector) as $one) {
                $one = trim($one);

                if (! str_contains($one, 'lz-jump')) {
                    continue;
                }

                // The bare `.lz-jump` block is the one that carries the layout.
                // Found by exact selector, not by a regex, because `.lz-jump {`
                // also matches the tail of `.lz-aside .lz-jump {` and that rule
                // is the hide, not the layout.
                if ($one === '.lz-jump') {
                    $base = $body;
                }

                if (preg_match('/display:\s*(\w+)/', $body, $found)) {
                    $displays[] = $one.' => '.$found[1];
                }
            }
        }

        $this->assertNotEmpty($displays, 'no display rules found for the navigator');

        foreach ($displays as $rule) {
            $this->assertMatchesRegularExpression(
                '/=> (flex|none)$/',
                $rule,
                "the navigator must not be display:block, which would switch its gap off: {$rule}",
            );
        }

        // And the gap has to actually be a gap, on the rule that also sets flex.
        $this->assertNotNull($base, 'no bare .lz-jump rule found');

        $this->assertMatchesRegularExpression(
            '/display:\s*flex/',
            $base,
            'the base .lz-jump rule is not flex',
        );

        $this->assertMatchesRegularExpression(
            '/gap:\s*(?!0\b)\S/',
            $base,
            'the navigator has no gap, so its buttons will touch',
        );
    }

    public function test_the_answer_letter_is_centred_against_its_text_and_big_enough_to_read(): void
    {
        // Two separate faults in the option row, both invisible to the markup:
        // the letter was set at .8rem (8px) inside an 18px chip, and the row was
        // `align-items: flex-start`, which put the letter and the answer both on
        // the top edge. That reads as "roughly centred" on a one-line option and
        // as badly wrong on the two-line ones - measured at 14px of drift.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // Comments first, for the same reason as the navigator test above.
        $css = (string) preg_replace('!/\*.*?\*/!s', '', $css);

        preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $row = null;
        $key = null;

        foreach ($rules as [, $selector, $body]) {
            foreach (explode(',', $selector) as $one) {
                $one = trim($one);

                // Exact selectors, because `.lz-key {` would otherwise match the
                // tail of `.lz-opt .lz-key {`, which only recolours it.
                if ($one === '.lz-opt') {
                    $row = $body;
                }

                if ($one === '.lz-key') {
                    $key = $body;
                }
            }
        }

        $this->assertNotNull($row, 'no .lz-opt rule found');
        $this->assertNotNull($key, 'no .lz-key rule found');

        $this->assertMatchesRegularExpression(
            '/align-items:\s*center/',
            $row,
            'the option row is not centred, so the letter floats above its answer',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/align-items:\s*flex-start/',
            $row,
            'the option row is flex-start aligned again, which puts the letter on the top edge',
        );

        // The radio carried a margin-top nudge that only existed to counteract
        // the flex-start above. With the row centred it would shove the radio
        // below centre instead, so it has to be gone.
        $radio = null;

        foreach ($rules as [, $selector, $body]) {
            foreach (explode(',', $selector) as $one) {
                if (trim($one) === '.lz-opt input') {
                    $radio = $body;
                }
            }
        }

        $this->assertNotNull($radio, 'no .lz-opt input rule found');
        $this->assertDoesNotMatchRegularExpression(
            '/margin-top:/',
            $radio,
            'the radio still carries a top nudge that fights the centred row',
        );

        // Now the type. Body text is 1.8rem (18px) in this theme; the letter was
        // less than half that, which is why it read as too small.
        preg_match('/font-size:\s*([\d.]+)rem/', $key, $size);

        $this->assertNotEmpty($size, '.lz-key sets no rem font size');
        $this->assertGreaterThanOrEqual(
            1.1,
            (float) $size[1],
            'the answer letter is set below 11px, which is too small to read',
        );

        // And the chip has to be able to hold it. A letter wider than the box it
        // sits in is the other way this goes wrong.
        preg_match('/flex:\s*0\s+0\s+([\d.]+)rem/', $key, $box);

        $this->assertNotEmpty($box, '.lz-key sets no width in rem');
        $this->assertGreaterThan(
            (float) $size[1],
            (float) $box[1],
            'the answer letter would overflow its own chip',
        );
    }

    public function test_there_is_a_jump_button_for_every_question(): void
    {
        // 24 questions means 24 targets, and a missing one is a question the
        // learner cannot navigate back to.
        $course = $this->course();
        $buyer = $this->buyer($course);

        $quiz = $this->makeQuiz($course, 'mock_test', 4, ['a', 'b', 'c', 'd'], 4);

        $html = $this->actingAs($buyer)
            ->get(route('learn.quizzes.play', [$course, $quiz]))
            ->assertOk()
            ->getContent();

        preg_match_all('/data-goto="(\d+)"/', $html, $matches);

        $this->assertSame(
            range(1, 4),
            array_map('intval', $matches[1]),
            'the navigator does not list every question, in order',
        );
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

    protected function buyer(Course $course): Student
    {
        $student = Student::factory()->create();

        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $student->fresh();
    }
}
