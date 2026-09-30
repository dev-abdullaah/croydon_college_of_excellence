<?php

namespace App\Content;

/**
 * One multiple-choice question in a paper.
 *
 * Four options, keyed by the letter the learner picks, which is the same A/B/C/D
 * layout the source documents used.
 *
 * `correct` is deliberately not something the question hands out freely. It is
 * read by the marker, on the server, and never sent to the browser while a paper
 * is in progress.
 */
final class Question
{
    public const LETTERS = ['a', 'b', 'c', 'd'];

    /**
     * @param  array<string, string>  $options  keyed a-d
     */
    public function __construct(
        public readonly int $position,
        public readonly string $prompt,
        public readonly array $options,
        public readonly string $correct,
        public readonly string $explanation,
    ) {}

    public function optionText(?string $letter): ?string
    {
        if ($letter === null) {
            return null;
        }

        return $this->options[strtolower($letter)] ?? null;
    }

    public function isCorrect(?string $given): bool
    {
        return $given !== null && strtolower($given) === $this->correct;
    }

    /**
     * Whether the question was answered at all in a marked attempt.
     */
    public function wasAnswered(?string $given): bool
    {
        return $given !== null && in_array(strtolower($given), self::LETTERS, true);
    }
}
