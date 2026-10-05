<?php

namespace App\Services;

use App\Content\CourseContent;
use App\Content\Lesson;
use App\Content\Quiz;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The learner's view of a course they own.
 *
 * Assembles the course hub, the lesson reader and the paper list in one place
 * so the controllers stay thin and the "what has this person done" rules are
 * stated once.
 *
 * The material itself comes from the JSON content files; what is added here is
 * the learner's own record against it - which lessons they have read, how they
 * did on each paper.
 */
class LearningService
{
    public function __construct(
        private readonly CourseContent $content,
        private readonly QuizAttemptService $attempts,
    ) {}

    /**
     * Everything the course hub needs: the lessons, the papers grouped by kind,
     * and how far through each the learner is.
     *
     * @return array{
     *     lessons: Collection<int, Lesson>,
     *     read: Collection<int, string>,
     *     groups: array<string, array{label: string, quizzes: Collection<int, Quiz>}>,
     *     progress: array{lessons_total: int, lessons_done: int, quizzes_total: int, quizzes_sat: int},
     *     best: Collection<string, float>
     * }
     */
    public function courseOverview(Student $student, Course $course): array
    {
        $lessons = $this->content->lessons($course->slug);
        $read = LessonProgress::readSlugsFor($student, $course->slug);

        $quizzes = $this->content->quizzes($course->slug);
        $best = $this->attempts->bestScoresBySlug($student, $course->slug);

        $groups = [];

        foreach ($this->content->quizzesByKind($course->slug) as $kind => $papers) {
            $groups[$kind] = [
                'label' => Quiz::kindLabelFor($kind),
                'quizzes' => $papers,
            ];
        }

        return [
            'lessons' => $lessons,
            'read' => $read,
            'groups' => $groups,
            'progress' => [
                'lessons_total' => $lessons->count(),
                'lessons_done' => $lessons->whereIn('slug', $read->all())->count(),
                'quizzes_total' => $quizzes->count(),
                'quizzes_sat' => $best->count(),
            ],
            'best' => $best,
        ];
    }

    /**
     * The lessons either side of the current one, for the prev/next footer.
     *
     * @return array{previous: Lesson|null, next: Lesson|null}
     */
    public function lessonNeighbours(Course $course, Lesson $lesson): array
    {
        return [
            'previous' => $this->content->lessons($course->slug)
                ->first(fn (Lesson $other) => $other->number < $lesson->number),
            'next' => $this->content->lessons($course->slug)
                ->first(fn (Lesson $other) => $other->number > $lesson->number),
        ];
    }

    /**
     * When a learner marked a lesson as finished, or null if they have not.
     */
    public function completedAt(Student $student, Course $course, Lesson $lesson): ?Carbon
    {
        return LessonProgress::query()
            ->for($student)
            ->where('course_slug', $course->slug)
            ->where('lesson_slug', $lesson->slug)
            ->value('completed_at');
    }
}
