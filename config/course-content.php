<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Where the course material lives
    |---------------------------------------------------------------------------
    |
    | The lessons and the papers are the product, and they are held as JSON in
    | the repository rather than in database tables. A course is therefore
    | deployed by copying the code: there is no content to import, no seed to
    | remember and no risk of the two copies drifting apart.
    |
    | The paths are configuration so a test can point them at a small fixture.
    | Everything else - the schema, the file layout, the answer keys - is read
    | from the files themselves. See App\Content\CourseContent.
    |
    */

    'lessons' => env('COURSE_LESSON_CONTENT', 'database/data/lesson-content.json'),

    'quizzes' => env('COURSE_QUIZ_CONTENT', 'database/data/quiz-content.json'),

    /*
    |---------------------------------------------------------------------------
    | Is a purchase required to read the material?
    |---------------------------------------------------------------------------
    |
    | The course material is built and marked before it is sold, so during
    | development the paywall is switched off and any signed-in account can
    | open every lesson and paper. Set COURSE_REQUIRE_PURCHASE=true (or 1) to
    | require a paid purchase again, which is what a live site wants.
    |
    | This only ever *relaxes* the check. A signed-in user is still required
    | either way, because a quiz sitting is a database row keyed to a user and
    | a lesson's progress is recorded against one. Turning this off does not
    | make the material public to guests.
    |
    | `php artisan payments:doctor` warns while this is off, so a live site
    | cannot quietly ship with the paywall down.
    |
    */

    'require_purchase' => filter_var(
        env('COURSE_REQUIRE_PURCHASE', false),
        FILTER_VALIDATE_BOOLEAN
    ),

];
