# Task: Add Paid "Life in the UK" Courses, Stripe Payments, Lessons and Mock Tests to an Existing Laravel Project (JSON-Content Edition)

You are working on an existing Laravel project (`croydon_college_of_excellence`). Review the whole codebase, then integrate two paid courses with secure account-first checkout, Stripe payments, a learner dashboard, protected lessons, and interactive quizzes/mock tests.

**Architecture decision (fixed):** all lessons, quizzes, questions, options and answers live in JSON files, not in database tables. The database holds only users, courses, purchases, webhook events, lesson progress and quiz attempts. Do not create database tables for lessons, lesson items, quizzes, questions, options or attempt answers.

## 0. Working Rules

- Create a new git branch before making changes (e.g. `feature/paid-courses`). Never commit `.env` or any secrets.
- Do not remove or break existing functionality. Do not overwrite the existing design unnecessarily.
- Do not run destructive commands (`migrate:fresh`, `db:wipe`, etc.) against any database that may hold real data. Use a separate local or testing database for development and tests.
- If something essential is ambiguous or missing (for example a missing answer key), do not guess. Exclude the affected content from learners and report it clearly.
- Use the word "course" instead of "product" in all user-facing text, and prefer course-related names in code and database (`courses`, `course_id`, `Course`, `CoursePurchase`, etc.). Stripe's own API terminology may still appear where the SDK requires it.
- Never invent, alter or "improve" course wording, questions, options or answers.

## 1. Project Audit (before any changes)

Inspect and report on:

- Laravel and PHP versions, and installed packages (`composer.json`)
- Routes, controllers, models, middleware, Blade views, layouts and components
- Existing authentication and user-management logic (including email verification)
- Frontend stack and CSS system (Bootstrap, Tailwind, Vite/Mix, Alpine, etc.)
- Public assets and configuration files
- Database configuration, and which existing migrations have been run
- Any existing payment or Stripe code
- Existing tests, and any configured linting, formatting or static-analysis tools
- **Existing scaffolding in `database/`**, which appears to be an earlier partial attempt at this feature:
  - `database/data/lesson-content.json` and `database/data/quiz-content.json`
  - Migrations: `2024_01_01_000001_create_courses_table`, `..._000002_create_course_documents_table`, `..._000003_create_purchases_table`, `..._000004_create_stripe_webhook_events_table`, `..._000009_create_lesson_progress_table`, `..._000010_create_quiz_attempts_table` (numbers 000005 to 000008 are missing; do not recreate lesson/quiz tables to fill the gap)
  - `database/seeders/CourseSeeder.php` and `DatabaseSeeder.php`
- The contents of `course-files/` if it still exists (optional cross-check source only; see section 3.6)

Read every existing file above before deciding what to reuse. Reuse and extend what fits; do not build a parallel system. Then write a concise implementation plan and a list of assumptions, and proceed unless a blocking question exists.

## 2. Courses and Pricing

Two separate courses. Users only get access to the course they have successfully purchased.

| Course | Price | Includes |
|---|---|---|
| Life in the UK Course | £99 | Lessons 1-10; a final knowledge check for each lesson (10 total); 6 classroom mock tests |
| 24 Mock Tests Course | £49 | 24 mock tests |

- Currency: GBP. Store amounts as integers in pence; format as £ only for display.
- Course names, descriptions, prices and slugs are defined server-side (`courses` table seeded by `CourseSeeder`). Never trust values from the browser.
- Test format used across the mocks: 24 questions, 45 minutes, 75% pass mark (18/24).
- Marketing copy must not promise or guarantee that users will pass the official test.

## 3. Content Architecture (JSON is the source of truth)

### 3.1 Principles

- Content files live in `database/data/` (existing location; keep it), are tracked in a private git repository, and are never placed under `public/` or served directly. No route may return a raw content file.
- Content is read-only at runtime. It is loaded, validated and cached by a single service (section 3.3). Controllers and Blade views never read JSON files directly.
- The existing JSON files are authoritative. First read them and document their actual structure. Adapt the code to the existing structure. Do not rewrite or reformat the files. If something essential is missing (for example stable IDs), provide the optional normalisation command in section 3.4 instead of editing by hand.
- Every lesson, lesson item, quiz, question and option needs a stable, unique string `id` that is never renumbered or reused. Progress and attempts reference these IDs.

### 3.2 Recommended shape (use the existing shape if it already differs)

`lesson-content.json`:

```json
{
  "course": "life-in-the-uk",
  "content_reviewed_at": "2026-09-26",
  "lessons": [
    {
      "id": "lesson-01",
      "number": 1,
      "title": "UK Values, Citizenship & the Life in the UK Test",
      "slug": "uk-values-citizenship-and-the-test",
      "is_published": true,
      "items": [
        { "id": "l01-q001", "question": "...", "answer": "..." }
      ]
    }
  ]
}
```

`quiz-content.json`:

```json
{
  "quizzes": [
    {
      "id": "kc-01",
      "course": "life-in-the-uk",
      "type": "knowledge_check",
      "lesson_id": "lesson-01",
      "number": 1,
      "title": "Knowledge Check 1",
      "slug": "knowledge-check-1",
      "time_limit_minutes": null,
      "pass_mark": 75,
      "is_published": true,
      "questions": [
        {
          "id": "kc01-q01",
          "text": "...",
          "explanation": null,
          "options": [
            { "id": "kc01-q01-a", "text": "...", "pin_last": false }
          ],
          "correct_option_id": "kc01-q01-a"
        }
      ]
    }
  ]
}
```

Quiz `type` is one of `knowledge_check`, `classroom_mock`, `mock_test`. The `course` value must match a course slug defined by the seeder/config (one source of truth).

### 3.3 Content service

- Create a read-only service (e.g. `App\Services\CourseContent\CourseContentRepository`) exposing lessons for a course, one lesson, quizzes for a course (filtered by type), and one quiz with its questions. Return immutable DTOs or collections.
- Parse and validate once, then cache in the Laravel cache keyed by the files' content hash (or mtime), so edits are picked up automatically and repeated requests are fast.
- Only published and valid content is returned. Invalid content fails closed (not served) and is reported by the validation command.
- The content path is configurable (`config/courses.php`) so tests can point to small fixture files.
- Any browser-facing payload for quiz-taking contains only question IDs, question text and option IDs/text. It never includes `correct_option_id`, correctness flags or explanations before submission.

### 3.4 Commands

- `php artisan courses:validate-content [--strict]`: validates both JSON files and writes a report (console plus a file under `storage/app/private/`). It exits non-zero on errors with `--strict` (suitable for CI and pre-deploy). It checks:
  - Valid JSON, required fields present, unique IDs, no empty text.
  - Each quiz references a valid course slug and (for knowledge checks) an existing lesson; each question has 2-6 options and exactly one correct option that exists in its options; no duplicate option text within a question.
  - Expected counts: 10 lessons, 10 knowledge checks, 6 classroom mocks, 24 mock tests (configured in `config/courses.php`). Report any deviation.
  - Content outside the defined courses (for example Lessons 11 and 12, which appeared in the source contents list) is not served. Report it.
  - Warnings: questions whose correct answer does not match the lesson item's answer for the same question (normalised text comparison); wrong options that look unrelated (for example a yes/no question with non-yes/no options); lines containing time-sensitive wording ("as of", "currently", "fee", "£", "must be booked", year references) for the owner to verify against gov.uk.
- `php artisan courses:normalise-content [--dry-run]` (optional): adds missing stable IDs or `pin_last` flags without changing any wording. It writes a timestamped backup first and shows a diff summary. It is never run automatically.
- `php artisan courses:clear-content-cache`.

### 3.5 Content maintenance rules (document them)

- Edit only the JSON files. Never renumber or reuse an ID; add new IDs for new items.
- Run `courses:validate-content --strict` before every commit and deploy.
- Content ships with the code. Attempt history is protected by snapshots (section 5), so edits never change past results.

### 3.6 Notes on the original source material

Some content came from Word files that may or may not still be in `course-files/`. If they exist, use them only to spot-check the JSON. Never overwrite the JSON from them. Observations to keep in mind:

- Lessons are numbered question-and-answer pairs. Knowledge checks are 10 multiple-choice questions each, options A-D, sometimes with a "None of these" option. Class mocks and the 24 mocks are 24 questions each. All questions are single-answer.
- In the source mock papers the correct letter cycles A, B, C, D from question to question, and many wrong options are answers taken from unrelated questions. Do not change wording. Shuffle option order at runtime (section 5) and list suspicious questions in the validation report.
- Print/teacher elements ("Student: ___ Date: ___", "Circle or tick one answer", teacher notes and answer keys) must not appear in the learner experience.
- Some facts are date-sensitive (for example the test fee and voting age "as of 26 September 2026"). Show a configurable "content last reviewed" date on lessons (from `content_reviewed_at`).

## 4. Lesson Experience (Life in the UK Course)

- Each lesson is a study page of numbered question-and-answer cards. By default answers are visible (reading mode). Add a "Test yourself" toggle that hides answers with per-card "Show answer" and "Reveal all".
- Lesson navigation: a list of lessons 1-10 with completed/current/locked states, plus Previous/Next buttons.
- "Mark lesson as complete" (POST) stores a `lesson_progress` row (user, course, lesson ID string, completed_at; unique per user and lesson). Show progress on the course page and dashboard.
- Config toggles (defaults): lesson N+1 unlocks after lesson N is complete (`true`); the lesson's knowledge check unlocks after the lesson is complete (`true`); classroom mocks are recommended after all 10 lessons but not locked (`false`).
- Responsive, accessible (keyboard operable, sensible headings, readable line length), consistent with the existing site design.

## 5. Quiz and Mock Test Experience

Applies to knowledge checks (10 questions, untimed), classroom mocks (24 questions, 45 minutes) and the 24 mock tests (24 questions, 45 minutes). Time limit and pass mark come from the quiz JSON (pass mark default 75%).

**Attempt lifecycle and snapshots**
- Starting a quiz (POST) checks course access, then either resumes the user's existing in-progress, unexpired attempt or creates a new `quiz_attempts` row.
- At creation, store a snapshot in the attempt (JSON column): content hash, quiz ID/title/pass mark/time limit, and for each question its ID, text, explanation, options in the per-attempt display order, and the correct option ID. The snapshot lives server-side only. Scoring and review use the snapshot, so later edits to the JSON never change past attempts.
- Option order is shuffled per attempt (default on, config toggle). Options flagged `pin_last` (such as "None of these" or "All of the above") stay last. Re-label options A-D by displayed position. Question order follows the source by default (config toggle to shuffle).
- Store answers and flags as JSON on the attempt (`answers`: question ID to option ID; `flagged`: list of question IDs), plus `status` (in_progress / submitted / expired), `started_at`, `expires_at`, `submitted_at`, `score`, `total`, `percentage`, `passed`. Update them inside a transaction with a row lock.
- The autosave endpoint validates that the question ID exists in the attempt's snapshot and that the option ID belongs to that question. Reject changes once the attempt is submitted or expired.
- The server is authoritative for time. The timer is UI only. Answers saved after `expires_at` (plus a short grace period) are not counted. Finalise expired attempts lazily when accessed (optionally also with a scheduled cleanup).

**Taking a test**
- One question at a time with Previous/Next, a palette showing answered/unanswered/flagged, "flag for review", a progress indicator, a countdown for timed tests (auto-submit at expiry), and a review-your-answers screen before submitting. Autosave so refreshes or dropped connections do not lose progress.
- Use the existing frontend stack (plain JS, Alpine, Livewire, etc.). Do not add a new framework. Must work well on mobile.

**Results**
- Show the score (raw and percentage), pass/fail (pass if percentage >= pass mark), time taken and date.
- Review every question from the snapshot: the learner's answer, the correct answer, and a right/wrong state that does not rely on colour alone (icons and text labels). Offer a "wrong answers only" filter. Show explanation text only if it exists. For knowledge checks, link to the relevant lesson.
- Unlimited retakes. Show attempt history and best score per quiz (computed from `quiz_attempts`).

## 6. Homepage

Add a promotional courses section to `home.blade.php`, directly below the existing banner.

- Advertise both courses with: name, short description, included content, price in GBP, and a clear call-to-action.
- Buttons: `Buy Life in the UK Course — £99` and `Buy 24 Mock Tests Course — £49`.
- Heading and copy must refer to "courses", not "products".
- Responsive, using the existing CSS framework, components and design conventions. Do not introduce a new frontend framework.
- Render name, description, price and currency from the `Course` model (seeded), not hard-coded in the view.
- Guests, logged-in users and owners follow the flow in section 7. Owners see "Go to course" instead of "Buy".
- Include a short disclaimer near the section or footer: the courses are independent study material and are not affiliated with or endorsed by the UK Home Office.

## 7. Authentication and Checkout Flow (required)

Use an account-first flow. Guests cannot start checkout.

1. Guest clicks "Buy": store the intended course slug in the session (`url.intended` or equivalent), then redirect to register/login.
2. Registration uses Laravel's official starter kit (Breeze or Fortify), whichever fits the project's frontend stack. Do not write custom auth from scratch.
3. Email verification is mandatory (`MustVerifyEmail`). Apply the `verified` middleware to checkout and to all paid course routes. Unverified users see a "verify your email" page with a resend option.
4. After login and verification, return the user to the course they selected and start checkout only via an explicit POST (CSRF-protected) with the course slug validated server-side.
5. Checkout Session uses `customer_email` (or a stored Stripe Customer ID) from the authenticated user, plus `client_reference_id` = user ID and metadata `course_id` and `user_id`.
6. Access is granted only by the signature-verified webhook. The success page shows "confirming payment" until the purchase record is paid.
7. Security baseline: hashed passwords, strong password rules, rate limiting on login, registration, password reset and verification resend, session regeneration on login, secure/HTTP-only/SameSite cookies in production, password reset flow, and no passwords or tokens in logs. Design so 2FA can be added later.
8. One account may buy both courses; each course unlocks independently. Logged-in users who already own a course see "Go to course" instead of "Buy".

## 8. Stripe Payments

Stripe is the payment method. NatWest is only the bank account receiving Stripe payouts; do not integrate with NatWest.

- If Stripe is already integrated, extend it and do not create a conflicting second system. Otherwise install the official Stripe PHP SDK (or Laravel Cashier only if it clearly fits) compatible with this Laravel version.
- Use Stripe test mode by default. All credentials come from `.env` via `config/services.php` (or a dedicated config file). Never call `env()` outside config files. Never hard-code keys.
- Use one source of truth for pricing: either Stripe Price IDs stored in config/`courses.stripe_price_id`, or server-built `price_data` from the `courses` table. Do not mix both. Document the choice.
- Checkout: the server validates the course slug, loads the course and price, creates a Stripe Checkout Session (GBP) with the parameters in section 7, and redirects to Stripe. Prevent buying a course the user already owns.
- The success and cancel pages are informational only. They never grant access.
- Access is granted only after a verified webhook marks the purchase as paid.

### Webhook
- A dedicated endpoint that verifies the `Stripe-Signature` header with the webhook secret. Always enabled. Reject invalid signatures with 400. Exclude the route from CSRF only as needed for the Laravel version.
- Handle at least: `checkout.session.completed` (grant access only if `payment_status` is `paid`), `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `payment_intent.payment_failed`, and `charge.refunded` / `charge.dispute.created` (mark the purchase refunded/disputed and revoke or flag access according to a documented policy). Ignore unknown events with a 200 response.
- Idempotency: store processed Stripe event IDs in `stripe_webhook_events` with a unique constraint. Handle duplicate deliveries and out-of-order events safely. Use database transactions and row locking where needed.
- Verify the amount and currency from Stripe match the course's server-side price before granting access.
- Return quickly with correct HTTP status codes. Log failures without sensitive data.

### Cancelled and failed payments
- Cancelled: the user returns via `cancel_url` to a friendly page with a retry option. Mark the pending purchase cancelled/expired when Stripe reports it.
- Failed: record the failed status, show a helpful message and allow retry.

### Emails and receipts
- Enable Stripe receipts or send a purchase-confirmation email (document the choice) to the verified account email.

### UK legal items (implement placeholders and flag for owner review)
- Links to Terms, Privacy Policy and Refund Policy on checkout-related pages.
- Consent to immediate access to digital content and the 14-day cancellation-right waiver before checkout, if immediate access is granted.
- VAT: document whether Stripe Tax is enabled or prices are VAT-inclusive.

## 9. Database

Use the project's configured database driver. If none is configured, choose a sensible Laravel-supported option (MySQL/MariaDB/PostgreSQL for production, SQLite for local/testing) and document the `.env` settings.

**Existing migrations:** review the existing migrations listed in section 1. If a migration has been run on any shared or production database, do not edit it; add a new migration instead. If it has never been run anywhere, you may adjust or rename it. Report which case applies. The existing table is named `purchases`; the preferred name is `course_purchases`. If the table can be renamed safely, rename it. Otherwise keep the table name and name the model `CoursePurchase` with an explicit `$table`. Existing `lesson_progress` and `quiz_attempts` migrations likely reference lesson/quiz tables that must not exist. Rework them to use string IDs from the JSON content (no foreign keys to content tables). Review `course_documents`: keep it only if it serves a clear purpose (for example downloadable documents), and report your decision. Do not drop anything without reporting it.

**Tables that should exist (and only these for this feature):**

- `courses`: `id`, `name`, `slug` (unique), `description`, `price` (integer, pence), `currency` (default `gbp`), `stripe_price_id` (nullable), `is_active`, timestamps.
- `course_purchases` (or existing `purchases`): `id`, `user_id`, `course_id`, `stripe_checkout_session_id` (unique), `stripe_payment_intent_id` (unique, nullable), `stripe_customer_id` (nullable), `amount`, `currency`, `status` (pending, paid, failed, cancelled, expired, refunded, disputed), `paid_at`, `refunded_at` (nullable), timestamps. Prevent duplicate paid purchases of the same course by the same user (unique rule or guarded transaction).
- `stripe_webhook_events`: `id`, `stripe_event_id` (unique), `type`, `processed_at`, timestamps.
- `lesson_progress`: `id`, `user_id`, `course_id`, `lesson_id` (string content ID), `completed_at`, timestamps. Unique on (`user_id`, `lesson_id`).
- `quiz_attempts`: `id`, `user_id`, `course_id`, `quiz_id` (string content ID), `status`, `started_at`, `expires_at`, `submitted_at`, `score`, `total`, `percentage`, `passed`, `snapshot` (JSON), `answers` (JSON), `flagged` (JSON), `content_hash`, timestamps. Index on (`user_id`, `quiz_id`).

Write migrations, Eloquent models with relationships, casts and scopes, and factories for test data only. `CourseSeeder` creates only the two course records. `DatabaseSeeder` calls `CourseSeeder`. Do not put lessons or questions in seeders or factories.

## 10. Access Control

- Create reusable access logic (e.g. `CoursePolicy`/Gate plus a `course.access` middleware, or `User::hasCourseAccess($course)`) and use it on every protected route. Do not duplicate authorization checks in controllers and views. Check access before calling the content service.
- Life in the UK purchasers access: lessons 1-10, the 10 knowledge checks and the 6 classroom mock tests.
- 24 Mock Tests purchasers access: the 24 mock tests only.
- A user who owns one course must not access the other. Unpaid users get 403 or a redirect to the course page. Guests are redirected to login. Unverified users are redirected to email verification.
- Users can only see their own purchases, progress and attempts (prevent IDOR on attempts, purchases and lessons).
- Content files are never web-accessible. Verify the web server serves only `public/`.

## 11. Routes (named routes, appropriate HTTP methods)

Public: homepage; course landing pages if useful; Stripe webhook (POST); checkout success and cancel pages.
Authenticated and verified: `POST` start checkout (per course); learner dashboard; my courses / purchase history.
Protected (course access required): course overview; lessons index and lesson show; mark lesson complete (POST); quiz start (POST), question show, answer autosave (POST), submit (POST), result and review; quiz attempt history.

Apply rate limiting to checkout, quiz autosave and quiz submission routes.

## 12. Security

- CSRF protection on all state-changing web routes (except the signed webhook).
- Validate all input with Form Requests. Never trust prices, amounts, course names or IDs from the client. Resolve courses and quizzes server-side and check ownership of attempts.
- Never expose Stripe secret keys or webhook secrets. Do not log passwords, secrets or full payment details.
- Use transactions where appropriate. Handle exceptions and Stripe API errors with user-friendly messages.
- Escape output in Blade. Content is plain text; if any content field contains HTML, sanitise it before rendering.
- Review the final implementation for authorization gaps and answer leakage (HTML, JSON responses, JS, logs).

## 13. Environment and Documentation

Update `.env.example` (as plain text, no escaped characters):

```env
APP_URL=http://localhost

DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

STRIPE_COURSE_LIFE_IN_UK_PRICE_ID=
STRIPE_COURSE_MOCK_TESTS_PRICE_ID=
```

Add any other variables you introduce (mail settings, lesson-unlock toggles, shuffle toggles, content path).

Create `docs/COURSES_AND_PAYMENTS.md` (or a README section) covering: required env variables; database setup, migrations and seeding; the JSON content structure and maintenance rules (section 3.5); the validation, normalisation and cache commands and how to read the report; creating Stripe test keys, courses and prices; webhook setup (Stripe CLI locally: `stripe listen --forward-to <APP_URL>/stripe/webhook`); testing successful payments (test card `4242 4242 4242 4242`), failed payments (e.g. `4000 0000 0000 0002`) and cancelled payments; switching from test to live mode; and production requirements (HTTPS, mail configuration for verification emails, queue worker if used, `config:cache`, correct webhook endpoint and live signing secret, log monitoring, web root pointing at `public/`).

**Data safety:** state clearly that content is protected by the private git repository, while learner data (accounts, purchases, progress, attempts) exists only in the database and cannot be rebuilt from files. Document automated daily off-server database backups (e.g. `spatie/laravel-backup`, retention policy, a tested restore procedure, a backup before each deploy). Deployments use `php artisan migrate --force` and never `migrate:fresh`. Optionally provide a `courses:reconcile-stripe` command that rebuilds paid purchase records from Stripe Checkout Sessions for disaster recovery.

## 14. Tests

Use a mocked/faked Stripe client (or signed fixture payloads) so tests never call the Stripe network. Use an in-memory or dedicated test database, and small fixture JSON files via the configurable content path. Cover:

- Homepage: course names, descriptions, prices and currency
- Auth flow: guest redirected to register/login with the intended course remembered; unverified user blocked from checkout and course content; verified user returned to the selected course
- Checkout: session creation, invalid course handling, already-purchased handling, server-side price verification, `client_reference_id` and metadata set
- Webhook: valid signature success, invalid signature rejection, duplicate event handling, out-of-order events, amount/currency mismatch, failed, expired, refunded
- Purchase creation and payment-status updates
- Access: Life in the UK purchaser can reach lessons, knowledge checks and classroom mocks; 24 Mock Tests purchaser can reach the 24 mocks; unpaid users rejected; cross-course access rejected; guests rejected; direct URL access rejected; users cannot view other users' purchases or attempts
- The success page does not grant access before the webhook
- Cancelled and failed payment flows
- Content service and validation: valid fixtures load; duplicate IDs, missing correct option, multiple correct options, unknown course and lesson references are detected; invalid or unpublished quizzes are not served; cache refreshes when the file changes; no route serves raw content files
- Quiz: correct scoring, pass/fail at the 75% boundary, correct answers absent from HTML/JSON before submission, option shuffling with pinned last options, snapshot stored at start, editing the JSON after an attempt does not change that attempt's review, autosave validates question and option IDs, resume of an in-progress attempt, timer expiry handling, review page showing right and wrong answers, attempt history, retake
- Lessons: progress tracking and unlock rules
- Ownership: users cannot read or modify another user's attempt or progress

Run the full existing test suite plus the new tests. Run any configured formatter, linter and static-analysis tools (Pint, PHPStan/Larastan, ESLint, etc.) and fix failures caused by your changes.

## 15. Implementation Order

1. Audit and plan (sections 1-3), including the reading of the existing JSON files and migrations.
2. Migrations (reworking the existing ones as described), models, factories, `CourseSeeder`.
3. Content service, `courses:validate-content` and the validation report.
4. Auth flow, Stripe checkout, webhook and payment-status handling.
5. Access control (policy, middleware, helpers).
6. Dashboard, lessons, quizzes with snapshots, results.
7. Homepage section, success/cancel pages, navigation links.
8. Tests, then linting/formatting, then fixes.
9. Final security and maintainability review.

## 16. Final Response

Provide:

1. Files created or modified
2. Database changes, including what happened to each existing migration and to `course_documents`
3. Stripe integration summary
4. Authentication and access-control summary
5. Content architecture summary: the actual structure of the existing JSON files, any adaptations, and the full validation report (counts, errors, warnings, unpublished or out-of-scope content such as Lessons 11 and 12, suspicious wrong options, time-sensitive lines, answers that disagree with lesson text)
6. Lesson and quiz experience summary
7. Homepage changes
8. Required `.env` variables
9. Migration, seeding and validation commands
10. Stripe webhook setup instructions
11. Local testing instructions
12. Production deployment instructions, including backups
13. Assumptions, limitations and legal/business items needing owner review
14. Test suite results (and lint/static-analysis results)
