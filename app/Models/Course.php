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

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
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

    /**
     * Whether this course has lessons to read.
     *
     * The £49 mock test pack has none - it is papers only - so views label its
     * call to action accordingly instead of sending buyers to "start learning"
     * when there is nothing to learn.
     */
    public function hasLessons(): bool
    {
        return app(CourseContent::class)->lessons($this->slug)->isNotEmpty();
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

    /**
     * Whether this student may read the material.
     *
     * A completed Stripe payment is the only thing that grants it, so this is
     * deliberately the same question Student::hasPurchased() answers. There is no
     * setting that relaxes it.
     */
    public function hasAccessFor(?Student $student): bool
    {
        return $student?->hasPurchased($this) ?? false;
    }

    /**
     * Verifiable certificates issued for this course.
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
