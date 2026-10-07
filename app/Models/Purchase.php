<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    /** Checkout session created, payment not confirmed by Stripe yet. */
    public const STATUS_PENDING = 'pending';

    /** Stripe confirmed the money arrived. This is the only granting status. */
    public const STATUS_PAID = 'paid';

    /** Payment failed (e.g. card declined). */
    public const STATUS_FAILED = 'failed';

    /** The customer abandoned Checkout. */
    public const STATUS_CANCELLED = 'cancelled';

    /** Stripe expired the session before it was completed. */
    public const STATUS_EXPIRED = 'expired';

    /** The payment was refunded, which revokes access. */
    public const STATUS_REFUNDED = 'refunded';

    /** Learner admitted manually by admin. */
    public const STATUS_ADMITTED = 'admitted';

    /** Access revoked by admin. */
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'student_id',
        'course_id',
        'coupon_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'stripe_customer_id',
        'stripe_event_id',
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
        'refunded_at',
        'failure_reason',
        'terms_accepted_at',
        'terms_version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'admitted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            // When the customer agreed to the terms, and which version of them
            // they agreed to. Recorded on the pending row the moment the
            // consent tick box was accepted, so the acceptance exists even if
            // the payment later fails or is abandoned.
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

    /**
     * Only an `admitted` or `paid` purchase unlocks content. `revoked` / `refunded`
     * deliberately does not, so revoking access is a matter of changing one status.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PAID, self::STATUS_ADMITTED]);
    }

    public function scopeForCourse(Builder $query, Course|int $course): Builder
    {
        return $query->where('course_id', $course instanceof Course ? $course->id : $course);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_ADMITTED], true);
    }

    /**
     * Statuses that still grant (or will grant) access, used for styling.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'Paid',
            self::STATUS_PENDING => 'Awaiting payment confirmation',
            self::STATUS_FAILED => 'Payment failed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Session expired',
            self::STATUS_REFUNDED => 'Refunded',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => 'badge bg-success',
            self::STATUS_PENDING, self::STATUS_CANCELLED, self::STATUS_EXPIRED => 'badge bg-warning text-dark',
            default => 'badge bg-danger',
        };
    }
}
