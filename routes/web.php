<?php

use App\Http\Controllers\AssesmentMailController;
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
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TutorMailController;
use Illuminate\Support\Facades\Route;

Route::post('/enroll/send', [EnrollMailController::class, 'sendMail'])->name('enroll.send');

Route::post('/assesment/send', [AssesmentMailController::class, 'sendMail'])->name('assesment.send');

Route::post('/contact/send', [ContactMailController::class, 'sendMail'])->name('contact.send');

Route::post('/tutor/send', [TutorMailController::class, 'sendMail'])->name('tutor.send');

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

Route::get('/director-message', function () {
    return view('website.pages.directors_msg');
});

Route::get('/our-team', function () {
    return view('website.pages.our_team');
});

Route::get('/our-mission', function () {
    return view('website.pages.our_mission');
});

Route::get('/our-policy', function () {
    return view('website.pages.our_policy');
});

Route::get('/become-tutor', function () {
    return view('website.pages.become_tutor');
});

Route::get('/courses-regular', function () {
    return view('website.pages.courses_regular');
});

Route::get('/courses-send', function () {
    return view('website.pages.courses_send');
});

Route::get('/contact-us', function () {
    return view('website.pages.contact_us');
});

Route::get('/gallery', function () {
    return view('website.pages.gallery');
});

Route::get('/enroll-now', function () {
    return view('website.pages.enroll_now');
});

Route::get('/free-assesment', function () {
    return view('website.pages.free_assesment');
});

Route::get('/regular-english', function () {
    return view('website.pages.courses_regular.regular_english');
});

Route::get('/regular-math', function () {
    return view('website.pages.courses_regular.regular_math');
});

Route::get('/regular-science', function () {
    return view('website.pages.courses_regular.regular_science');
});

Route::get('/regular-exam', function () {
    return view('website.pages.courses_regular.regular_exam');
});

Route::get('/regular-sat', function () {
    return view('website.pages.courses_regular.regular_sat');
});

Route::get('/regular-skills', function () {
    return view('website.pages.courses_regular.regular_skills');
});

Route::get('/regular-esol', function () {
    return view('website.pages.courses_regular.regular_esol');
});

Route::get('/regular-ielts', function () {
    return view('website.pages.courses_regular.regular_ielts');
});

Route::get('/regular-ukvi', function () {
    return view('website.pages.courses_regular.regular_ukvi');
});

Route::get('/regular-uk-life', function () {
    return view('website.pages.courses_regular.regular_uk_life');
});

Route::get('/send-english', function () {
    return view('website.pages.courses_send.send_english');
});

Route::get('/send-math', function () {
    return view('website.pages.courses_send.send_math');
});

Route::get('/send-science', function () {
    return view('website.pages.courses_send.send_science');
});

Route::get('/send-exam', function () {
    return view('website.pages.courses_send.send_exam');
});

Route::get('/send-sat', function () {
    return view('website.pages.courses_send.send_sat');
});

Route::get('/send-skills', function () {
    return view('website.pages.courses_send.send_skills');
});

Route::get('/send-esol', function () {
    return view('website.pages.courses_send.send_esol');
});

Route::get('/send-literacy', function () {
    return view('website.pages.courses_send.send_literacy');
});

Route::get('/send-humanities', function () {
    return view('website.pages.courses_send.send_humanities');
});

Route::get('/send-business', function () {
    return view('website.pages.courses_send.send_business');
});

Route::get('/send-ict', function () {
    return view('website.pages.courses_send.send_ict');
});

Route::get('/send-life-skills', function () {
    return view('website.pages.courses_send.send_life_skills');
});

Route::get('/send-music', function () {
    return view('website.pages.courses_send.send_music');
});
