<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseDocument;
use Illuminate\Database\Seeder;

/**
 * Loads the paid course catalogue from config/catalog.php.
 *
 * Safe to run repeatedly: courses are matched on their slug and documents on
 * their filename, so re-seeding updates the copy without touching any
 * purchase a customer has already made.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $missing = [];

        foreach (config('catalog.courses', []) as $index => $definition) {
            $course = Course::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'badge' => $definition['badge'] ?? null,
                    'short_description' => $definition['short_description'] ?? null,
                    'description' => $definition['description'] ?? null,
                    'price' => $definition['price'],
                    'currency' => $definition['currency'] ?? config('stripe.currency', 'gbp'),
                    'features' => $definition['features'] ?? [],
                    'stripe_price_key' => $definition['stripe_price_key'] ?? null,
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );

            foreach ($definition['documents'] ?? [] as $position => $document) {
                // Only catalogue a file that actually exists, so a purchase can
                // never point at something that cannot be downloaded.
                $exists = is_file(base_path('course-files/'.basename($document['filename'])));

                if (! $exists) {
                    $missing[] = $document['filename'];
                }

                CourseDocument::updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'filename' => basename($document['filename']),
                    ],
                    [
                        'title' => $document['title'],
                        'description' => $document['description'] ?? null,
                        'file_type' => $document['file_type'] ?? 'docx',
                        'sort_order' => $document['sort_order'] ?? $position,
                    ]
                );
            }
        }

        if ($missing !== []) {
            $this->command?->warn('These course-files are referenced but not present in course-files/:');
            $this->command?->warn('  - '.implode(PHP_EOL.'  - ', $missing));
        }
    }
}
