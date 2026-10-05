<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * "I have finished this lesson".
 *
 * Deliberately tiny - the lesson reader does not gate content on it, it is
 * the learner's own record that they got to the end. That keeps someone
 * re-reading a lesson from having to redo anything to keep their progress.
 *
 * A lesson is not a database row, so this names one by its course and lesson
 * slug. Both are needed because a slug is only unique within its course and the
 * two courses are sold separately.
 */
class LessonProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_slug',
        'lesson_slug',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeFor(Builder $query, Student|int $student): Builder
    {
        return $query->where('student_id', $student instanceof Student ? $student->id : $student);
    }

    /**
     * Which of one course's lessons a learner has read, as a set of lesson slugs.
     *
     * The learning pages ask "have they read this?" about every lesson at once,
     * and this is the one query that answers it. Slugs rather than ids, so the
     * lesson list can be asked about as it is drawn.
     *
     * @return Collection<int, string>
     */
    public static function readSlugsFor(Student|int $student, string $courseSlug): Collection
    {
        $id = $student instanceof Student ? $student->id : $student;

        return static::query()
            ->for($id)
            ->where('course_slug', $courseSlug)
            ->pluck('lesson_slug');
    }
}
