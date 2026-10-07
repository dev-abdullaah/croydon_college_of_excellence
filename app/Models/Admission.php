<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Admission extends Model
{
    use HasFactory;

    protected $table = 'purchases';

    /** Learner submitted request on website, awaiting admin contact & manual payment confirmation. */
    public const STATUS_PENDING = 'pending';

    /** Admin approved the request; course access is active. */
    public const STATUS_ADMITTED = 'admitted';

    /** Legacy alias for admitted status. */
    public const STATUS_PAID = 'paid';

    /** Admin revoked or made course unavailable for the learner. */
    public const STATUS_REVOKED = 'revoked';

    /** Admission request rejected or cancelled. */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'student_id',
        'course_id',
        'coupon_id',
        'customer_email',
        'customer_name',
        'contact_phone',
        'amount',
        'discount_amount',
        'currency',
        'payment_method',
        'status',
        'metadata',
        'learner_notes',
        'admin_notes',
        'requested_at',
        'admitted_at',
        'revoked_at',
        'admitted_by',
        'paid_at',
        'terms_accepted_at',
        'terms_version',
    ];

    protected function casts(): array
    {
        return [
            'amount'            => 'integer',
            'discount_amount'   => 'integer',
            'metadata'          => 'array',
            'requested_at'      => 'datetime',
            'admitted_at'       => 'datetime',
            'revoked_at'        => 'datetime',
            'paid_at'           => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAdmitted(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ADMITTED, self::STATUS_PAID]);
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REVOKED);
    }

    public function scopeForCourse(Builder $query, Course|int $course): Builder
    {
        $id = $course instanceof Course ? $course->id : $course;
        return $query->where('course_id', $id);
    }

    public function isAdmitted(): bool
    {
        return in_array($this->status, [self::STATUS_ADMITTED, self::STATUS_PAID], true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ADMITTED, self::STATUS_PAID => 'badge-light-success',
            self::STATUS_PENDING                    => 'badge-light-warning',
            self::STATUS_REVOKED                    => 'badge-light-danger',
            self::STATUS_REJECTED                   => 'badge-light-dark',
            default                                 => 'badge-light-secondary',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ADMITTED, self::STATUS_PAID => 'Admitted (Active)',
            self::STATUS_PENDING                    => 'Pending Contact / Payment',
            self::STATUS_REVOKED                    => 'Access Revoked',
            self::STATUS_REJECTED                   => 'Request Declined',
            default                                 => ucfirst($this->status),
        };
    }
}
