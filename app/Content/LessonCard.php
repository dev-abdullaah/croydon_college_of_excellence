<?php

namespace App\Content;

/**
 * One study card: a question and the answer to it.
 *
 * The cards are the reading material. A lesson is a hundred of them, and the
 * learner works through the lesson then sits the knowledge check on the same
 * subject.
 */
final class LessonCard
{
    public function __construct(
        public readonly int $position,
        public readonly string $question,
        public readonly string $answer,
    ) {}
}
