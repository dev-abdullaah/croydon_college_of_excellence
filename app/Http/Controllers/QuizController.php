<?php

namespace App\Http\Controllers;

use App\Content\CourseContent;
use App\Content\Question;
use App\Content\Quiz;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Services\QuizAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sitting a paper and reading the result.
 *
 * The learner works through the paper in their browser, which holds their
 * choices as they go, so moving between questions costs no request. The whole
 * answer map is posted once, when they finish, and they are then shown every
 * question with what they chose, what the right answer was, and the score.
 *
 * The correct answer is never sent to the browser while a paper is in
 * progress. It is only read when the attempt is marked, on the server.
 */
class QuizController extends Controller
{
    public function __construct(
        private readonly CourseContent $content,
        private readonly QuizAttemptService $attempts,
    ) {}

    /**
     * Start a paper, or pick the learner back up where they left off.
     *
     * Every question is rendered up front and answered in the browser, so
     * moving through a paper costs no request. That means the whole paper -
     * prompts and options, never the answers - is on the page at once.
     */
    public function play(Request $request, Course $course, Quiz $quiz): View
    {
        $attempt = $this->attempts->resumeOrStart($request->user(), $quiz);

        $total = $quiz->questionCount();

        // A saved cursor can point past the end of a paper that has since been
        // shortened, so it is clamped rather than trusted.
        $position = min(max(1, (int) $attempt->current_position), $total);

        return view('website.pages.learn.play', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'total' => $total,
            'position' => $position,
            // `correct` and `explanation` are left out on purpose. They reveal
            // the answer, and this page is built by the browser as well as
            // read by it.
            'questions' => $quiz->questions->map(fn (Question $question) => [
                'position' => $question->position,
                'prompt' => $question->prompt,
                'options' => $question->options,
            ]),
            'history' => $this->attempts->history($request->user(), $quiz),
        ]);
    }

    /**
     * Finish the paper and mark it.
     *
     * The one request that carries the learner's work: the whole answer map
     * arrives at once. Submitting twice is harmless: the second one lands on
     * the result that already exists rather than marking a second, empty
     * sitting.
     */
    public function submit(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            // Not `present`: a learner who finishes having answered nothing
            // sends no `answers` field at all, and that is a blank paper, not a
            // bad request.
            'answers' => ['sometimes', 'array'],
            'answers.*' => ['nullable', 'string', 'in:a,b,c,d,A,B,C,D'],
        ]);

        $attempt = $this->attempts->submitWithAnswers(
            $request->user(),
            $quiz,
            $data['answers'] ?? []
        );

        return redirect()->route('learn.quizzes.result', [$course, $quiz->slug, $attempt->id]);
    }

    /**
     * The result of one finished sitting.
     *
     * The attempt is looked up constrained to this learner *and* this paper,
     * so one learner's id cannot be used to read another's answers.
     */
    public function result(Request $request, Course $course, Quiz $quiz, string $attempt): View
    {
        $found = QuizAttempt::query()
            ->forPaper($request->user(), $course->slug, $quiz->slug)
            ->find($attempt);

        abort_if($found === null, 404);

        abort_unless($found->isSubmitted(), 422, 'That attempt has not been submitted yet.');

        // Per question: what was chosen, what was right, and why. Built here
        // rather than in the view so the view stays declarative.
        $marked = $quiz->questions->map(function (Question $question) use ($found) {
            $given = $found->answerFor($question->position);

            return [
                'question' => $question,
                'given' => $given,
                'given_text' => $question->optionText($given),
                'correct' => $question->correct,
                'correct_text' => $question->optionText($question->correct),
                'explanation' => $question->explanation,
                'is_correct' => $question->isCorrect($given),
                'is_blank' => $given === null,
            ];
        });

        return view('website.pages.learn.result', [
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $found,
            'marked' => $marked,
            'blank' => $marked->where('is_blank', true)->count(),
            'history' => $this->attempts->history($request->user(), $quiz),
            // A knowledge check's number matches the lesson it revises, so the
            // result page can send the learner back to the reading that led to
            // it. Whole-course papers have no lesson behind them.
            'lesson' => $quiz->isKnowledgeCheck()
                ? $this->content->lessonByNumber($course->slug, $quiz->number)
                : null,
        ]);
    }
}
