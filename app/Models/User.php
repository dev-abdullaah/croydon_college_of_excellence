<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * A learner.
 *
 * Implements MustVerifyEmail, so `email_verified_at` decides what the account
 * is allowed to reach - see routes/web.php.
 *
 * The trait is imported as MustVerifyEmailTrait rather than under its own
 * name on purpose. PHP resolves names in a `use TraitName` list against the
 * current namespace and ignores the imports at the top of the file, so writing
 * the bare `MustVerifyEmail` there quietly binds the *interface* of that name
 * instead of the trait. The result is not a readable error: the process dies
 * the moment a row is hydrated from the database.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Every purchase this user has made, in any status.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Every lesson this user has marked as finished.
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Every sitting of every paper, in progress and submitted alike.
     */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * The single reusable access check. Everything that gates paid content
     * ultimately asks this question, so there is exactly one definition of
     * "has paid" in the application.
     */
    public function hasPurchased(Course|int|string $course): bool
    {
        $courseId = match (true) {
            $course instanceof Course => $course->id,
            is_numeric($course) => (int) $course,
            default => Course::query()->where('slug', $course)->value('id'),
        };

        if (! $courseId) {
            return false;
        }

        return $this->purchases()
            ->forCourse($courseId)
            ->paid()
            ->exists();
    }

    /**
     * The active courses this user is allowed to open, in catalogue order.
     *
     * @return Collection<int, Course>
     */
    public function purchasedCourses()
    {
        return Course::query()
            ->whereIn('id', $this->purchases()->paid()->select('course_id'))
            ->ordered()
            ->get();
    }
}
