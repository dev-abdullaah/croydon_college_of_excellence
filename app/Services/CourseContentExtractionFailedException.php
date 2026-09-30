<?php

namespace App\Services;

use RuntimeException;

/**
 * Raised when course material cannot be turned into the content files.
 *
 * Carries every problem found during the parse, not just the first, so an
 * operator fixing a document sees the whole list in one run.
 */
class CourseContentExtractionFailedException extends RuntimeException
{
    /**
     * @param  array<int, array{severity: string, message: string, context: array}>  $problems
     */
    public function __construct(private readonly array $problems)
    {
        parent::__construct(sprintf(
            'Course extraction stopped: %d problem(s) found. Nothing was written.',
            count($problems)
        ));
    }

    /**
     * @return array<int, array{severity: string, message: string, context: array}>
     */
    public function problems(): array
    {
        return $this->problems;
    }
}
