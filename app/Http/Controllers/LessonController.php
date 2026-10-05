<?php

namespace App\Http\Controllers;

use App\Content\CourseContent;
use App\Content\Lesson;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Services\LearningService;
use App\Services\QuizAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Reading a lesson.
 *
 * A lesson is 100 study cards. They are paged rather than dumped in one long
 * scroll, because a hundred questions and answers on a single page is
 * unreadable on a phone.
 */
class LessonController extends Controller
{
    /** Study cards per page. */
    private const PER_PAGE = 20;

    public function __construct(
        private readonly CourseContent $content,
        private readonly LearningService $learning,
        private readonly QuizAttemptService $attempts,
    ) {}

    public function show(Request $request, Course $course, Lesson $lesson): View
    {
        $user = $request->user();

        // A knowledge check's number matches the lesson it revises, which is
        // what ties the two together.
        $check = $this->content->knowledgeCheck($course->slug, $lesson->number);

        return view('website.pages.learn.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'items' => $this->cards($request, $lesson),
            'neighbours' => $this->learning->lessonNeighbours($course, $lesson),
            'completedAt' => $this->learning->completedAt($user, $course, $lesson),
            'check' => $check,
            'checkScore' => $check ? $this->attempts->bestPercentage($user, $check) : null,
            'lessons' => $this->content->lessons($course->slug),
        ]);
    }

    /**
     * Record that the learner reached the end of a lesson.
     *
     * This is the learner's own note to themselves, not a gate: it does not
     * unlock anything, and re-reading a lesson does not need it cleared again.
     * The unique (student, course, lesson) index is what stops a double-click
     * creating two rows.
     */
    public function complete(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        LessonProgress::firstOrCreate(
            [
                'student_id' => $request->user()->id,
                'course_slug' => $course->slug,
                'lesson_slug' => $lesson->slug,
            ],
            ['completed_at' => now()]
        );

        return redirect()
            ->route('learn.lessons.show', [$course, $lesson->slug])
            ->with('status', "Lesson {$lesson->number} marked as complete.");
    }

    /**
     * Take back the note that this lesson was finished.
     *
     * Completion gates nothing, so un-reading it is safe: it drops the learner
     * back to having this lesson outstanding on their course progress and
     * changes no access. That is the point - someone revising for a test wants
     * the lesson back in their list, not locked away.
     *
     * The delete is scoped to the signed-in learner as well as to the course and
     * lesson the URL names. The lesson binding is already scoped to the course in
     * the URL, so this cannot reach another course's lesson, but a delete that
     * trusted the URL alone would be one careless change away from a buyer
     * clearing somebody else's progress.
     *
     * Deleting nothing is not an error: the button can be double-clicked and a
     * replayed request lands the same way, which is the same reasoning as the
     * unique index keeping a double-clicked "mark as read" to one row.
     */
    public function unread(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        LessonProgress::query()
            ->for($request->user())
            ->where('course_slug', $course->slug)
            ->where('lesson_slug', $lesson->slug)
            ->delete();

        return redirect()
            ->route('learn.lessons.show', [$course, $lesson->slug])
            ->with('status', "Lesson {$lesson->number} marked as unread.");
    }

    /**
     * One page of a lesson's study cards.
     *
     * The cards are a plain array rather than database rows, so the pager is
     * built by hand. It is a real paginator all the same, which is what keeps
     * the Bootstrap page links working, and a page number past the end of the
     * lesson shows the last page instead of an empty screen.
     */
    private function cards(Request $request, Lesson $lesson): LengthAwarePaginator
    {
        $lastPage = max(1, (int) ceil($lesson->itemCount() / self::PER_PAGE));

        $page = min(max(1, $request->integer('page', 1)), $lastPage);

        return new LengthAwarePaginator(
            $lesson->items->forPage($page, self::PER_PAGE)->values(),
            $lesson->itemCount(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'pageName' => 'page']
        );
    }
}
