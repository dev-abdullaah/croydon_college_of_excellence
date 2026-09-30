<?php

namespace App\Models;

use App\Content\CourseContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'badge',
        'short_description',
        'description',
        'price',
        'currency',
        'features',
        'stripe_price_key',
        'stripe_price_id',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Courses are addressed by their slug everywhere in the URL space.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Whether this course has anything to read or sit.
     *
     * The material lives in the JSON content files, so this asks the content
     * store rather than the database - the course row knows nothing about it.
     * The dashboard uses this to decide whether to offer the learning area at
     * all, rather than sending a buyer to an empty page.
     */
    public function hasLearningContent(): bool
    {
        return app(CourseContent::class)->hasContent($this->slug);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The Stripe Price this course is sold through.
     *
     * Resolution order: an explicit per-course price id, then the key stored
     * against the course mapped into config/stripe.php. Prices are always
     * read from the server - a price supplied by the browser is ignored.
     */
    public function stripePriceId(): ?string
    {
        $priceId = $this->stripe_price_id ?: config("stripe.prices.{$this->stripe_price_key}");

        return filled($priceId) ? $priceId : null;
    }

    /**
     * Price in pounds, e.g. 9900 => 99, 4950 => 49.5.
     */
    public function priceInPounds(): float
    {
        return $this->price / 100;
    }

    /**
     * Human readable British price, e.g. "£99" or "£49.50".
     */
    public function formattedPrice(): string
    {
        $pounds = $this->priceInPounds();

        return '£'.(fmod($pounds, 1.0) === 0.0
            ? number_format($pounds, 0)
            : number_format($pounds, 2));
    }

    public function hasAccessFor(?User $user): bool
    {
        // While the paywall is off every signed-in account may read the
        // material, so a purchase is not what grants access.
        if (! $this->requiresPurchase()) {
            return $user !== null;
        }

        return $user?->hasPurchased($this) ?? false;
    }

    /**
     * Whether opening this course's material requires a paid purchase.
     *
     * Off while the lessons and papers are being built and marked; a live
     * site wants it on. See config/course-content.php.
     */
    public function requiresPurchase(): bool
    {
        return (bool) config('course-content.require_purchase');
    }
}
