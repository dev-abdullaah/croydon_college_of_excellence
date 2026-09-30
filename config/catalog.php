<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paid Course Catalogue
    |--------------------------------------------------------------------------
    |
     | This is the single, authoritative description of the two sellable
     | courses. `CourseSeeder` copies it into the `courses` table, and
     | `CatalogService` falls back to it if the catalogue has not been
     | seeded yet, so the marketing pages can never go blank.
     |
     | The material itself is not described here. Lessons and papers live in
     | the JSON content files the learning area reads; nothing is sold as a
     | downloadable file.
     |
    | Prices are stored in the currency's smallest unit (pence for GBP) so
    | no floating point rounding ever creeps into an order total.
    |
    | Each course is deliberately separate: buying one never unlocks the
    | other, and each maps to its own Stripe Price.
    |
    */

    'courses' => [

        [
            'slug' => 'life-in-the-uk-course',
            'name' => 'Life in the UK Course',
            'badge' => 'Most Popular',
            'short_description' => 'Ten structured lessons covering UK values, geography, history, modern society, government, law and community participation.',
            'description' => 'A complete self-study preparation course for the official Life in the UK Test. Work through ten structured lessons, consolidate each one with the final knowledge checks, then sit six full classroom mock papers under realistic test conditions before your real exam.',
            'price' => 9900,
            'currency' => 'gbp',
            'stripe_price_key' => 'course',
            'features' => [
                'Life in the UK Lessons 1-10',
                'Final Knowledge Checks for Lessons 1-10',
                '6 Classroom Mock Tests with teacher answer keys',
            ],
        ],

        [
            'slug' => '24-mock-tests',
            'name' => '24 Mock Tests Package',
            'badge' => 'Practice Pack',
            'short_description' => 'Twenty-four separate full-length Life in the UK papers to rehearse against the clock, with answer keys included.',
            'description' => 'Twenty-four complete practice papers, 24 questions each (576 non-repeated course questions), every one sampling all ten lessons. Answer keys are included at the back so you can mark yourself once you have finished a paper.',
            'price' => 4900,
            'currency' => 'gbp',
            'stripe_price_key' => 'mock_tests',
            'features' => [
                '24 mock tests',
                '24 questions per paper, 45 minutes allowed',
                'Answer keys included',
            ],
        ],

    ],

];
