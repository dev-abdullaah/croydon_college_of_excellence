<?php

namespace App\Http\Middleware;

use App\Models\Course;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a completed purchase.
 *
 * Usage:
 *   Route::get('/courses/{course}/read', ...)->middleware('purchased:course');
 *
 * The argument is the *route parameter* to inspect. It may be a bound Course
 * or a course slug. The middleware never decides access on its own - it
 * delegates to the user's purchase record, so there is only one definition
 * of "has paid" in the application.
 */
class EnsureCoursePurchased
{
    public function handle(Request $request, Closure $next, string $parameter = 'course'): Response
    {
        $course = $this->resolveCourse($request, $parameter);

        abort_if(! $course, 404);

        if (! Auth::check()) {
            // Guests are sent to sign in and returned to this URL afterwards.
            return redirect()->guest(route('login'));
        }

        // A completed purchase is the only thing that opens the material, and
        // there is no setting that relaxes it. 403 rather than 404: the
        // resource exists, this visitor simply is not entitled to it.
        abort_unless($course->hasAccessFor($request->user()), 403);

        return $next($request);
    }

    protected function resolveCourse(Request $request, string $parameter): ?Course
    {
        $routeParam = $request->route($parameter);

        if ($routeParam instanceof Course) {
            return $routeParam->is_active ? $routeParam : null;
        }

        $slug = $routeParam ?: $request->route('slug');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Course::where('slug', $slug)->where('is_active', true)->first();
    }
}
