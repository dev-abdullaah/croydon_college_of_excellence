<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paid Course Catalogue
    |--------------------------------------------------------------------------
    |
    | This is the single, authoritative description of the two sellable
    | courses. `CourseSeeder` copies it into the `courses` and
    | `course_documents` tables, and `CatalogService` falls back to it if
    | the catalogue has not been seeded yet, so the marketing pages can
    | never go blank.
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
            'documents' => [
                [
                    'title' => 'Life in the UK Lessons 1-10',
                    'description' => 'The full lesson pack: UK values and citizenship, geography and symbols, early Britain, the Middle Ages and Parliament, Tudors to Civil War, empire and the Victorian age, the 20th century, modern society and culture, government and devolution, and law and public services.',
                    'filename' => 'Life in the UK Lesson 1-10.docx',
                    'sort_order' => 1,
                ],
                [
                    'title' => 'Final Knowledge Checks (Lessons 1-10)',
                    'description' => 'Ten multiple-choice revision checks, one per lesson, with answers so you can see exactly where to go back over the material.',
                    'filename' => 'Life in the UK Lesson 1-10 Final Knowledge Checks.docx',
                    'sort_order' => 2,
                ],
                [
                    'title' => '6 Classroom Mock Tests',
                    'description' => 'Six independent end-of-course papers, 24 questions each (144 questions in total), with teacher answer keys. Allow 45 minutes per paper; 18 out of 24 is a pass.',
                    'filename' => '6 Classroom Mock Test.docx',
                    'sort_order' => 3,
                ],
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
            'documents' => [
                [
                    'title' => 'Total 24 Mock Tests - Life in the UK',
                    'description' => 'Mock Tests 1 to 24. Each paper contains 24 multiple-choice questions drawn from across all ten lessons, with a score sheet and the full answer keys at the back.',
                    'filename' => 'Total 24 Mock Tests Life in the UK.docx',
                    'sort_order' => 1,
                ],
            ],
        ],

    ],

];
