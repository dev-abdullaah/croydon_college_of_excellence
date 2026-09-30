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
 * One question at a time, like the real test: the learner saves an answer and
 * moves on, so a refresh or a dropped connection never loses their work. When
 * they finish they are shown every question with what they chose, what the
 * right answer was, and the score.
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
            'question' => $quiz->questionAt($position) ?? $quiz->questionAt(1),
            'questions' => $quiz->questions,
            'total' => $total,
            'position' => $position,
            'answered' => $attempt->answeredCount(),
            'history' => $this->attempts->history($request->user(), $quiz),
        ]);
    }

    /**
     * Save an answer and move to the next question.
     */
    public function answer(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:1', 'max:'.$quiz->questionCount()],
            'answer' => ['nullable', 'string', 'in:a,b,c,d,A,B,C,D'],
        ]);

        $attempt = $this->attempts->openForAnswering($request->user(), $quiz);

        $question = $this->questionAt($quiz, (int) $data['position']);

        $this->attempts->recordAnswer($attempt, $question, $data['answer'] ?? null);

        return redirect()->route('learn.quizzes.play', [$course, $quiz->slug, 'position' => $question->position + 1]);
    }

    /**
     * Move the cursor without recording an answer, for the back button and
     * the jump-to-question nav.
     */
    public function jump(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $data = $request->validate([
            'position' => ['required', 'integer', 'min:1', 'max:'.$quiz->questionCount()],
        ]);

        $attempt = $this->attempts->openForAnswering($request->user(), $quiz);

        $this->attempts->moveTo($attempt, (int) $data['position']);

        return redirect()->route('learn.quizzes.play', [$course, $quiz->slug]);
    }

    /**
     * Finish the paper and mark it.
     *
     * Submitting twice is harmless: the second one lands on the result that
     * already exists rather than marking a second, empty sitting.
     */
    public function submit(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $attempt = $this->attempts->submitCurrent($request->user(), $quiz);

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

    /**
     * Find the question at a position, or 404 if the paper has no such
     * question. Guards against a crafted position that is inside the range
     * check but has no question behind it.
     */
    private function questionAt(Quiz $quiz, int $position): Question
    {
        $question = $quiz->questionAt($position);

        abort_if($question === null, 404);

        return $question;
    }
}
