<?php

namespace App\Services;

use App\Content\CourseContent;
use App\Content\Question;
use App\Content\Quiz;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sitting a paper.
 *
 * Answers are saved as the learner moves from question to question so a
 * refresh or a dropped connection never loses their work, and the score is
 * always recomputed from the content file at the moment they finish. There is
 * no path by which a stored score, or anything the browser posts, decides
 * whether someone passed.
 */
class QuizAttemptService
{
    public function __construct(private readonly CourseContent $content) {}

    /**
     * The learner's current sitting of a paper, starting a new one if they
     * have not got one in progress.
     *
     * Coming back to a paper resumes where they left off rather than wiping
     * and restarting it. This is the paper *screen*'s entry point: opening a
     * paper you have already finished is how you sit it again.
     */
    public function resumeOrStart(User $user, Quiz $quiz): QuizAttempt
    {
        return $this->inProgress($user, $quiz) ?? $this->start($user, $quiz);
    }

    /**
     * Begin a fresh sitting.
     */
    public function start(User $user, Quiz $quiz): QuizAttempt
    {
        return QuizAttempt::create([
            'user_id' => $user->id,
            'course_slug' => $quiz->course,
            'quiz_slug' => $quiz->slug,
            'status' => QuizAttempt::IN_PROGRESS,
            'current_position' => 1,
            'answers' => [],
            'started_at' => now(),
        ]);
    }

    /**
     * The sitting that is currently open, or null if there is not one.
     */
    public function inProgress(User $user, Quiz $quiz): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->forPaper($user, $quiz->course, $quiz->slug)
            ->inProgress()
            ->recentFirst()
            ->first();
    }

    /**
     * The attempt to record an answer against.
     *
     * Deliberately not `resumeOrStart`. A posting that arrives after the
     * paper was finished - a stale tab, a double-click, a replayed request -
     * must be refused rather than quietly opening a second sitting, or a
     * learner could end up marking a paper they never meant to retake. To sit
     * a paper again they open it, which is an explicit act.
     */
    public function openForAnswering(User $user, Quiz $quiz): QuizAttempt
    {
        $latest = QuizAttempt::query()
            ->forPaper($user, $quiz->course, $quiz->slug)
            ->recentFirst()
            ->first();

        if ($latest === null) {
            return $this->start($user, $quiz);
        }

        abort_if(
            $latest->isSubmitted(),
            422,
            'This sitting has already been submitted. Open the paper to start a new one.'
        );

        return $latest;
    }

    /**
     * Store one answer and move on.
     *
     * A null letter clears the answer, which is how a learner changes their
     * mind on the jump-to-question nav.
     */
    public function recordAnswer(QuizAttempt $attempt, Question $question, ?string $letter): QuizAttempt
    {
        abort_unless($attempt->inProgress(), 422, 'This paper has already been submitted.');

        $letter = $letter !== null ? strtolower($letter) : null;

        if ($letter !== null && ! in_array($letter, Question::LETTERS, true)) {
            $letter = null;
        }

        $attempt->recordAnswer($question->position, $letter);

        $attempt->current_position = min($question->position + 1, $this->paper($attempt)->questionCount());

        $attempt->save();

        return $attempt;
    }

    /**
     * Move the cursor without changing any answer, for the jump-to-question
     * nav and the back button.
     */
    public function moveTo(QuizAttempt $attempt, int $position): QuizAttempt
    {
        abort_unless($attempt->inProgress(), 422, 'This paper has already been submitted.');

        $attempt->current_position = max(1, min($position, $this->paper($attempt)->questionCount()));
        $attempt->save();

        return $attempt;
    }

    /**
     * Mark whatever sitting is currently open, or return the one that was
     * already marked.
     *
     * A second submit of the same paper is answered with the existing result
     * rather than opening a fresh blank attempt and immediately closing it,
     * so a double-click or a replayed request cannot manufacture an empty
     * score in the learner's history.
     */
    public function submitCurrent(User $user, Quiz $quiz): QuizAttempt
    {
        $open = $this->inProgress($user, $quiz);

        if ($open !== null) {
            return $this->submit($open);
        }

        $finished = QuizAttempt::query()
            ->forPaper($user, $quiz->course, $quiz->slug)
            ->submitted()
            ->recentFirst()
            ->first();

        abort_if($finished === null, 422, 'There is nothing to submit for this paper.');

        return $finished;
    }

    /**
     * Mark the paper.
     *
     * The score is derived from the content file, never from what the browser
     * claims. Questions left blank count as wrong, which is how the real test
     * behaves.
     */
    public function submit(QuizAttempt $attempt): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            return $attempt;
        }

        $quiz = $this->paper($attempt);

        return DB::transaction(function () use ($attempt, $quiz) {
            $answers = $attempt->answers ?? [];

            $score = 0;

            foreach ($quiz->questions as $question) {
                if ($question->isCorrect($answers[$question->position] ?? null)) {
                    $score++;
                }
            }

            $total = $quiz->questionCount();
            $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0.0;

            $attempt->status = QuizAttempt::SUBMITTED;
            $attempt->score = $score;
            $attempt->total = $total;
            $attempt->percentage = $percentage;
            $attempt->passed = $percentage >= $quiz->pass_mark_percent;
            // How long the learner sat the paper. The diff is measured from the
            // start, so the start is the left-hand side; asking for it the
            // other way round returns a negative number and clamps to zero.
            // Carbon 3 returns a float, and the column is whole seconds, so
            // the result is cast down. Clamped at zero to absorb a clock that
            // has drifted backwards mid-paper.
            $attempt->time_taken_seconds = (int) max(
                0,
                $attempt->started_at->diffInSeconds(now())
            );
            $attempt->submitted_at = now();

            $attempt->save();

            return $attempt;
        });
    }

    /**
     * Every finished sitting of a paper, best first.
     *
     * @return Collection<int, QuizAttempt>
     */
    public function history(User $user, Quiz $quiz): Collection
    {
        return QuizAttempt::query()
            ->forPaper($user, $quiz->course, $quiz->slug)
            ->submitted()
            ->recentFirst()
            ->get();
    }

    /**
     * The learner's best finished score on a paper as a whole percentage, or
     * null if they have never finished it.
     */
    public function bestPercentage(User $user, Quiz $quiz): ?float
    {
        $best = QuizAttempt::query()
            ->forPaper($user, $quiz->course, $quiz->slug)
            ->submitted()
            ->max('percentage');

        return $best !== null ? (float) $best : null;
    }

    /**
     * Best score across all of a learner's attempts at one course's papers,
     * keyed by paper slug.
     *
     * Used to decorate the paper list without an N+1 query, so it asks for a
     * whole course at once rather than a paper at a time. Each row carries the
     * best percentage and how many times the paper has been sat, which is what
     * the paper list shows.
     *
     * @return Collection<string, QuizAttempt>
     */
    public function bestScoresBySlug(User $user, string $courseSlug): Collection
    {
        return QuizAttempt::query()
            ->for($user)
            ->where('course_slug', $courseSlug)
            ->submitted()
            ->selectRaw('quiz_slug, MAX(percentage) as best_percentage, COUNT(*) as times_sat')
            ->groupBy('quiz_slug')
            ->get()
            ->keyBy('quiz_slug');
    }

    /**
     * The paper an attempt belongs to.
     *
     * An attempt names a paper by slug, so a paper that has since been renamed
     * or withdrawn from the content file would leave an attempt pointing at
     * nothing. That is a content problem, not a learner one, and it is reported
     * as such rather than quietly marked against whatever is in the file now.
     */
    private function paper(QuizAttempt $attempt): Quiz
    {
        $quiz = $this->content->quiz($attempt->course_slug, (string) $attempt->quiz_slug);

        abort_if($quiz === null, 404, sprintf(
            'This result is for the paper "%s", which is no longer part of the course.',
            $attempt->quiz_slug
        ));

        return $quiz;
    }
}
