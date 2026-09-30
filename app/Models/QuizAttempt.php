<?php

namespace App\Models;

use App\Content\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sitting of a paper.
 *
 * Answers are written here as the learner works through the paper, so a
 * refresh, a dropped connection or a closed tab does not lose their work.
 * `answers` is a map of the question's number on the paper to "a"|"b"|"c"|"d".
 *
 * A paper is not a database row - it lives in the JSON content file - so this
 * names one by its course and paper slug. Both are needed, because a slug is
 * only unique within its course and the two courses are sold separately.
 *
 * None of the stored answers are trusted. The score is always recomputed from
 * the content file at submit time, so a learner cannot post their own way to a
 * pass, and re-editing a paper cannot silently rewrite a stored result.
 */
class QuizAttempt extends Model
{
    use HasFactory;

    public const IN_PROGRESS = 'in_progress';

    public const SUBMITTED = 'submitted';

    protected $fillable = [
        'user_id',
        'course_slug',
        'quiz_slug',
        'status',
        'current_position',
        'answers',
        'score',
        'total',
        'percentage',
        'passed',
        'time_taken_seconds',
        'started_at',
        'submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'current_position' => 'integer',
            'score' => 'integer',
            'total' => 'integer',
            'percentage' => 'decimal:2',
            'passed' => 'boolean',
            'time_taken_seconds' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFor(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->id : $user);
    }

    /**
     * One learner's sittings of one paper, most recent first.
     */
    public function scopeForPaper(Builder $query, User|int $user, string $courseSlug, string $quizSlug): Builder
    {
        return $query->for($user)
            ->where('course_slug', $courseSlug)
            ->where('quiz_slug', $quizSlug);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::IN_PROGRESS);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::SUBMITTED);
    }

    public function scopeRecentFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::SUBMITTED;
    }

    public function inProgress(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }

    /**
     * The learner's answer to a question, if they gave one.
     *
     * Keyed by the question's number on the paper rather than by any id: a
     * question is a line in a content file, and its place on the paper is what
     * identifies it to the learner and to a half-finished attempt.
     */
    public function answerFor(Question|int $question): ?string
    {
        $position = $question instanceof Question ? $question->position : $question;
        $given = $this->answers[$position] ?? null;

        return is_string($given) ? strtolower($given) : null;
    }

    /**
     * Replace every answer on this sitting with the ones given.
     *
     * A whole paper is recorded in one go, because the learner works through it
     * in their browser and posts it all at the end. It replaces rather than
     * merges: the map that arrives is the whole sitting, so a question missing
     * from it is one they left blank.
     *
     * @param  array<int|string, string>  $answers  question number => "a".."d"
     */
    public function replaceAnswers(array $answers): void
    {
        $this->answers = $answers;

        // Cast to JSON explicitly: an empty map must be stored as NULL, not as
        // the empty string the array cast would otherwise produce, so a paper
        // that was never answered and one that was answered wrongly do not
        // look the same in the database.
        $this->attributes['answers'] = $answers === [] ? null : json_encode($answers);
    }

    /**
     * How many questions the learner answered on this sitting.
     */
    public function answeredCount(): int
    {
        $answers = $this->answers ?? [];

        return count(array_filter(
            $answers,
            fn ($letter) => in_array(strtolower((string) $letter), Question::LETTERS, true)
        ));
    }

    /**
     * The score on this sitting, as a whole percentage, or null if it has not
     * been marked yet.
     */
    public function bestPercentage(): ?float
    {
        return $this->percentage !== null ? (float) $this->percentage : null;
    }
}
