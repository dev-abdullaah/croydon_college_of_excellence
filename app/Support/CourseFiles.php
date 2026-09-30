<?php

namespace App\Support;

/**
 * Where the course content lives.
 *
 * The lessons and papers are files in the repository, not database rows, and
 * these two paths are the only places they can be. Config points at defaults
 * here so tests and the extractor can override them; anything that reads the
 * content should go through config('course-content.*') rather than using these
 * constants directly.
 */
final class CourseFiles
{
    /** The study cards, ten lessons of a hundred. */
    public const LESSONS = 'database/data/lesson-content.json';

    /** Every paper a learner can sit, with its answer key. */
    public const PAPERS = 'database/data/quiz-content.json';
}