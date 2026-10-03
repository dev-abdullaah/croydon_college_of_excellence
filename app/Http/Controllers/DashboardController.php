<?php

namespace App\Http\Controllers;

use App\Content\CourseContent;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\LoginHistory;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly CourseContent $content) {}

    /**
     * "My account": everything the signed-in user owns, with a way into the
     * learning area for each course their purchase unlocks.
     */
    public function index(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchases = Purchase::with('course')
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

        $loginHistory = LoginHistory::forUser($user->id)
            ->latest('login_at')
            ->limit(10)
            ->get();

        return view('website.pages.dashboard', [
            'purchases' => $purchases,
            'paidPurchases' => $paid,
            'pendingPurchases' => $purchases->reject(fn (Purchase $purchase) => $purchase->isPaid()),
            'learnableCourses' => $learnable,
            'lessonProgress' => $this->lessonProgressFor($user, $learnable),
            'highlightCourse' => $this->highlightCourse($request, $learnable),
            /*
             | Courses this account has started to buy but not finished. One
             | entry per course, not per attempt: somebody who clicked pay
             | three times has one thing to do, not three, and showing three
             | rows reads as three separate problems.
             */
            'unfinishedCourses' => $this->unfinishedCourses($user, $purchases),
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
    private function unfinishedCourses(User $user, Collection $purchases): Collection
    {
        return $purchases
            ->map(fn (Purchase $purchase) => $purchase->course)
            ->filter()
            ->unique('id')
            ->reject(fn (Course $course) => $user->hasPurchased($course))
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

    /**
     * Show the password change form.
     */
    public function showPasswordForm(Request $request): View
    {
        return view('website.pages.dashboard-password');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Prevent reusing the current password
        if (Hash::check($request->string('password'), $user->password)) {
            return back()->withErrors([
                'password' => 'The new password must be different from your current password.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        $user->password = Hash::make($request->string('password'));
        $user->save();

        return back()->with('status', 'password-changed');
    }

    /**
     * Show the email change form.
     */
    public function showEmailForm(Request $request): View
    {
        return view('website.pages.dashboard-email', [
            'pendingEmail' => $request->user()->new_email,
        ]);
    }

    /**
     * Request an email change - sends verification to the new address.
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Rate limit: 3 requests per hour per user
        $limiter = RateLimiter::for('email-change', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perHour(3)->by($request->user()->id);
        });
        $key = 'email-change:' . $user->id;

        if ($limiter->tooManyAttempts($key)) {
            $seconds = $limiter->availableIn($key);
            return back()->withErrors([
                'email' => 'Too many email change requests. Please try again in ' . gmdate('i:s', $seconds) . '.',
            ]);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'email' => ['required', 'email', 'max:255', 'different:email', 'unique:users,email'],
        ]);

        $limiter->hit($key);

        $user->requestEmailChange($request->string('email'));

        return back()->with('status', 'email-change-sent');
    }

    /**
     * Verify the email change token from the email link.
     */
    public function verifyEmailChange(Request $request, string $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPendingEmailChange()) {
            return redirect()->route('dashboard.email')
                ->withErrors(['email' => 'No pending email change request.']);
        }

        if ($user->verifyEmailChange($token)) {
            return redirect()->route('dashboard.email')
                ->with('status', 'email-changed');
        }

        return redirect()->route('dashboard.email')
            ->withErrors(['email' => 'Invalid or expired verification link.']);
    }
}
