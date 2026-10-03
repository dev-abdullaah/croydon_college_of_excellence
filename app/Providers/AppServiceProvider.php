<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulLogin;
use App\Content\CourseContent;
use App\Services\CatalogService;
use App\Services\PurchaseService;
use App\Services\StripeService;
use Closure;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One Stripe client per request, reused across the purchase services.
        $this->app->singleton(StripeService::class);

        // The course material is a few hundred kilobytes of JSON, read once and
        // held for the request. A page that needs a lesson and a paper list
        // reads the files once rather than once per lookup.
        $this->app->singleton(CourseContent::class);

        $this->app->singleton(CatalogService::class);

        $this->app->singleton(PurchaseService::class, function ($app) {
            return new PurchaseService($app->make(StripeService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */


    /**
     * Paginate with the styles the site actually loads.
     *
     * Laravel's default paginator view is written for Tailwind, but this site
     * loads Bootstrap 5. Left alone, `$paginator->links()` emits utility
     * classes the stylesheet has never heard of, so the page buttons come out
     * unstyled and the Previous/Next arrows inherit the heading font at full
     * size. Pointing the paginator at the Bootstrap 5 view fixes the lesson
     * reader and anything else that paginates.
     */
    protected function useBootstrapPagination(): void
    {
        Paginator::useBootstrapFive();
    }

    /**
     * Cap how hard the API may be driven by a single caller.
     */
    protected function limitApiRequests(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        Event::listen(Login::class, LogSuccessfulLogin::class);

        \Illuminate\Support\Facades\Route::macro('lesson', function (string $uri, ?string $name = null, ?string $default = null) {
            return $this->bind($uri, $name, 'lesson_id', function (\App\Content\CourseContent $content, string $slug, string $id) {
                $lesson = $content->findLesson($slug, $id);
                abort_if($lesson === null, 404, "There is no lesson \"{$id}\" in this course.");
                return $lesson;
            }, $default);
        });

        \Illuminate\Support\Facades\Route::macro('quiz', function (string $uri, ?string $name = null, ?string $default = null) {
            return $this->bind($uri, $name, 'quiz_id', function (\App\Content\CourseContent $content, string $slug, string $id) {
                $quiz = $content->findQuiz($slug, $id);
                abort_if($quiz === null, 404, "There is no paper or mock test \"{$id}\" in this course.");
                return $quiz;
            }, $default);
        });

        $this->bootBrandEmail();
    }

    private function bootBrandEmail(): void
    {
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Verify your email address - Croydon College of Excellence')
                ->greeting('Confirm your email address')
                ->line('Please click the button below to verify your email address.')
                ->action('Verify Email Address', $url)
                ->line('If you did not create an account, you can ignore this message.')
                ->salutation(new \Illuminate\Support\HtmlString('Regards,<br>Croydon College of Excellence'));
        });

        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($notifiable, $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Reset your password - Croydon College of Excellence')
                ->greeting('Reset your password')
                ->line('You are receiving this email because we received a password reset request for your account.')
                ->action('Reset Password', $url)
                ->line('This password reset link will expire in ' . config('auth.passwords.' . config('auth.defaults.passwords') . '.expire') . ' minutes.')
                ->line('If you did not request a password reset, no further action is required.')
                ->salutation(new \Illuminate\Support\HtmlString('Regards,<br>Croydon College of Excellence'));
        });
    }
}
