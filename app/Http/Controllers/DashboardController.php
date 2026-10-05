<?php

namespace App\Http\Controllers;

use App\Content\CourseContent;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\LoginHistory;
use App\Models\Purchase;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CourseContent $content) {}

    /**
     * "My account": everything the signed-in student owns, with a way into the
     * learning area for each course their purchase unlocks.
     */
    public function index(Request $request): View|RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $purchases = Purchase::with('course')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        $paid = $purchases->filter(fn (Purchase $purchase) => $purchase->isPaid());

        // Only offer the learning area for courses that actually have lessons
        // or papers loaded, so a buyer is never sent to an empty page.
        $learnable = $paid
            ->map(fn (Purchase $purchase) => $purchase->course)
            ->filter(fn (?Course $course) => $course && $this->content->hasContent($course->slug))
            ->values();

        $loginHistory = LoginHistory::forStudent($student->id)
            ->latest('login_at')
            ->limit(10)
            ->get();

        return view('website.pages.dashboard', [
            'purchases' => $purchases,
            'paidPurchases' => $paid,
            'pendingPurchases' => $purchases->reject(fn (Purchase $purchase) => $purchase->isPaid()),
            'learnableCourses' => $learnable,
            'lessonProgress' => $this->lessonProgressFor($student, $learnable),
            'highlightCourse' => $this->highlightCourse($request, $learnable),
            /*
             | Courses this account has started to buy but not finished. One
             | entry per course, not per attempt: somebody who clicked pay
             | three times has one thing to do, not three, and showing three
             | rows reads as three separate problems.
             */
            'unfinishedCourses' => $this->unfinishedCourses($student, $purchases),
            'loginHistory' => $loginHistory,
        ]);
    }

    /**
     * The course the success page asked us to mark as new, if this learner
     * really owns it.
     *
     * The slug arrives in the session rather than the query string, so it
     * cannot be a way to make the page claim something untrue. It is matched
     | against the learnable courses the account page has just built, which
     * means the "New" badge and its Start learning button can only ever appear
     * on a course whose paid purchase this request has already established.
     */
    private function highlightCourse(Request $request, Collection $learnable): ?Course
    {
        $slug = $request->session()->get('highlight_course');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        // One-shot: the badge is for the arrival, not a permanent fixture.
        $request->session()->forget('highlight_course');

        return $learnable->first(fn (Course $course) => $course->slug === $slug);
    }

    /**
     * Active courses this learner has a purchase against, whatever its
     * status, that they do not have a paid purchase for.
     *
     * A row that is merely pending is not a failure and needs no explaining;
     * what it needs is a way to finish. So the block is a to-do list with one
     * button per course, and the button goes to checkout.start - which knows
     * whether they still need an account check or a payment.
     *
     * @param  Collection<int, Purchase>  $purchases
     * @return Collection<int, Course>
     */
    private function unfinishedCourses(Student $student, Collection $purchases): Collection
    {
        return $purchases
            ->map(fn (Purchase $purchase) => $purchase->course)
            ->filter()
            ->unique('id')
            ->reject(fn (Course $course) => $student->hasPurchased($course))
            ->filter(fn (Course $course) => $course->is_active)
            ->values();
    }

    /**
     * How many lessons each course this learner owns has read, so the account
     * page can show progress without a query per course.
     *
     * The lesson total comes from the content files and the count read comes
     * from one row per lesson this learner finished, so nothing here needs the
     * lesson itself to be a database row.
     *
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, array{done: int, total: int}>
     */
    private function lessonProgressFor(Student $student, Collection $courses): Collection
    {
        return $courses->mapWithKeys(fn (Course $course) => [
            // Keyed by slug rather than id, because that is what the content
            // files and the progress rows both use to name a course's lessons.
            $course->slug => [
                'done' => LessonProgress::readSlugsFor($student, $course->slug)
                    ->intersect($this->content->lessons($course->slug)->pluck('slug'))
                    ->count(),
                'total' => $this->content->lessons($course->slug)->count(),
            ],
        ]);
    }
}
