<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

/**
 * Loads the paid course catalogue from config/catalog.php.
 *
 * Safe to run repeatedly: courses are matched on their slug, so re-seeding
 * updates the copy without touching any purchase a customer has already
 * made.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('catalog.courses', []) as $index => $definition) {
            Course::updateOrCreate(
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
        }
    }
}
