<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseDocument;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Read side of the course catalogue.
 *
 * Marketing pages must never 500 just because the catalogue has not been
 * seeded yet, so when the `courses` table is missing or empty we fall back
 * to config/catalog.php - the very same file CourseSeeder writes into the
 * database. There is therefore only ever one definition of a course.
 */
class CatalogService
{
    /**
     * Active courses, ready to render. Always a collection of Course
     * models, whether they came from the database or from the config.
     *
     * @return Collection<int, Course>
     */
    public function displayCourses(): Collection
    {
        try {
            $courses = Course::query()->active()->ordered()->with('documents')->get();

            if ($courses->isNotEmpty()) {
                return $courses;
            }
        } catch (Throwable $e) {
            // The catalogue table is missing or the database is unreachable.
            // A marketing page must degrade gracefully rather than break.
            report($e);
        }

        return $this->fromConfig();
    }

    /**
     * Build unsaved Course models out of config/catalog.php.
     *
     * @return Collection<int, Course>
     */
    public function fromConfig(): Collection
    {
        return collect(config('catalog.courses', []))
            ->map(function (array $definition) {
                $course = new Course([
                    'name' => $definition['name'] ?? '',
                    'slug' => $definition['slug'] ?? '',
                    'badge' => $definition['badge'] ?? null,
                    'short_description' => $definition['short_description'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'price' => $definition['price'] ?? 0,
                    'currency' => $definition['currency'] ?? config('stripe.currency', 'gbp'),
                    'features' => $definition['features'] ?? [],
                    'stripe_price_key' => $definition['stripe_price_key'] ?? null,
                    'is_active' => true,
                    'sort_order' => 0,
                ]);

                $course->setRelation('documents', $this->documentsFromConfig($definition['documents'] ?? []));

                return $course;
            })
            ->values();
    }

    /**
     * @return Collection<int, CourseDocument>
     */
    protected function documentsFromConfig(array $documents): Collection
    {
        return collect($documents)
            ->map(function (array $document) {
                return new CourseDocument([
                    'title' => $document['title'] ?? '',
                    'filename' => $document['filename'] ?? '',
                    'description' => $document['description'] ?? null,
                    'file_type' => $document['file_type'] ?? 'docx',
                    'sort_order' => $document['sort_order'] ?? 0,
                ]);
            })
            ->values();
    }
}
