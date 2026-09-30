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
 * The learner moves between questions in the browser, which holds their
 * choices so that working through a paper costs no server request. They arrive
 * together in `recordAnswers()` when the paper is finished.
 *
 * Nothing the browser posts is believed. Only positions that are really on the
 * paper and letters that could have been offered are kept, and the score is
 * always recomputed from the content file at that moment. There is no path by
 * which a stored score, a letter, or a question of the browser's own making
 * decides whether someone passed.
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
     * Store every answer of a sitting in one go, then mark it.
     *
     * The learner works through the paper in the browser and their choices are
     * held there, so moving between questions costs no request. They arrive
     * together when they finish.
     *
     * Only positions that are really on this paper are kept, and only a letter
     * the question could have been answered with. A hand-rolled request cannot
     * invent a question to be credited with a mark or a letter that is not
     * offered, so it cannot inflate the total.
     *
     * @param  array<int|string, mixed>  $answers  question number => "a".."d"
     */
    public function recordAnswers(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        abort_unless($attempt->inProgress(), 422, 'This paper has already been submitted.');

        $quiz = $this->paper($attempt);

        $kept = [];

        foreach ($quiz->questions as $question) {
            $given = $answers[$question->position] ?? $answers[(string) $question->position] ?? null;

            if (is_string($given) && in_array(strtolower($given), Question::LETTERS, true)) {
                $kept[$question->position] = strtolower($given);
            }
        }

        // Replaces rather than merges: the browser holds the full picture, so
        // anything the learner cleared out of it is genuinely blank.
        $attempt->replaceAnswers($kept);
        $attempt->current_position = $quiz->questionCount();
        $attempt->save();

        return $this->submit($attempt);
    }

    /**
     * Mark the open sitting with the answers the browser has been holding, or
     * return the one that was already marked.
     *
     * A second submit of the same paper is answered with the existing result
     * rather than opening a fresh blank attempt and immediately closing it,
     * so a double-click or a replayed request cannot manufacture an empty
     * score in the learner's history.
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function submitWithAnswers(User $user, Quiz $quiz, array $answers): QuizAttempt
    {
        $open = $this->inProgress($user, $quiz);

        if ($open !== null) {
            return $this->recordAnswers($open, $answers);
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
