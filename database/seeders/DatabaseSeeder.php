<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // The paid course catalogue (Life in the UK Course and the 24 Mock
        // Tests package). Required before the checkout buttons will work.
        $this->call(CourseSeeder::class);

        // Seed the primary super administrator for the isolated backend panel.
        $this->call(AdminUserSeeder::class);

        // The lessons and papers are not seeded: they are the JSON files in
        // `database/data/`, read straight from the repository at request time.
        // There is nothing to import and nothing to drift out of step.
    }
}
