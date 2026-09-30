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

];
