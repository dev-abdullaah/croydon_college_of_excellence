<?php

namespace App\Http\Controllers;

use App\Content\CourseContent;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CourseContent $content) {}

    /**
     * "My account": everything the signed-in user owns, plus the download
     * links for each document their purchase unlocks.
     */
    public function index(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchases = Purchase::with('course.documents')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $paid = $purchases->filter(fn (Purchase $purchase) => $purchase->isPaid());

        // Only offer the learning area for courses that actually have lessons
        // or papers loaded, so a buyer is never sent to an empty page.
        $learnable = $paid
            ->map(fn (Purchase $purchase) => $purchase->course)
            ->filter(fn (?Course $course) => $course && $this->content->hasContent($course->slug))
            ->values();

        return view('website.pages.dashboard', [
            'purchases' => $purchases,
            'paidPurchases' => $paid,
            'pendingPurchases' => $purchases->reject(fn (Purchase $purchase) => $purchase->isPaid()),
            'learnableCourses' => $learnable,
            'lessonProgress' => $this->lessonProgressFor($user, $learnable),
        ]);
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
    private function lessonProgressFor(User $user, Collection $courses): Collection
    {
        return $courses->mapWithKeys(fn (Course $course) => [
            // Keyed by slug rather than id, because that is what the content
            // files and the progress rows both use to name a course's lessons.
            $course->slug => [
                'done' => LessonProgress::readSlugsFor($user, $course->slug)
                    ->intersect($this->content->lessons($course->slug)->pluck('slug'))
                    ->count(),
                'total' => $this->content->lessons($course->slug)->count(),
            ],
        ]);
    }
}
