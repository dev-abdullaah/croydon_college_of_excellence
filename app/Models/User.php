<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
     * @return \Illuminate\Support\Collection<int, Course>
     */
    public function purchasedCourses()
    {
        return Course::query()
            ->whereIn('id', $this->purchases()->paid()->select('course_id'))
            ->ordered()
            ->get();
    }
}
