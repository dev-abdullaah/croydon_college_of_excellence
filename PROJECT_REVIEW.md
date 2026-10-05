# 🎓 Croydon College of Excellence — Project Review

> **Reviewed:** 5 October 2026
> **Stack:** Laravel 13 · PHP 8.3 · MySQL · Stripe Checkout · Bootstrap 5
> **Type:** Educational platform with paid course purchasing, lesson reading, and quiz testing

---

## Table of Contents

- [✅ Strengths (Do Not Touch)](#-strengths-do-not-touch)
- [🔧 Work To Be Done](#-work-to-be-done)
  - [Priority 1 — Admin Panel](#-priority-1--admin-panel-detailed-plan)

---

## ✅ Strengths (Do Not Touch)

These areas are solid and should not be refactored or reworked.

### 1. Security Posture

- **HMAC-hashed verification codes** — plain codes are never stored. HMAC is keyed with the app key and scoped per-student ([`Student.php`](file:///var/www/html/croydon_college_of_excellence/app/Models/Student.php)).
- **Session fixation prevention** — session ID regenerated on login, invalidated on logout and failed verification ([`LoginController`](file:///var/www/html/croydon_college_of_excellence/app/Http/Controllers/Auth/LoginController.php)).
- **Stripe webhook signature verification** — validates `Stripe-Signature` header before processing, with idempotent recording via unique `event_id` index ([`StripeWebhookController`](file:///var/www/html/croydon_college_of_excellence/app/Http/Controllers/StripeWebhookController.php)).
- **No price from browser** — amount charged is always read from server-side `Course` model ([`CheckoutController`](file:///var/www/html/croydon_college_of_excellence/app/Http/Controllers/CheckoutController.php)).
- **Brute-force protection** — per-account attempt counting, time-locked lockouts, code expiry, single-use consumption.
- **Generic error messages** — login and verification failures give deliberately vague responses to prevent account enumeration.
- **CSRF on all forms** — every `<form method="POST">` uses `@csrf`.
- **XSS protection** — all `{!! !!}` usages wrap content safely. Consent text uses `Str::markdown()`.
- **Rate limiting** — all public mail form endpoints throttled at `3,1`. Auth endpoints throttled appropriately.
- **Config-driven email** — recipient address uses `config('mail.college_inbox')` backed by env variable.
- **Security headers** — `robots.txt` blocks auth/private routes. External links use `rel="noopener noreferrer"`.

### 2. Payment Integration

- **Idempotent payment recording** — handles Stripe webhook replays gracefully using `updateOrCreate` with unique database indexes ([`PurchaseService`](file:///var/www/html/croydon_college_of_excellence/app/Services/PurchaseService.php)).
- **Concurrent checkout lock** — `Cache::lock()` prevents double-sessions on rapid button clicks.
- **Session reuse** — pending Stripe sessions are reused, checked against Stripe's live status.
- **Payment status never downgrades** — a `paid` purchase cannot be overwritten to `failed` or `pending`.
- **`PaymentsDoctor` artisan command** — diagnostic tool for verifying Stripe configuration health.
- **Terms consent tracking** — `terms_accepted_at` and `terms_version` columns record exactly when and which version was agreed to.

### 3. Architecture

- **Service layer** — business logic lives in dedicated service classes under `app/Services/`, not in controllers.
- **Content separated from database** — lesson/quiz content lives in JSON files (`database/data/`), read once per request via `CourseContent` singleton.
- **Proper Laravel features** — route model binding via slug, middleware for purchase gating, Eloquent scopes (`paid()`, `forCourse()`, `active()`, `ordered()`), casts, `$fillable`.
- **Scoped content binding** — [`AppServiceProvider::bindCourseContent()`](file:///var/www/html/croydon_college_of_excellence/app/Providers/AppServiceProvider.php) scopes lesson/quiz lookups to their parent course, preventing cross-course access.
- **`StaticPageController` whitelist** — static pages use a constant whitelist with regex route constraint, preventing arbitrary view rendering ([`StaticPageController`](file:///var/www/html/croydon_college_of_excellence/app/Http/Controllers/StaticPageController.php)).
- **Single source of truth** — account management (password, email, sessions) centralised in [`AccountCenterController`](file:///var/www/html/croydon_college_of_excellence/app/Http/Controllers/AccountCenterController.php).

### 4. Testing

- **8,267+ lines of feature tests** across 8+ test files covering checkout, verification, paid courses, learning area, content files, and account management.
- **4 unit test files** — [`CourseContentTest`](file:///var/www/html/croydon_college_of_excellence/tests/Unit/CourseContentTest.php), [`CourseTest`](file:///var/www/html/croydon_college_of_excellence/tests/Unit/CourseTest.php), [`PurchaseStatusTest`](file:///var/www/html/croydon_college_of_excellence/tests/Unit/PurchaseStatusTest.php), [`StudentVerificationCodeTest`](file:///var/www/html/croydon_college_of_excellence/tests/Unit/StudentVerificationCodeTest.php).
- **Complete model factories** — all models have factories in [`database/factories/`](file:///var/www/html/croydon_college_of_excellence/database/factories/).
- **`InteractsWithCourseContent` test trait** — shared helpers for consistent course content setup.
- **Edge case coverage** — concurrent webhooks, session reuse, abandoned checkouts, double-submission.

### 5. Code Documentation

- Security decisions are explained inline with clear reasoning throughout the codebase.
- [`routes/web.php`](file:///var/www/html/croydon_college_of_excellence/routes/web.php) comments explain *why* design decisions were made, not just what the code does.

### 6. Student Flow

- **Unverified account re-registration** — students who abandoned registration can re-register with the same email.
- **Intended course preservation** — the course a student was trying to buy survives the registration → verification → login flow.
- **Full password reset flow** — forgot/reset password with branded email notifications.
- **Multi-email management** — add, verify, set primary, and remove secondary email addresses.
- **Dedicated course catalog** — `/courses` page with its own controller.

---

## 🔧 Work To Be Done

---

### 🔴 Priority 1 — Admin Panel (Detailed Plan)

> [!CAUTION]
> **DO NOT start working on the admin panel automatically.** Wait for explicit user approval before writing any code for this feature.

Build a custom admin panel (no third-party packages like Filament or Nova) using the existing Bootstrap 5 stack and Laravel conventions already established in the project.

> **Terminology Note:** Throughout the admin panel, "users" refers to **administrators** (staff who log into the admin panel). The people who buy courses are **students** (stored in the `students` table). This clear separation avoids confusion between the two distinct roles.

---

#### Step 1: Database — Add `is_admin` column and `contact_submissions` table

**Migration 1 — `add_is_admin_to_students_table`:**

| Column | Type | Default | Notes |
|--------|------|---------|-------|
| `is_admin` | `boolean` | `false` | Added to `students` table. Determines admin access. |

**Migration 2 — `create_contact_submissions_table`:**

Store form submissions from the 4 public mail forms so admin can view them in the panel instead of relying solely on email.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `type` | string | One of: `contact`, `enrollment`, `assessment`, `tutor` |
| `name` | string | Sender name |
| `email` | string | Sender email |
| `phone` | string, nullable | Sender phone |
| `subject` | string, nullable | Message subject |
| `message` | text | Message body |
| `metadata` | json, nullable | Any extra form fields (e.g. course preference, qualification) |
| `read_at` | timestamp, nullable | When an admin marked it as read |
| `timestamps` | | `created_at` / `updated_at` |

**Migration 3 — Seeder command:**

Artisan command `admin:create` that takes an email address and sets `is_admin = true` on an existing student. No admin registration through the web.

---

#### Step 2: Model and Middleware

**`ContactSubmission` model:**
- `fillable`: `type`, `name`, `email`, `phone`, `subject`, `message`, `metadata`, `read_at`
- `casts`: `metadata` → `array`, `read_at` → `datetime`
- Scopes: `scopeUnread()`, `scopeOfType($type)`
- Relationship: none (standalone)

**`Student` model update:**
- Add `is_admin` to `$casts` as `boolean`
- Add helper method: `isAdmin(): bool`

**`EnsureUserIsAdmin` middleware:**
- Check `auth()->user()->isAdmin()`, abort 403 if not.
- Register in `bootstrap/app.php` as alias `admin`.

**Update mail controllers** (`ContactMailController`, `EnrollMailController`, `AssessmentMailController`, `TutorMailController`):
- After sending the email, also create a `ContactSubmission` record with the appropriate `type` so submissions are stored in the database.

---

#### Step 3: Routes

All admin routes in a dedicated route group in [`routes/web.php`](file:///var/www/html/croydon_college_of_excellence/routes/web.php):

```php
Route::prefix('admin')
    ->middleware(['auth', 'verified', 'admin'])
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        // Students (Learners)
        Route::get('/students', [AdminStudentController::class, 'index'])
            ->name('students.index');
        Route::get('/students/{student}', [AdminStudentController::class, 'show'])
            ->name('students.show');
        Route::patch('/students/{student}/toggle', [AdminStudentController::class, 'toggle'])
            ->name('students.toggle');

        // Purchases
        Route::get('/purchases', [AdminPurchaseController::class, 'index'])
            ->name('purchases.index');
        Route::get('/purchases/{purchase}', [AdminPurchaseController::class, 'show'])
            ->name('purchases.show');

        // Courses
        Route::get('/courses', [AdminCourseController::class, 'index'])
            ->name('courses.index');
        Route::get('/courses/{course}/edit', [AdminCourseController::class, 'edit'])
            ->name('courses.edit');
        Route::put('/courses/{course}', [AdminCourseController::class, 'update'])
            ->name('courses.update');
        Route::patch('/courses/{course}/toggle', [AdminCourseController::class, 'toggle'])
            ->name('courses.toggle');

        // Analytics
        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])
            ->name('analytics.index');

        // Contact Submissions
        Route::get('/submissions', [AdminSubmissionController::class, 'index'])
            ->name('submissions.index');
        Route::get('/submissions/{submission}', [AdminSubmissionController::class, 'show'])
            ->name('submissions.show');
        Route::patch('/submissions/{submission}/read', [AdminSubmissionController::class, 'markRead'])
            ->name('submissions.read');
    });
```

---

#### Step 4: Controllers

All placed in `app/Http/Controllers/Admin/`:

**`AdminDashboardController`** — `index()`
- Total registered students (all, verified, unverified)
- Total revenue (sum of `purchases.amount` where `status = paid`, divided by 100 for display)
- Total purchases by status (paid, pending, failed)
- Active courses count
- Recent 10 signups (with verification status)
- Recent 10 purchases (with student, course, amount, status)
- Unread contact submissions count

**`AdminStudentController`** — `index()`, `show($student)`, `toggle($student)`
- `index`: Paginated list of all students. Search by name/email. Filter by: verified/unverified, has purchases/no purchases. Sort by: created_at, name, email. Columns: name, email, verified status, purchase count, joined date.
- `show`: Full student detail — profile info, email verification status, all secondary emails (`StudentEmail`), purchase history, quiz attempt summary, lesson progress summary, login history (last 20).
- `toggle`: Enable/disable a student account (add `is_active` boolean to students migration or use `locked_until` with a far-future date).

**`AdminPurchaseController`** — `index()`, `show($purchase)`
- `index`: Paginated list of all purchases. Filter by: status (paid/pending/failed), course, date range. Sort by: created_at, amount. Columns: student name/email, course name, amount (formatted as £), status, date, Stripe session ID.
- `show`: Full purchase detail — student info, course info, Stripe session ID, payment intent ID, amount, currency, status, terms accepted at/version, timestamps.

**`AdminCourseController`** — `index()`, `edit($course)`, `update($course)`, `toggle($course)`
- `index`: All courses sorted by `sort_order`. Columns: name, slug, price (formatted), is_active status, purchase count, total revenue.
- `edit`: Form to update `name`, `tagline`, `description`, `price` (input in pounds, store in pence), `sort_order`.
- `update`: Validate and save. Price stored as integer pence (multiply input by 100).
- `toggle`: Toggle `is_active` boolean.

**`AdminAnalyticsController`** — `index()`
- Per-course stats: total purchases, total revenue, completion rate (lessons completed / total lessons), average quiz score.
- Quiz breakdown: per quiz — attempt count, average percentage, pass rate (>= 50%).
- Student progress: per course — enrolled students, students who completed all lessons, students who attempted all quizzes.

**`AdminSubmissionController`** — `index()`, `show($submission)`, `markRead($submission)`
- `index`: Paginated list of all contact submissions. Filter by: type (contact/enrollment/assessment/tutor), read/unread. Sort by: created_at. Columns: type badge, name, email, subject (truncated), date, read status.
- `show`: Full submission detail with all fields. Marks as read automatically on view.
- `markRead`: Toggle read status.

---

#### Step 5: Views

All in `resources/views/admin/`:

**Layout — `resources/views/admin/layouts/app.blade.php`:**
- Separate admin layout, not sharing the public website's `master.blade.php`.
- Simple Bootstrap 5 layout with:
  - Top navbar: "Admin Panel" branding, logged-in admin name, link back to public site, logout button.
  - Left sidebar: navigation links to Dashboard, Students, Purchases, Courses, Analytics, Submissions (with unread badge count).
  - Main content area with `@yield('content')`.
  - Flash message support via `@include('admin.partials.flash')`.
- Uses the same Bootstrap 5 CSS already loaded on the site. No extra CSS frameworks.
- Page-specific JS only where needed (e.g. charts on analytics page).

**Views structure:**

```
resources/views/admin/
├── layouts/
│   └── app.blade.php              # Admin layout with sidebar
├── partials/
│   ├── sidebar.blade.php          # Sidebar navigation
│   ├── flash.blade.php            # Flash messages
│   └── stats-card.blade.php       # Reusable summary card component
├── dashboard.blade.php            # Summary cards + recent activity tables
├── students/
│   ├── index.blade.php            # Paginated student list with search/filters
│   └── show.blade.php             # Student detail (tabs: profile, purchases, progress, logins)
├── purchases/
│   ├── index.blade.php            # Paginated purchase list with filters
│   └── show.blade.php             # Purchase detail with Stripe IDs
├── courses/
│   ├── index.blade.php            # Course list with revenue stats
│   └── edit.blade.php             # Course edit form
├── analytics/
│   └── index.blade.php            # Per-course stats, quiz breakdown
└── submissions/
    ├── index.blade.php            # Submission list with type badges, read status
    └── show.blade.php             # Full submission detail
```

---

#### Step 6: Testing

**Feature tests in `tests/Feature/AdminPanelTest.php`:**

| Test | What it verifies |
|------|-----------------|
| `non_admin_cannot_access_admin_routes` | A verified non-admin student gets 403 on all admin routes. |
| `guest_is_redirected_to_login` | Unauthenticated user is redirected to `/login`. |
| `unverified_admin_cannot_access` | An admin with unverified email cannot access admin routes. |
| `admin_can_see_dashboard` | Dashboard loads with correct stats (student count, revenue, purchase counts). |
| `admin_can_list_students` | Student index page shows paginated students with correct data. |
| `admin_can_search_students` | Search by name and email returns correct results. |
| `admin_can_view_student_detail` | Student show page displays profile, purchases, progress, logins. |
| `admin_can_toggle_student` | Toggle endpoint changes student's active status. |
| `admin_can_list_purchases` | Purchase index shows all purchases with correct filters. |
| `admin_can_filter_purchases_by_status` | Status filter returns only matching purchases. |
| `admin_can_view_purchase_detail` | Purchase show page displays all fields including Stripe IDs. |
| `admin_can_list_courses` | Course index shows all courses with revenue totals. |
| `admin_can_edit_course` | Edit form loads with current values pre-filled. |
| `admin_can_update_course` | Update saves new values. Price in pounds converts to pence correctly. |
| `admin_can_toggle_course` | Toggle changes `is_active` status. |
| `admin_can_view_analytics` | Analytics page loads with per-course stats. |
| `admin_can_list_submissions` | Submission index shows all submissions with correct type badges. |
| `admin_can_view_submission` | Viewing a submission marks it as read. |
| `admin_can_filter_submissions_by_type` | Type filter returns only matching submissions. |
| `contact_form_creates_submission` | Submitting the contact form creates a `ContactSubmission` record. |
| `enrollment_form_creates_submission` | Submitting the enrollment form creates a `ContactSubmission` record. |

---

#### Step 7: File Summary

| Category | Files | Count |
|----------|-------|-------|
| Migrations | `add_is_admin_to_students_table`, `create_contact_submissions_table` | 2 |
| Model | `ContactSubmission` | 1 |
| Model update | `Student` (add `isAdmin()`, `is_admin` cast) | 1 |
| Middleware | `EnsureUserIsAdmin` | 1 |
| Artisan command | `admin:create` | 1 |
| Controllers | `AdminDashboardController`, `AdminStudentController`, `AdminPurchaseController`, `AdminCourseController`, `AdminAnalyticsController`, `AdminSubmissionController` | 6 |
| Mail controller updates | `ContactMailController`, `EnrollMailController`, `AssessmentMailController`, `TutorMailController` | 4 |
| Views | Layout + sidebar + partials + 10 page views | ~14 |
| Routes | Admin route group in `web.php` | 1 block |
| Tests | `AdminPanelTest.php` (~20 test methods) | 1 |
| Factory | `ContactSubmissionFactory` | 1 |
| **Total new files** | | **~28** |

---

#### Database Schema Reference (Existing)

For reference, the existing models the admin panel will read from:

| Model | Key Columns | Key Relationships |
|-------|------------|-------------------|
| `Student` | `id`, `name`, `email`, `email_verified_at`, `password`, `verification_code_hash`, `verification_code_sent_at`, `verification_code_attempts`, `verification_code_locked_until`, `locked_until`, `is_admin` | `purchases()`, `quizAttempts()`, `lessonProgress()`, `loginHistories()`, `emails()` |
| `Course` | `id`, `name`, `slug`, `tagline`, `description`, `price` (pence), `is_active`, `sort_order` | `purchases()` |
| `Purchase` | `id`, `student_id`, `course_id`, `stripe_checkout_session_id`, `stripe_payment_intent_id`, `status`, `amount`, `currency`, `terms_accepted_at`, `terms_version`, `paid_at` | `student()`, `course()` |
| `QuizAttempt` | `id`, `student_id`, `course_slug`, `quiz_slug`, `score`, `total`, `percentage`, `answers` (json) | `student()` |
| `LessonProgress` | `id`, `student_id`, `course_slug`, `lesson_slug`, `completed_at` | `student()` |
| `LoginHistory` | `id`, `student_id`, `email`, `ip_address`, `user_agent`, `platform`, `browser`, `device_type`, `login_at`, `logout_at`, `status`, `is_current` | `student()` |
| `StudentEmail` | `id`, `student_id`, `email`, `is_primary`, `verified_at`, `verification_token` | `student()` |
| `StripeWebhookEvent` | `id`, `event_id`, `event_type`, `payload` (json), `processed_at` | — |

---

> **Bottom line:** The application is architecturally sound, secure, and well-tested. The biggest gap is the lack of an admin panel for day-to-day operations. Everything else is optimisation and polish.