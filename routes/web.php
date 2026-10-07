<?php

use App\Http\Controllers\AccountCenterController;
use App\Http\Controllers\AssessmentMailController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
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
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\Backend\AdminAdmissionController;
use App\Http\Controllers\Backend\AdminAnalyticsController;
use App\Http\Controllers\Backend\AdminAuditLogController;
use App\Http\Controllers\Backend\AdminCertificateController;
use App\Http\Controllers\Backend\AdminCouponController;
use App\Http\Controllers\Backend\AdminCurriculumController;
use App\Http\Controllers\Backend\AdminCourseController;
use App\Http\Controllers\Backend\AdminDashboardController;
use App\Http\Controllers\Backend\AdminPurchaseController;
use App\Http\Controllers\Backend\AdminStudentController;
use App\Http\Controllers\Backend\AdminSubmissionController;
use App\Http\Controllers\Backend\AdminUserController;
use App\Http\Controllers\Backend\Auth\AdminLoginController;
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
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:2,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
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
    Route::post('/my-account/emails/{studentEmail}/verify', [AccountCenterController::class, 'resendVerification'])
        ->name('account.emails.resend');
    Route::get('/my-account/emails/verify/{token}', [AccountCenterController::class, 'verifyEmail'])
        ->name('account.emails.verify');
    Route::put('/my-account/emails/{studentEmail}/primary', [AccountCenterController::class, 'setPrimary'])
        ->name('account.emails.primary');
    Route::delete('/my-account/emails/{studentEmail}', [AccountCenterController::class, 'removeEmail'])
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
|--------------------------------------------------------------------------
| Backend / Admin Panel Routes (Isolated with "admin" guard and "users" table)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest Admin Routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
    });

    // Authenticated Admin Routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // Students Management
        Route::get('/students/export', [AdminStudentController::class, 'export'])->name('students.export');
        Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
        Route::match(['post', 'patch'], '/students/{student}/toggle-status', [AdminStudentController::class, 'toggleStatus'])->name('students.toggle-status');
        Route::match(['post', 'put'], '/students/{student}/reset-password', [AdminStudentController::class, 'resetPassword'])->name('students.reset-password');
        Route::post('/students/{student}/enroll', [AdminStudentController::class, 'enroll'])->name('students.enroll');
        Route::get('/students/{student}/impersonate', [AdminStudentController::class, 'impersonate'])->name('students.impersonate');

        // Course Catalog Management
        Route::get('/courses', [AdminCourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}/edit', [AdminCourseController::class, 'edit'])->name('courses.edit');
        Route::match(['put', 'patch', 'post'], '/courses/{course}', [AdminCourseController::class, 'update'])->name('courses.update');
        Route::match(['post', 'patch'], '/courses/{course}/toggle', [AdminCourseController::class, 'toggleStatus'])->name('courses.toggle');

        // Static Curriculum & Question Bank Inspector
        Route::get('/curriculum', [AdminCurriculumController::class, 'index'])->name('curriculum.index');
        Route::get('/curriculum/{courseSlug}/lessons', [AdminCurriculumController::class, 'lessons'])->name('curriculum.lessons');
        Route::get('/curriculum/{courseSlug}/quizzes', [AdminCurriculumController::class, 'quizzes'])->name('curriculum.quizzes');

        // Course Admissions & Learner Access
        Route::get('/admissions/export', [AdminAdmissionController::class, 'export'])->name('admissions.export');
        Route::get('/admissions', [AdminAdmissionController::class, 'index'])->name('admissions.index');
        Route::post('/admissions/manual-admit', [AdminAdmissionController::class, 'manualAdmit'])->name('admissions.manual-admit');
        Route::get('/admissions/{admission}', [AdminAdmissionController::class, 'show'])->name('admissions.show');
        Route::post('/admissions/{admission}/approve', [AdminAdmissionController::class, 'approve'])->name('admissions.approve');
        Route::post('/admissions/{admission}/revoke', [AdminAdmissionController::class, 'revoke'])->name('admissions.revoke');
        Route::post('/admissions/{admission}/reject', [AdminAdmissionController::class, 'reject'])->name('admissions.reject');
        Route::patch('/admissions/{admission}/notes', [AdminAdmissionController::class, 'updateNotes'])->name('admissions.notes');

        // Certificate & Credential Management
        Route::get('/certificates/export', [AdminCertificateController::class, 'export'])->name('certificates.export');
        Route::get('/certificates', [AdminCertificateController::class, 'index'])->name('certificates.index');
        Route::post('/certificates', [AdminCertificateController::class, 'store'])->name('certificates.store');
        Route::post('/certificates/{certificate}/revoke', [AdminCertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::post('/certificates/{certificate}/restore', [AdminCertificateController::class, 'restore'])->name('certificates.restore');

        // Legacy Purchases
        Route::get('/purchases/export', [AdminPurchaseController::class, 'export'])->name('purchases.export');
        Route::get('/purchases', [AdminPurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/{purchase}', [AdminPurchaseController::class, 'show'])->name('purchases.show');
        Route::match(['post', 'patch'], '/purchases/{purchase}/status', [AdminPurchaseController::class, 'updateStatus'])->name('purchases.status');

        // Promotional Coupons & Vouchers
        Route::resource('coupons', AdminCouponController::class)->except(['show']);
        Route::post('/coupons/{coupon}/toggle', [AdminCouponController::class, 'toggleStatus'])->name('coupons.toggle');

        // Inquiries & Contact Submissions
        Route::get('/submissions/export', [AdminSubmissionController::class, 'export'])->name('submissions.export');
        Route::get('/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/{submission}', [AdminSubmissionController::class, 'show'])->name('submissions.show');
        Route::match(['post', 'patch'], '/submissions/{submission}/read', [AdminSubmissionController::class, 'toggleRead'])->name('submissions.read');
        Route::match(['post', 'put', 'patch'], '/submissions/{submission}/notes', [AdminSubmissionController::class, 'updateNotes'])->name('submissions.notes');
        Route::delete('/submissions/{submission}', [AdminSubmissionController::class, 'destroy'])->name('submissions.destroy');

        // Analytics & Reports
        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');

        // Audit Trail
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit-logs.index');

        // System Users Management
        Route::resource('users', AdminUserController::class)->except(['show']);
    });
});

Route::get('/stop-impersonating', [AdminStudentController::class, 'stopImpersonating'])->name('stop-impersonating');

/*
|--------------------------------------------------------------------------
| Public Certificate Verification Registry
|--------------------------------------------------------------------------
*/
Route::get('/verify', [CertificateVerificationController::class, 'show'])->name('certificates.lookup');
Route::get('/verify/{certificate_number}', [CertificateVerificationController::class, 'show'])->name('certificates.verify');

Route::get('/id-card', [StaticPageController::class, 'show'])->defaults('slug', 'id-card')->name('id-card');
Route::post('/id-card', [StaticPageController::class, 'verifyIdCardPassword'])->name('id-card.verify');

/*
| Static pages - consolidated into a single parameterized route.
|
| The slug is validated against a whitelist in StaticPageController,
| preventing arbitrary view rendering.
*/
Route::get('/{slug}', [StaticPageController::class, 'show'])
    ->where('slug', implode('|', array_keys(StaticPageController::PAGES)))
    ->name('static');
