# Task: Add Paid "Life in the UK" Courses, Stripe Payments, Lessons and Mock Tests to an Existing Laravel Project

> **Note — this file is the original brief, kept as a record.** It describes the
> checkout flow as originally specified (a guest clicking Buy is redirected to
> register/login, and checkout starts from an explicit POST). The flow was since
> streamlined into a single journey: **Buy → Create account → Verify email →
> Check your order → Pay**, where the Buy button is a link to `checkout.start`
> that routes the visitor to whichever step they are actually on, and a
> **check-your-order** page with a required consent tick box sits in front of
> Stripe. For how the site works today, see
> [`docs/PAID_COURSES.md`](docs/PAID_COURSES.md).

You are working on an existing Laravel project. Review the whole codebase, then integrate two paid courses with secure account-first checkout, Stripe payments, a learner dashboard, protected lessons, and interactive quizzes/mock tests built from the `.docx` files in `course-files/`.

## 0. Working Rules

- Create a new git branch before making changes (e.g. `feature/paid-courses`). Never commit `.env` or any secrets.
- Do not remove or break existing functionality. Do not overwrite the existing design unnecessarily.
- Do not run destructive commands (`migrate:fresh`, `db:wipe`, etc.) against any database that may hold real data. Use a separate local or testing database for development and tests.
- If something essential is ambiguous or missing (for example, answer keys), do not guess. Quarantine the affected content and report it clearly.
- Use the word "course" instead of "product" in all user-facing text, and prefer course-related names in code and database (`courses`, `course_id`, `Course`, `CoursePurchase`, etc.). Stripe's own API terminology may still appear where the SDK requires it.
- Never invent, alter or "improve" course wording, questions, options or answers.

## 1. Project Audit (before any changes)

Inspect and report on:

- Laravel and PHP versions, and installed packages (`composer.json`)
- Routes, controllers, models, middleware, Blade views, layouts and components
- Existing authentication and user-management logic (including email verification)
- Frontend stack and CSS system (Bootstrap, Tailwind, Vite/Mix, Alpine, etc.)
- Public assets and configuration files
- Database configuration and any existing migrations. Do not assume there is no database; verify.
- Any existing payment or Stripe code
- Existing tests, and any configured linting, formatting or static-analysis tools
- The complete contents of the `course-files/` directory (filenames, formats, structure, embedded images/tables)

Then write a concise implementation plan, including the file-to-content mapping (section 3) and a list of assumptions. Proceed with the implementation unless a blocking question exists.

## 2. Courses and Pricing

Two separate courses. Users only get access to the course they have successfully purchased.

| Course | Price | Includes |
|---|---|---|
| Life in the UK Course | £99 | Lessons 1–10; a final knowledge check for each lesson (10 total); 6 classroom mock tests |
| 24 Mock Tests Course | £49 | 24 mock tests |

- Currency: GBP. Store amounts as integers in pence; format as £ only for display.
- Course names, descriptions, prices and slugs are defined server-side (database + seeder). Never trust values from the browser.
- Test format used across the mocks: 24 questions, 45 minutes, 75% pass mark (18/24).
- Marketing copy must not promise or guarantee that users will pass the official test.

## 3. Source Files: Observed Format and Mapping

These observations come from excerpts. Verify them against the complete files and report any differences.

| Material | Observed format | Belongs to |
|---|---|---|
| Lessons 1–10 | Numbered question-and-answer pairs ("N. Question" then "Answer: ..."). Lesson 1 has 32 pairs. | Life in the UK |
| Knowledge Checks | "Knowledge Check N" per lesson: 10 multiple-choice questions, options A–D, sometimes with a "None of these" option | Life in the UK |
| Class Mock Tests 1–6 | 24 multiple-choice questions each, options A–D, header mentions teacher answer keys | Life in the UK |
| Mock Tests (numbered up to 24) | Question papers plus answer keys, e.g. "Mock Test 21 Answers": a letter grid (1. A 2. B ...) and a detail list ("1. A — answer text") | 24 Mock Tests |

Notes for the agent:

- **Inconsistent headings:** headings and titles are inconsistent (e.g. "Lesson1:" vs "Lesson-11:", and a title typo "IFE IN THE UK TEST COURSE"). Take course, lesson and quiz titles from the seeder, not from scraped headings.
- **Lessons 11 and 12:** the lesson file's contents list also names "Lesson-11: 2 Mock Tests & Solutions" and "Lesson-12: 2 Mock Tests & Solutions". These are not part of either course as defined. Find them. If the evidence shows they are mock tests that belong to the 24-test set, map them there and show this in your plan. Otherwise import them unpublished and report. Never silently add them to the Life in the UK Course.
- **Print and teacher elements:** strip fields meant for paper use ("Student: ___ Date: ___ Score: ___ Time: ___", "Circle or tick one answer") and teacher-only notes. Keep the useful learner-facing guidance (use after all 10 lessons, allow 45 minutes, choose one answer). Answer keys are used only for scoring and never displayed before submission.
- **Auto-numbering:** question numbers and option letters may be Word auto-numbering that is not present in the raw text. Handle both.
- **Single-answer only:** every question is single-answer. If any question looks multi-select, quarantine it and report it.
- **Weak wrong options:** many wrong options are answers from unrelated questions. Do not rewrite them. List suspicious questions in the report (for example, a yes/no question whose options are not yes/no).
- **No positional pattern:** in the mock papers the correct letter cycles A, B, C, D from question to question. Do not assume any positional pattern when determining answers, and shuffle options at runtime (section 6).
- **Time-sensitive facts:** some content is dated (e.g. "as of 26 September 2026": test fee, voting age). Do not change it. Flag lines containing "as of", "currently", "fee", "£", "must be booked" or year references so the owner can verify them against gov.uk, and add a configurable "content last reviewed" date shown on lessons.

## 4. Content Model, Import and Answer Verification

Store content as structured data in the database and render it through reusable Blade templates and components. Do not hand-write one Blade file per lesson or quiz.

- Build a documented, idempotent Artisan command, e.g. `php artisan courses:import-content {--dry-run} {--allow-partial}`, using PhpWord or Pandoc. Re-running it must update existing records (match on a stable `source_key`) rather than create duplicates.
- Do not rename or move source files. Keep them outside `public/`.
- Sanitize any imported HTML. Preserve lists and tables. Store embedded images privately and serve them through an authorized controller.
- Preserve wording exactly. Only trim whitespace and normalise spacing and quotes. Report suspected typos instead of fixing them.
- Every imported record keeps a source reference (file name and question number) for traceability.

**Determining the correct answer**, in this order:

1. An explicit answer key in the files (letter grid and/or detail list).
2. For keys with both a grid and a detail list (Mocks 21–24): the letter's option text must match the detail text. Any mismatch is an error.
3. If no key exists (possibly the class mocks and knowledge checks): match the question text to the lesson "Answer:" text using normalised exact matching. Accept only if exactly one option matches. Mark `answer_source = lesson_match` and list every such question in the report for owner spot-check.
4. Otherwise quarantine the question.

**Validation rules:** 2–6 options (normally 4), exactly one correct, no empty or duplicate options, questions numbered without gaps, 24 questions per mock and 10 per knowledge check (report any deviation). A quiz with any quarantined question is imported unpublished unless `--allow-partial` is passed, so learners never see incomplete tests. Write an import report (JSON and readable text, stored privately) listing counts, the file-to-content mapping, quarantined questions, `lesson_match` answers, suspicious-distractor candidates, time-sensitive lines and unmapped content. The command exits non-zero on validation errors.

## 5. Lesson Experience (Life in the UK Course)

- Each lesson is a study page of numbered question-and-answer cards. By default answers are visible (reading mode). Add a "Test yourself" toggle that hides answers with a per-card "Show answer" and "Reveal all".
- Lesson navigation: sidebar or top list of lessons 1–10 with completed/current/locked states, plus Previous/Next buttons.
- "Mark lesson as complete" (POST). Track per-user `lesson_progress` and show overall progress on the course page and dashboard.
- Config toggles (with defaults): lesson N+1 unlocks after lesson N is complete (`true`); the lesson's knowledge check unlocks after the lesson is complete (`true`); the classroom mocks are only recommended after all 10 lessons and are not locked (`false`).
- Responsive, accessible (keyboard operable, sensible headings, readable line length), consistent with the existing site design.

## 6. Quiz and Mock Test Experience

Applies to knowledge checks (10 questions, untimed), classroom mocks (24 questions, 45 minutes) and the 24 mock tests (24 questions, 45 minutes). Time limit and pass mark are configurable per quiz (`time_limit_minutes` nullable; pass mark default 75%).

**Taking a test**
- One question at a time with Previous/Next, a question palette showing answered/unanswered/flagged, "flag for review", a progress indicator, and a review-your-answers screen before submitting.
- Timed tests show a countdown and auto-submit at expiry. The timer is UI only: the server stores `started_at` and `expires_at` and is authoritative. Answers saved after expiry (plus a short grace period) are not counted.
- Answers are saved server-side as the learner goes (autosave), so a refresh or connection drop does not lose progress. One in-progress attempt per user per quiz. Resume it if the learner returns.
- Use the existing frontend stack (plain JS, Alpine, Livewire, etc.). Do not add a new framework. Must work well on mobile.

**Answer security and option order**
- Correct answers and `is_correct` flags must never be sent to the browser (HTML, JSON or JS) before submission. Score on the server.
- Shuffle option order on every attempt (default on). Store option IDs and the per-attempt display order, not letters. Re-label options A–D by displayed position. Pin options such as "None of these" or "All of the above" last. Keep question order as in the source by default (config toggle to shuffle).

**Results**
- Show the score (raw and percentage), pass/fail against the pass mark (pass if percentage >= pass mark), time taken and date.
- Review every question: the learner's answer, the correct answer, and a clear right/wrong state that does not rely on colour alone (icons and text labels). Filter for "wrong answers only". Show explanation text only if the source provides it. For knowledge checks, link to the relevant lesson.
- Unlimited retakes. Each attempt is stored (score, total, passed, timestamps, per-question answers and option order). Show attempt history and best score per quiz.

## 7. Homepage

Add a promotional courses section to `home.blade.php`, directly below the existing banner.

- Advertise both courses with: name, short description, included content, price in GBP, and a clear call-to-action.
- Buttons: `Buy Life in the UK Course — £99` and `Buy 24 Mock Tests Course — £49`.
- Heading and copy must refer to "courses", not "products".
- Responsive, using the existing CSS framework, components and design conventions. Do not introduce a new frontend framework.
- Render name, description, price and currency from the `Course` model (seeded), not hard-coded in the view.
- Guests, logged-in users and owners follow the flow in section 8. Owners see "Go to course" instead of "Buy".
- Include a short disclaimer near the section or footer: the courses are independent study material and are not affiliated with or endorsed by the UK Home Office.

## 8. Authentication and Checkout Flow (required)

Use an account-first flow. Guests cannot start checkout.

1. Guest clicks "Buy": store the intended course slug in the session (`url.intended` or equivalent), then redirect to register/login.
2. Registration uses Laravel's official starter kit (Breeze or Fortify), whichever fits the project's frontend stack. Do not write custom auth from scratch.
3. Email verification is mandatory (`MustVerifyEmail`). Apply the `verified` middleware to checkout and to all paid course routes. Unverified users see a "verify your email" page with a resend option.
4. After login and verification, return the user to the course they selected and start checkout only via an explicit POST (CSRF-protected) with the course slug validated server-side.
5. Checkout Session uses `customer_email` (or a stored Stripe Customer ID) from the authenticated user, plus `client_reference_id` = user ID and metadata `course_id` and `user_id`.
6. Access is granted only by the signature-verified webhook. The success page shows "confirming payment" until the purchase record is paid.
7. Security baseline: hashed passwords, strong password rules, rate limiting on login, registration, password reset and verification resend, session regeneration on login, secure/HTTP-only/SameSite cookies in production, password reset flow, and no passwords or tokens in logs. Design so 2FA can be added later.
8. One account may buy both courses; each course unlocks independently. Logged-in users who already own a course see "Go to course" instead of "Buy".

## 9. Stripe Payments

Stripe is the payment method. NatWest is only the bank account receiving Stripe payouts; do not integrate with NatWest.

- If Stripe is already integrated, extend it and do not create a conflicting second system. Otherwise install the official Stripe PHP SDK (or Laravel Cashier only if it clearly fits) compatible with this Laravel version.
- Use Stripe test mode by default. All credentials come from `.env` via `config/services.php` (or a dedicated config file). Never call `env()` outside config files. Never hard-code keys.
- Use one source of truth for pricing: either Stripe Price IDs stored in config/`courses.stripe_price_id`, or server-built `price_data` from the `courses` table. Do not mix both. Document the choice.
- Checkout: the server validates the course slug, loads the course and price, creates a Stripe Checkout Session (GBP) with the parameters in section 8, and redirects to Stripe. Prevent buying a course the user already owns.
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

## 10. Database

- Use the project's configured database driver where possible. If none is configured, choose a sensible Laravel-supported option (MySQL/MariaDB/PostgreSQL for production, SQLite for local/testing) and document the `.env` settings.
- Write migrations, Eloquent models with relationships, casts and scopes, factories and seeders.

**`courses`**: `id`, `name`, `slug` (unique), `description`, `price` (integer, pence), `currency` (default `gbp`), `stripe_price_id` (nullable), `is_active`, timestamps.

**`course_purchases`**: `id`, `user_id`, `course_id`, `stripe_checkout_session_id` (unique), `stripe_payment_intent_id` (unique, nullable), `stripe_customer_id` (nullable), `amount`, `currency`, `status` (pending, paid, failed, cancelled, expired, refunded, disputed), `paid_at`, `refunded_at` (nullable), timestamps.

**`stripe_webhook_events`**: `id`, `stripe_event_id` (unique), `type`, `processed_at`, timestamps.

**Content and learning tables** (adjust to fit the project): `lessons` (course_id, number, title, slug, sort_order, is_published, source_key); `lesson_items` (lesson_id, question, answer, sort_order, source_key); `quizzes` (course_id, lesson_id nullable, type: knowledge_check / classroom_mock / mock_test, title, slug, number, time_limit_minutes nullable, pass_mark, is_published, source_key); `quiz_questions` (quiz_id, question_text, explanation nullable, sort_order, is_active, answer_source: key / lesson_match, source_reference, source_key); `quiz_options` (question_id, option_text, is_correct, pin_last, sort_order); `quiz_attempts` (user_id, quiz_id, status: in_progress / submitted / expired, started_at, expires_at, submitted_at, score, total, percentage, passed, question_order and option_order JSON); `quiz_attempt_answers` (attempt_id, question_id, selected_option_id nullable, is_correct nullable, flagged); `lesson_progress` (user_id, lesson_id, completed_at).

Add sensible indexes and foreign keys. Prevent duplicate paid purchases of the same course by the same user (unique rule or guarded transaction).

## 11. Access Control

- Create reusable access logic (e.g. `CoursePolicy`/Gate plus a `course.access` middleware, or `User::hasCourseAccess($course)`) and use it on every protected route. Do not duplicate authorization checks in controllers and views.
- Life in the UK purchasers access: lessons 1–10, the 10 knowledge checks and the 6 classroom mock tests.
- 24 Mock Tests purchasers access: the 24 mock tests only.
- A user who owns one course must not access the other. Unpaid users get 403 or a redirect to the course page. Guests are redirected to login. Unverified users are redirected to email verification.
- Users can only see their own purchases, progress and attempts (prevent IDOR on attempts, purchases and lessons).
- Paid content and files never sit in `public/` or under guessable URLs. Serve files and images only through authorized controller routes.

## 12. Routes (named routes, appropriate HTTP methods)

Public: homepage; course landing pages if useful; Stripe webhook (POST); checkout success and cancel pages.
Authenticated and verified: `POST` start checkout (per course); learner dashboard; my courses / purchase history.
Protected (course access required): course overview; lessons index and lesson show; mark lesson complete (POST); quiz start, question show, answer autosave (POST), submit (POST), result and review; quiz attempt history; protected file/image delivery.

Apply rate limiting to checkout, quiz autosave and quiz submission routes.

## 13. Security

- CSRF protection on all state-changing web routes (except the signed webhook).
- Validate all input with Form Requests. Never trust prices, amounts, course names or IDs from the client. Resolve courses and quizzes by slug/ID server-side and check ownership of attempts.
- Never expose Stripe secret keys or webhook secrets. Do not log passwords, secrets or full payment details.
- Use transactions where appropriate. Handle exceptions and Stripe API errors with user-friendly messages.
- Escape output in Blade. Render imported HTML only after sanitising it at import time.
- Review the final implementation for authorization gaps and answer leakage.

## 14. Environment and Documentation

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

Add any other variables you introduce (mail settings, pass mark, lesson-unlock toggles, shuffle toggles, content-reviewed date).

Create `docs/COURSES_AND_PAYMENTS.md` (or a README section) covering: required env variables; database setup, migrations and seeding; the content import command and how to read its report; creating Stripe test keys, courses and prices; webhook setup (Stripe CLI locally: `stripe listen --forward-to <APP_URL>/stripe/webhook`); testing successful payments (test card `4242 4242 4242 4242`), failed payments (e.g. `4000 0000 0000 0002`) and cancelled payments; switching from test to live mode; and production requirements (HTTPS, mail configuration for verification emails, queue worker if used, `config:cache`, correct webhook endpoint and live signing secret, log monitoring, database backups, storage permissions for private course files).

## 15. Tests

Use a mocked/faked Stripe client (or signed fixture payloads) so tests never call the Stripe network. Use an in-memory or dedicated test database. Cover:

- Homepage: course names, descriptions, prices and currency
- Auth flow: guest redirected to register/login with the intended course remembered; unverified user blocked from checkout and course content; verified user returned to the selected course
- Checkout: session creation, invalid course handling, already-purchased handling, server-side price verification, `client_reference_id` and metadata set
- Webhook: valid signature success, invalid signature rejection, duplicate event handling, out-of-order events, amount/currency mismatch, failed, expired, refunded
- Purchase creation and payment-status updates
- Access: Life in the UK purchaser can reach lessons, knowledge checks and classroom mocks; 24 Mock Tests purchaser can reach the 24 mocks; unpaid users rejected; cross-course access rejected; guests rejected; direct URL access rejected; users cannot view other users' purchases or attempts
- The success page does not grant access before the webhook
- Cancelled and failed payment flows
- Import: parser fixtures for each source format, idempotent re-import, answer-key grid/detail mismatch detected, `lesson_match` logic, quarantine of invalid questions, unpublished quiz when a question is quarantined
- Quiz: correct scoring, pass/fail at the 75% boundary, correct answers absent from the HTML/JSON before submission, option shuffling with pinned "None of these", autosave and resume, timer expiry handling, review page showing right and wrong answers, attempt storage, retake
- Lessons: progress tracking and unlock rules
- Secure file delivery: unauthorized denied, authorized allowed, no public path

Run the full existing test suite plus the new tests. Run any configured formatter, linter and static-analysis tools (Pint, PHPStan/Larastan, ESLint, etc.) and fix failures caused by your changes.

## 16. Implementation Order

1. Audit and plan (sections 1–3), including the file-to-content mapping.
2. Migrations, models, factories, seeders.
3. Content importer, answer verification and import report (sections 3–4).
4. Auth flow and Stripe checkout, webhook and payment-status handling.
5. Access control (policy, middleware, helpers).
6. Dashboard, lessons, quizzes, results and file delivery.
7. Homepage section, success/cancel pages, navigation links.
8. Tests, then linting/formatting, then fixes.
9. Final security and maintainability review.

## 17. Final Response

Provide:

1. Files created or modified
2. Database changes
3. Stripe integration summary
4. Authentication and access-control summary
5. Content import summary: the file-to-course mapping, counts of lessons, knowledge checks, classroom mocks and mock tests imported, and the full list of items needing human review (unmapped content such as Lessons 11 and 12, quarantined questions, `lesson_match` answers, suspicious wrong options, time-sensitive lines)
6. Lesson and quiz experience summary
7. Homepage changes
8. Required `.env` variables
9. Migration, seeding and import commands
10. Stripe webhook setup instructions
11. Local testing instructions
12. Production deployment instructions
13. Assumptions, limitations and legal/business items needing owner review
14. Test suite results (and lint/static-analysis results)