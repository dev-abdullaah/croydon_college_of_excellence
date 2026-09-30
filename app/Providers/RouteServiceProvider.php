<?php

namespace App\Providers;

use App\Content\CourseContent;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * This is where users are redirected after logging in, and where the
     * `auth` middleware sends unauthenticated visitors.
     *
     * @var string
     */
    public const HOME = '/my-account';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->bindCourseContent();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Turn `{lesson}` and `{quiz}` in a learning-area URL back into objects.
     *
     * A lesson and a paper are lines in a JSON content file, not database rows,
     * so Laravel's implicit model binding has nothing to look up. These do the
     * same job.
     *
     * The scoping is the part that matters. A slug means nothing outside its own
     * course, so a lookup is always made against the course named in the same
     * URL. Without that, a buyer of the £99 course could reach the £49 pack's
     * papers by putting their slug in the path, and a purchase of one course
     * would quietly unlock another.
     *
     * A slug that is not part of that course is a 404 rather than a 403: there
     * is nothing at that address. This runs during binding substitution, which
     * is before the `purchased` middleware, so it answers "does this exist"
     * without ever asking what the visitor has bought.
     */
    protected function bindCourseContent(): void
    {
        Route::bind('lesson', $this->scopedToCourse(
            'lesson',
            fn (CourseContent $content, string $course, string $slug) => $content->lesson($course, $slug)
        ));

        Route::bind('quiz', $this->scopedToCourse(
            'paper',
            fn (CourseContent $content, string $course, string $slug) => $content->quiz($course, $slug)
        ));
    }

    /**
     * @param  Closure(CourseContent, string, string): ?object  $lookup
     */
    protected function scopedToCourse(string $what, Closure $lookup): Closure
    {
        return function ($value, IlluminateRoute $route) use ($what, $lookup) {
            $course = $route->parameter('course');

            // A route with no course in it has nothing to scope to, so the
            // parameter is left as it arrived rather than guessed at.
            if (! is_string($course) || $course === '') {
                return $value;
            }

            $found = $lookup(app(CourseContent::class), $course, (string) $value);

            abort_if($found === null, 404, "There is no {$what} \"{$value}\" in this course.");

            return $found;
        };
    }
}
