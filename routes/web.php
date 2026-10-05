<?php

use App\Http\Controllers\AccountCenterController;
use App\Http\Controllers\AssessmentMailController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactMailController;
use App\Http\Controllers\CourseCatalogController;
use App\Http\Controllers\CourseLearnController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollMailController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TutorMailController;
use Illuminate\Support\Facades\Route;

Route::post('/enroll/send', [EnrollMailController::class, 'sendMail'])
    ->middleware('throttle:3,1')
    ->name('enroll.send');

Route::post('/assessment/send', [AssessmentMailController::class, 'sendMail'])
    ->middleware('throttle:3,1')
    ->name('assessment.send');

Route::post('/contact/send', [ContactMailController::class, 'sendMail'])
    ->middleware('throttle:3,1')
    ->name('contact.send');

Route::post('/tutor/send', [TutorMailController::class, 'sendMail'])
    ->middleware('throttle:3,1')
    ->name('tutor.send');

/*
|--------------------------------------------------------------------------
| Paid Courses: Stripe Checkout
|--------------------------------------------------------------------------
|
| The webhook is the only place a payment is trusted. It is exempt from CSRF
| (Stripe cannot send a token) and authenticates itself with a verified
| Stripe-Signature header instead.
|
*/

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| The project shipped without any sign-in flow. Purchases are attached to a
| real user account so access can never hinge on a URL parameter.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    // Password reset (forgot password)
    Route::get('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'store'])
        ->middleware('throttle:2,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Confirming an email address
|--------------------------------------------------------------------------
|
| Deliberately outside the `guest` middleware group. A correct password on an
| unverified account does not sign anybody in, and registration does not
| either, so somebody who has only a mailed code is a guest until they redeem
| it.
|
| Verification is by code, so there is no signed URL and nothing to put behind
| `signed`. What replaces it is the throttle below plus the attempt counter in
| the controller: a code is guessable in a way a signature is not, so the
| defence moves from "cannot be forged" to "cannot be guessed quickly".
|
| The redeem form is throttled per IP, and the resend form even harder, since
| each hit costs a real email.
|
| Each page asks for one thing. The code page asks for a code and takes the
| address from the session; the resend page asks for an address. They are
| linked rather than stacked, which is the shape Laravel's own password reset
| uses.
|
*/

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
    ->name('verification.notice');

Route::post('/email/verify', [EmailVerificationController::class, 'confirm'])
    ->middleware('throttle:10,1')
    ->name('verification.verify');

/*
| The resend page is a page of its own, not a second form under the code
| form. Two email boxes on one screen reads as two competing forms; here the
| code page asks for a code and this one asks for an address.
*/

Route::get('/email/resend', [EmailVerificationController::class, 'resendForm'])
    ->name('verification.resend.form');

Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:2,1')
    ->name('verification.resend');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Course Detail
|--------------------------------------------------------------------------
|
| Public: anyone may read what a course contains. The course is resolved
| from the slug on the server; the price shown is the stored price, never a
| value posted by the browser.
|
*/

/*
| The catalogue has a page of its own. Linking to the homepage and asking a
| visitor to scroll to an anchor buried under the hero, the gallery and the
| testimonials made them hunt for the thing they were sent to buy. `/courses`
| is a short list and nothing else.
|
| Registered before `/courses/{course}` so the literal segment always wins.
*/
Route::get('/courses', [CourseCatalogController::class, 'index'])->name('courses.index');

Route::get('/courses/{course}', [CheckoutController::class, 'show'])->name('courses.show');

/*
|--------------------------------------------------------------------------
| Purchasing
|--------------------------------------------------------------------------
*/

/*
| New entry point: remembers the chosen course and routes the visitor to
| the right step (register, verify, review, or dashboard).
| Public, rate limited, never creates a payment.
*/
Route::get('/buy/{course}', [CheckoutController::class, 'start'])
    ->middleware('throttle:30,1')
    ->name('checkout.start');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-account', [DashboardController::class, 'index'])->name('dashboard');

    // Account Center (security, emails, sessions)
    Route::get('/my-account/security', [AccountCenterController::class, 'index'])->name('account.center');
    Route::put('/my-account/security/password', [AccountCenterController::class, 'updatePassword'])
        ->name('account.password.update');
    Route::delete('/my-account/security/sessions/{loginHistory}', [AccountCenterController::class, 'revokeSession'])
        ->name('account.sessions.revoke');

    // Email management
    Route::post('/my-account/emails', [AccountCenterController::class, 'addEmail'])->name('account.emails.add');
    Route::post('/my-account/emails/{userEmail}/verify', [AccountCenterController::class, 'resendVerification'])
        ->name('account.emails.resend');
    Route::get('/my-account/emails/verify/{token}', [AccountCenterController::class, 'verifyEmail'])
        ->name('account.emails.verify');
    Route::put('/my-account/emails/{userEmail}/primary', [AccountCenterController::class, 'setPrimary'])
        ->name('account.emails.primary');
    Route::delete('/my-account/emails/{userEmail}', [AccountCenterController::class, 'removeEmail'])
        ->name('account.emails.remove');

    /*
    | Review page: shows the course, price, features, consent tick box,
    | and a Pay button that POSTs to checkout.store.
    */
    Route::get('/checkout/{course}/review', [CheckoutController::class, 'review'])
        ->name('checkout.review');

    Route::post('/checkout/{course}', [CheckoutController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('checkout.store');

    /*
     | The success page re-checks Stripe on a timer, so it is the one page in
     | the flow that is loaded repeatedly by design. The throttle is generous
     | enough for the refreshes it makes (ten of them, every three seconds) and
     | still stops the URL being used to hammer Stripe from a script.
     */
    Route::get('/checkout/success', [CheckoutController::class, 'success'])
        ->middleware('throttle:30,1')
        ->name('checkout.success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');

    /*
     | The learning area: read a lesson one card at a time, then sit the
     | papers. This is the only way into the paid material - nothing is
     | served as a file, so a purchase unlocks reading and testing online
     | and buys only the course it belongs to.
     |
     | A lesson and a paper are read from the JSON content files rather than
     | from database tables, so `{lesson}` and `{quiz}` are turned back into
     | objects by the bindings in AppServiceProvider rather than by Laravel's
     | model binding. Those bindings scope the lookup to the course in the URL,
     | which is what stops a buyer of the £99 course reaching the £49 pack's
     | papers by putting their slug in the URL.
     */
    Route::prefix('/my-account/courses/{course}')
        ->middleware('purchased:course')
        ->name('learn.')
        ->group(function () {
            Route::get('/', [CourseLearnController::class, 'index'])->name('index');

            Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
            Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete'])
                ->middleware('throttle:30,1')
                ->name('lessons.complete');

            // Undo the above. A POST rather than a DELETE because this is a plain
            // form with no script behind it, and the reader works with scripting
            // off as well as on - a real DELETE would need a method-spoofing field
            // that is only ever submitted by JavaScript.
            Route::post('/lessons/{lesson}/unread', [LessonController::class, 'unread'])
                ->middleware('throttle:30,1')
                ->name('lessons.unread');

            Route::get('/quizzes/{quiz}', [QuizController::class, 'play'])->name('quizzes.play');

            // The only write a paper takes. Answers are held in the browser
            // while the learner moves around and arrive together here, so this
            // is the one request that carries their work.
            Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit'])
                ->middleware('throttle:30,1')
                ->name('quizzes.submit');

            Route::get('/quizzes/{quiz}/attempts/{attempt}', [QuizController::class, 'result'])
                ->name('quizzes.result');
        });
});

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded from bootstrap/app.php and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

/*
| Redirect old misspelled URL to correct one.
| Avoids 404s from old bookmarks or cached search results.
*/
Route::redirect('/free-assesment', '/free-assessment');

/*
| Static pages - consolidated into a single parameterized route.
|
| The slug is validated against a whitelist in StaticPageController,
| preventing arbitrary view rendering.
*/
Route::get('/{slug}', [StaticPageController::class, 'show'])
    ->where('slug', implode('|', array_keys(\App\Http\Controllers\StaticPageController::PAGES)))
    ->name('static');
