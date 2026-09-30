<?php

use App\Http\Middleware\EnsureCoursePurchased;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'purchased' => EnsureCoursePurchased::class,
        ]);

        /*
         | Rate limit the API. This used to be part of the "api" middleware
         | group in the HTTP kernel; from Laravel 11 on it is opt-in, so it
         | is asked for explicitly here or the group arrives unthrottled.
         */
        $middleware->throttleApi();

        /*
         | Stripe cannot send a CSRF token. Requests to the webhook path are
         | authenticated instead by verifying Stripe's `Stripe-Signature`
         | header against the endpoint's signing secret, which is strictly
         | stronger, so it is exempt here.
         */
        $middleware->preventRequestForgery(except: [
            'stripe/webhook',
        ]);

        /*
         | A password is stored exactly as it was typed. Trimming these would
         | silently change a password the learner believes they chose, so
         | leading and trailing spaces are left alone.
         */
        $middleware->trimStrings(except: [
            'current_password',
            'password',
            'password_confirmation',
        ]);

        /*
         | Guests are sent to sign in and returned to the URL they wanted
         | afterwards; an already signed-in visitor who reaches /login or
         | /register is sent to their account instead.
         */
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/my-account');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
