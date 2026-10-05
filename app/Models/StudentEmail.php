<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEmail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'student_emails';

    protected $fillable = [
        'student_id',
        'email',
        'is_primary',
        'is_verified',
        'verification_token',
        'verification_token_expires_at',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verification_token_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function generateVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresInMinutes = (int) config('auth.email_verification.expire', 60);

        $this->update([
            'verification_token' => $token,
            'verification_token_expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        return $token;
    }

    public function verifyToken(string $token): bool
    {
        if ($this->verification_token === null) {
            return false;
        }

        if ($this->verification_token_expires_at?->isPast()) {
            return false;
        }

        if (! hash_equals($this->verification_token, $token)) {
            return false;
        }

        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verification_token' => null,
            'verification_token_expires_at' => null,
        ]);

        return true;
    }

    public function sendVerificationNotification(): void
    {
        $token = $this->generateVerificationToken();
        $expiresInMinutes = (int) config('auth.email_verification.expire', 60);

        $this->student->notify(new \App\Notifications\UserEmailVerification(
            $token,
            $this->email,
            $expiresInMinutes,
        ));
    }
}