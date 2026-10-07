<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    public const DISCOUNT_PERCENTAGE = 'percentage';
    public const DISCOUNT_FIXED = 'fixed';

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'course_id',
        'min_spend',
        'max_uses',
        'times_used',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'min_spend'      => 'integer',
            'max_uses'       => 'integer',
            'times_used'     => 'integer',
            'starts_at'      => 'datetime',
            'expires_at'     => 'datetime',
            'is_active'      => 'boolean',
        ];
    }

    /**
     * Always store coupon codes in uppercase and trimmed.
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Check if coupon is currently valid for a given course and price.
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateFor(Course $course, int $originalAmount): array
    {
        if (! $this->is_active) {
            return ['valid' => false, 'error' => 'This coupon code is currently deactivated.'];
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return ['valid' => false, 'error' => 'This coupon code is not valid yet.'];
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return ['valid' => false, 'error' => 'This coupon code has expired.'];
        }

        if ($this->max_uses !== null && $this->times_used >= $this->max_uses) {
            return ['valid' => false, 'error' => 'This coupon code has reached its maximum redemptions.'];
        }

        if ($this->course_id !== null && $this->course_id != $course->id) {
            return ['valid' => false, 'error' => "This coupon is only valid for the '{$this->course->name}' course."];
        }

        if ($this->min_spend !== null && $originalAmount < $this->min_spend) {
            $minFormatted = number_format($this->min_spend / 100, 2);
            return ['valid' => false, 'error' => "A minimum order of £{$minFormatted} is required for this coupon."];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Calculate discount amount in pence.
     */
    public function calculateDiscount(int $originalAmount): int
    {
        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            $discount = (int) round($originalAmount * ($this->discount_value / 100));
            return min($discount, $originalAmount);
        }

        return min($this->discount_value, $originalAmount);
    }

    /**
     * Human-readable formatted discount description.
     */
    public function formattedDiscount(): string
    {
        if ($this->discount_type === self::DISCOUNT_PERCENTAGE) {
            return "{$this->discount_value}% OFF";
        }

        return '£' . number_format($this->discount_value / 100, 2) . ' OFF';
    }

    /**
     * Increment usage counter.
     */
    public function incrementUsage(): void
    {
        $this->increment('times_used');
    }
}
