<?php

namespace App\Models;

use App\Notifications\EmailVerificationCode;
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
        // The hash is the value a stolen row would be brute-forced against, so
        // it never belongs in a serialised payload.
        'verification_code_hash',
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
            'verification_code_sent_at' => 'datetime',
            'verification_code_locked_until' => 'datetime',
            // Small int, not hashed, and not hidden: unlike a password this is
            // never a credential by itself, only a counter beside one.
            'verification_code_attempts' => 'integer',
            'password' => 'hashed',
        ];
    }

    /**
     * Mint a verification code, store a hash of it, and return the plain value
     * for mailing.
     *
     * Only the hash is kept. If the database is ever read by somebody who
     * should not be - a dump in a backup, a replica, an over-broad SQL grant -
     * they get hashes rather than codes that would let them verify an address
     * they do not own.
     *
     * Any code issued earlier is replaced, not kept alongside. Two valid codes
     * at once would mean the second one is still a live credential sitting in
     * an inbox long after the first was superseded, and the user has no way to
     * know which is current.
     *
     * `random_int` is the CSPRNG, not `rand`. A predictable code is not a code.
     */
    public function issueVerificationCode(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->writeVerificationCodeState([
            'verification_code_hash' => hash('sha256', $code),
            'verification_code_sent_at' => now(),
            // A new code restarts the count. Somebody who burned five guesses
            // on a code they lost is not an attacker for the next one.
            'verification_code_attempts' => 0,
            'verification_code_locked_until' => null,
        ]);

        return $code;
    }

    /**
     * Whether this code is the one currently outstanding, and not stale.
     *
     * Compared with hash_equals so that the time taken does not reveal how
     * much of a guessed hash was correct. It is a plain SHA-256 rather than a
     * password hash on purpose: the input is six digits, so there is nothing to
     * brute force offline - a slow hash would only make legitimate sign-ins
     * wait.
     */
    public function verificationCodeMatches(string $code): bool
    {
        if ($this->verification_code_hash === null || $code === '') {
            return false;
        }

        return hash_equals($this->verification_code_hash, hash('sha256', $code));
    }

    /**
     * Whether the outstanding code has passed its expiry.
     *
     * A user who has never been sent a code is not expired, they simply have
     * nothing to redeem - which is a different case and must not read as an
     * expired code to the controller.
     */
    public function verificationCodeExpired(): bool
    {
        if ($this->verification_code_sent_at === null) {
            return false;
        }

        return $this->verification_code_sent_at
            ->addMinutes((int) config('auth.verification_code.expire', 15))
            ->isPast();
    }

    /**
     * Whether wrong answers have used up the allowance.
     */
    public function verificationCodeLocked(): bool
    {
        if ($this->verification_code_locked_until !== null) {
            return $this->verification_code_locked_until->isFuture();
        }

        return $this->verification_code_attempts
            >= (int) config('auth.verification_code.max_attempts', 5);
    }

    /**
     * Record a wrong answer, and lock the code out if that was the last one.
     */
    public function recordFailedVerificationAttempt(): void
    {
        /*
         | Counted from the database rather than from $this, which may be a
         | stale instance. Two concurrent wrong answers against a model loaded
         | before either would otherwise each write "1" and the second guess
         | would cost nothing.
         */
        $attempts = $this->newQuery()
            ->whereKey($this->getKey())
            ->value('verification_code_attempts') ?? 0;

        $attempts++;

        $this->writeVerificationCodeState([
            'verification_code_attempts' => $attempts,
            'verification_code_locked_until' => $attempts
                >= (int) config('auth.verification_code.max_attempts', 5)
                ? now()->addMinutes((int) config('auth.verification_code.lockout_minutes', 15))
                : $this->verification_code_locked_until,
        ]);
    }

    /**
     * Discard the outstanding code once it has been redeemed, so a code that
     * leaked into a forwarded thread is spent.
     */
    public function clearVerificationCode(): void
    {
        $this->writeVerificationCodeState([
            'verification_code_hash' => null,
            'verification_code_sent_at' => null,
            'verification_code_attempts' => 0,
            'verification_code_locked_until' => null,
        ]);
    }

    /**
     * Write the verification columns straight to the database, then sync the
     * in-memory copy.
     *
     * Not `forceFill()->save()`, because that only writes columns Eloquent
     * considers dirty. Setting `verification_code_attempts` back to 0 on an
     * instance that already held 0 in memory is not dirty, so the reset would
     * silently not happen - and a counter that refuses to clear turns the
     * lockout into something the user cannot escape by requesting a new code.
     *
     * Writing by primary key also keeps a stale instance from resurrecting
     * values another request has since changed.
     */
    private function writeVerificationCodeState(array $attributes): void
    {
        $this->newQuery()
            ->whereKey($this->getKey())
            ->update($attributes);

        $this->forceFill($attributes);
        $this->syncOriginalAttributes(array_keys($attributes));
    }

    /**
     * Mint a code and mail it.
     *
     * Every caller goes through here, so there is one definition of what gets
     * sent and no route through which an unverified account can be left without
     * a code it cannot obtain.
     */
    public function sendEmailVerificationCodeNotification(): void
    {
        $this->notifyNow(new EmailVerificationCode(
            $this->issueVerificationCode(),
            (int) config('auth.verification_code.expire', 15),
        ));
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
