<?php

namespace App\Content;

use Illuminate\Support\Collection;

/**
 * One study lesson.
 *
 * A lesson is a hundred question/answer cards - see LessonCard - and is followed
 * by the knowledge check on the same subject.
 *
 * This is a value read from a JSON file, not a database row, and it carries no
 * route binding of its own — a URL uses the slug, and App\Content\CourseContent
 * is what turns that slug back into a lesson.
 *
 * @property-read Collection<int, LessonCard> $items
 */
final class Lesson
{
    /**
     * @param  Collection<int, LessonCard>  $items
     */
    public function __construct(
        public readonly string $course,
        public readonly int $number,
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $summary,
        public readonly Collection $items,
    ) {}

    public function itemCount(): int
    {
        return $this->items->count();
    }

    /**
     * "Lesson 3 of 10" - the lesson list shows this so a learner can see how far
     * through the course they are.
     */
    public function displayTitle(): string
    {
        return "Lesson {$this->number}: {$this->title}";
    }
}
