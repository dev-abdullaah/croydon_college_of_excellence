<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'certificate_number',
        'student_id',
        'course_id',
        'admission_id',
        'issued_at',
        'grade',
        'verification_hash',
        'status',
        'revoked_at',
        'revocation_reason',
        'issued_by',
    ];

    protected $casts = [
        'issued_at'  => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function generateNumber(): string
    {
        do {
            $number = 'CCE-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('certificate_number', $number)->exists());

        return $number;
    }

    public static function generateHash(string $certificateNumber, int $studentId): string
    {
        return hash('sha256', $certificateNumber . '|' . $studentId . '|' . config('app.key') . '|' . microtime());
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REVOKED);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function verificationUrl(): string
    {
        return route('certificates.verify', ['certificate_number' => $this->certificate_number]);
    }

    public function statusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE  => 'badge-light-success text-success',
            self::STATUS_REVOKED => 'badge-light-danger text-danger',
            default              => 'badge-light-secondary text-secondary',
        };
    }
}
