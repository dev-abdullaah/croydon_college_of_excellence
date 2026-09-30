<?php

namespace App\Content;

use Illuminate\Support\Collection;

/**
 * A paper a learner can sit.
 *
 * Three kinds exist:
 *
 *   knowledge_check - 10 questions, the revision that follows a lesson
 *   classroom_mock  - 24 questions, the end-of-course classroom papers
 *   mock_test       - 24 questions, the 24 paper practice pack
 *
 * A knowledge check's number matches the lesson it belongs to, which is how the
 * lesson page links to it and how a result links back to the lesson.
 *
 * This is a value read from a JSON file, not a database row, and it carries no
 * route binding of its own — a URL uses the slug, and App\Content\CourseContent
 * is what turns that slug back into a paper.
 *
 * @property-read Collection<int, Question> $questions
 */
final class Quiz
{
    /** Papers that revise a single lesson, as opposed to whole-course papers. */
    public const KIND_KNOWLEDGE_CHECK = 'knowledge_check';

    public const KIND_CLASSROOM_MOCK = 'classroom_mock';

    public const KIND_MOCK_TEST = 'mock_test';

    /**
     * @param  Collection<int, Question>  $questions
     */
    public function __construct(
        public readonly string $course,
        public readonly string $kind,
        public readonly int $number,
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $time_limit_minutes,
        public readonly int $pass_mark_percent,
        public readonly Collection $questions,
    ) {}

    public function questionCount(): int
    {
        return $this->questions->count();
    }

    /**
     * The question at a 1-based position, or null if the paper has no such
     * question. A null here is what stops a crafted position being answered.
     */
    public function questionAt(int $position): ?Question
    {
        // The questions are stored in position order and numbered from 1, so the
        // index is the position less one. Held to the array rather than assumed
        // so a gap in the file cannot silently answer the wrong question.
        $question = $this->questions->get($position - 1);

        return $question?->position === $position ? $question : null;
    }

    /**
     * The number of correct answers needed to pass, e.g. 18 out of 24.
     */
    public function passMarkCount(): int
    {
        return (int) ceil($this->questionCount() * ($this->pass_mark_percent / 100));
    }

    public function isKnowledgeCheck(): bool
    {
        return $this->kind === self::KIND_KNOWLEDGE_CHECK;
    }

    public function isClassroomMock(): bool
    {
        return $this->kind === self::KIND_CLASSROOM_MOCK;
    }

    public function isMockTest(): bool
    {
        return $this->kind === self::KIND_MOCK_TEST;
    }

    /**
     * A human label for the kind of paper, for the paper list heading.
     */
    public function kindLabel(): string
    {
        return self::kindLabelFor($this->kind);
    }

    /**
     * The same label without needing a paper, for grouping a collection.
     */
    public static function kindLabelFor(?string $kind): string
    {
        return match ($kind) {
            self::KIND_KNOWLEDGE_CHECK => 'Knowledge Checks',
            self::KIND_CLASSROOM_MOCK => 'Classroom Mock Tests',
            self::KIND_MOCK_TEST => 'Mock Tests',
            default => 'Papers',
        };
    }

    /**
     * The kinds in the order a learner works through the course.
     *
     * @return array<int, string>
     */
    public static function kindOrder(): array
    {
        return [self::KIND_KNOWLEDGE_CHECK, self::KIND_CLASSROOM_MOCK, self::KIND_MOCK_TEST];
    }
}
