<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per Stripe webhook event we have seen.
 *
 * The unique index on `event_id` is what makes payment processing
 * idempotent: a redelivered event fails to insert and is acknowledged
 * without touching any purchase.
 */
class StripeWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'type',
        'livemode',
        'payload',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'livemode' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
