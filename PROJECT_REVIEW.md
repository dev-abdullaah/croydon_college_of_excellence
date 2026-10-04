# 🎓 Croydon College of Excellence — Project Review

> **Reviewed:** 4 October 2026  
> **Stack:** Laravel 13 · PHP 8.3 · MySQL · Stripe Checkout · Bootstrap 5  
> **Type:** Educational platform with paid course purchasing, lesson reading, and quiz testing

---

## Table of Contents

- [Overview](#overview)
- [✅ Strengths](#-strengths)
- [⚠️ Weaknesses](#️-weaknesses)
- [🔧 Items to Fix](#-items-to-fix)
  - [Critical](#critical)
  - [High Priority](#high-priority)
  - [Medium Priority](#medium-priority)
  - [Low Priority](#low-priority)

---

## Overview

This is a Laravel 13 application for a UK-based college that offers free information pages (courses, team, enroll, contact) and a paid e-learning area with Stripe-based checkout, lesson reading (study cards), and quiz papers. The architecture has a clean separation between the public marketing site and the authenticated/paid learning area.

---

## ✅ Strengths

### 1. Excellent Security Posture

- **Verification codes are HMAC-hashed** (`User.php` L104–117) — the plain code is never stored. The HMAC is keyed with the app key and scoped per-user, so even identical codes produce different stored values. This is significantly better than storing codes in plain text.
- **Session fixation prevention** — `LoginController` regenerates the session ID on login (L94), and invalidates the session on logout and on failed verification (L74–75).
- **Stripe webhook signature verification** — `StripeWebhookController` (L47–63) properly validates the `Stripe-Signature` header before processing any event, with idempotent processing via a unique `event_id` index.
- **No price from browser** — The amount charged is always read from the server-side `Course` model, never from request input (`CheckoutController` L109–112).
- **Verification code brute-force protection** — per-account attempt counting (`User::recordFailedVerificationAttempt()`), time-locked lockouts, code expiry, and single-use consumption after redemption.
- **Generic error messages** — Login and verification failures give deliberately vague responses (e.g., "Those credentials do not match our records") to prevent account enumeration.
- **CSRF tokens on all forms** — Every `<form method="POST">` across all Blade templates uses `@csrf`.
- **XSS protection** — All `{!! !!}` usages wrap content in `e()` for escaping (e.g., `{!! nl2br(e($item->question)) !!}`). The one exception is `$consentText` in the checkout review page, which is sourced from server-side config.

### 2. Robust Payment Integration

- **Idempotent payment recording** — `PurchaseService` handles Stripe webhook replays gracefully using `updateOrCreate` keyed on session/intent IDs with unique database indexes.
- **Concurrent checkout lock** — A cache lock (`Cache::lock()`) in `beginCheckout()` prevents double-sessions when a user rapidly clicks the pay button.
- **Session reuse** — Pending checkout sessions are reused instead of creating duplicates, checked against Stripe's live status.
- **Payment status never downgrades** — A `paid` purchase cannot be overwritten to `failed` or `pending` (`PurchaseService` L362, L432).
- **`PaymentsDoctor` artisan command** — A diagnostic command for verifying Stripe configuration health.
- **Terms consent tracking** — The `terms_accepted_at` and `terms_version` columns record exactly when and which version of terms the customer agreed to.

### 3. Well-Structured Architecture

- **Clean Service Layer** — Business logic (purchasing, quiz attempts, learning progress, content parsing) lives in dedicated service classes under `app/Services/`, not in controllers.
- **Content separated from database** — Lesson/quiz content lives in JSON files (`database/data/`), keeping the database for transactional data. The `CourseContent` singleton reads them once per request.
- **Proper use of Laravel features** — Route model binding via slug, middleware for purchase gating, singletons for expensive services, Eloquent scopes (`paid()`, `forCourse()`, `active()`, `ordered()`), proper use of casts and `$fillable`.
- **Custom route parameter binding** — `AppServiceProvider::bindCourseContent()` scopes lesson/quiz lookups to their parent course, preventing cross-course access.

### 4. Thorough Testing

- **8,267 lines of tests** across 8 feature test files — an excellent test suite covering checkout journeys, email verification, paid courses, learning area, content files, and account management.
- **`InteractsWithCourseContent` test trait** — Shared test helpers for consistent course content setup.
- **Edge case coverage** — Tests for concurrent webhook delivery, session reuse, abandoned checkouts, and double-submission.

### 5. Thoughtful Code Comments

- The codebase has **unusually good documentation**. Critical security decisions (why codes are hashed, why error messages are vague, why sessions are invalidated) are explained inline with clear reasoning. This makes the code maintainable and auditable.

### 6. Solid User Flow

- **Unverified account re-registration** — Users who abandoned registration can re-register with the same email (the password is updated, a fresh code is sent), rather than being told the email is "taken" (`RegisterController` L47–87).
- **Intended course preservation** — The course a user was trying to buy survives the registration → verification → login flow (`IntendedCourse` helper).

---

## ⚠️ Weaknesses

### 1. `.env` File Is Tracked in Git

The `.gitignore` has `.env` **commented out** (`#.env`). This means the `.env` file — containing the `APP_KEY`, database credentials (`DB_PASSWORD=665422`), and the structure for Stripe keys — is committed to version control. While the live Stripe keys are intentionally left blank, the app key and database password are exposed to anyone with repository access.

### 2. Massive Route Duplication

`routes/web.php` contains **~35 nearly identical closure routes** for static course pages (e.g., `/regular-english`, `/regular-math`, `/send-english`, `/send-math`). Each one just returns a view with no logic. This should be a single parameterized route.

### 3. No Admin Panel / Back-Office

There is no admin interface. Course management, user management, purchase oversight, and content updates all require direct database access or artisan commands. For a live educational platform, this is a significant operational gap.

### 4. Duplicate Password/Email Change Logic

Password change logic exists in **both** `DashboardController` and `AccountCenterController`. The `DashboardController` has `updatePassword()` (L152–173) and `updateEmail()` (L188–216) methods, while `AccountCenterController` also has `updatePassword()` (L262–283). This violates DRY and risks the two falling out of sync.

### 5. No Unit Tests

The `tests/Unit/` directory contains only the default `ExampleTest` (16 lines). All business logic in models and services (verification code handling, purchase status transitions, content parsing) is only tested through feature tests. Targeted unit tests would be faster and more precise.

### 6. Public-Facing Mail Controllers Lack Rate Limiting

`ContactMailController`, `AssesmentMailController`, `EnrollMailController`, and `TutorMailController` have no rate limiting at the route or controller level. An attacker could trigger unlimited outbound emails to `info@croydoncollegeofexcellence.co.uk`, causing spam and potentially getting the domain blacklisted.

### 7. Hardcoded Recipient Email

All mail controllers hardcode `info@croydoncollegeofexcellence.co.uk` as the recipient. This should be a config/env value for easier management across environments.

### 8. Heavy Frontend Asset Loading

The master layout loads **16 CSS files** and **30+ JavaScript files** synchronously, many of which are not needed on every page (e.g., `jodit.min.js`, `plyr.js`, `countdown.js`, `isotop.js`). This severely impacts page load performance.

### 9. Missing Accessibility (a11y)

- Multiple `<img>` tags in footer and course pages are **missing `alt` attributes entirely**.
- No ARIA labels on interactive elements (dark mode switcher, mobile menu toggle).
- The dark/light mode switcher uses `javascript: void(0)` links without keyboard accessibility.

### 10. Spelling Errors in Code

- `AssesmentMailController` / `assesment.send` — should be "Assessment"
- `free_assesment.blade.php` — should be "free_assessment"
- These propagate into URLs (`/free-assesment`) and route names, meaning fixing them later is a breaking change.

### 11. `.bak` File in Production Code

`app/Providers/AppServiceProvider.php.bak` is a backup file sitting in the app directory. It should not be in version control.

### 12. Unused/Dead Code in `AppServiceProvider`

- `useBootstrapPagination()` (L55–58) and `limitApiRequests()` (L63–68) are defined as `protected` methods but **never called** from `boot()`.
- `Paginator::useBootstrapFive()` is called **directly in `boot()`** (L128) instead of through the defined method.
- The Route macros for `lesson` and `quiz` defined at L133–147 appear unused — the actual binding is done by `bindCourseContent()` which is also never called from `boot()`.

---

## 🔧 Items to Fix

### Critical

| # | Issue | Location | Detail |
|---|-------|----------|--------|
| 1 | **`.env` committed to Git** | `.gitignore` L13 | Uncomment `.env` from `.gitignore`. Rotate the `APP_KEY` and `DB_PASSWORD` immediately. Use `git rm --cached .env` to remove it from history. |
| 2 | **No rate limiting on public mail forms** | `ContactMailController`, `AssesmentMailController`, `EnrollMailController`, `TutorMailController` routes in `web.php` | Add `->middleware('throttle:3,1')` to the four `/send` routes. Without this, the mail endpoints can be abused to spam the college inbox or overwhelm the mail server. |
| 3 | **`$consentText` rendered unescaped** | `checkout/review.blade.php` L113 | `{!! $consentText !!}` outputs raw HTML from `config('courses.consent_text')`. While this is server-controlled config, it should use `{{ }}` or `{!! Str::markdown(...) !!}` to be safe by default — especially if the config is ever moved to a database or CMS. |

### High Priority

| # | Issue | Location | Detail |
|---|-------|----------|--------|
| 4 | **Consolidate 35+ static route closures** | `routes/web.php` L163–320+ | Replace all individual course-view routes with a parameterized approach, e.g. `Route::get('/regular-{subject}', fn($subject) => ...)` with a whitelist, or a single controller method. |
| 5 | **Eliminate duplicate password/email logic** | `DashboardController` L144–237 vs `AccountCenterController` L262–283 | Pick one location (AccountCenterController) and redirect the old dashboard routes there. Having two diverging implementations is a maintenance risk and a security surface. |
| 6 | **Remove `.bak` file** | `app/Providers/AppServiceProvider.php.bak` | Delete this backup file from the repository. |
| 7 | **Clean up `AppServiceProvider`** | `app/Providers/AppServiceProvider.php` | Either call `useBootstrapPagination()`, `limitApiRequests()`, and `bindCourseContent()` from `boot()`, or remove the dead methods. Currently, `bindCourseContent()` is not invoked, meaning the content-scoping logic described in its docblock may not be active. |
| 8 | **Move hardcoded email to config** | All mail controllers | Replace `'info@croydoncollegeofexcellence.co.uk'` with `config('mail.college_inbox')` or similar, across `ContactMailController`, `AssesmentMailController`, `EnrollMailController`, `TutorMailController`. |

### Medium Priority

| # | Issue | Location | Detail |
|---|-------|----------|--------|
| 9 | **Add missing `alt` attributes** | `footer.blade.php`, `courses_send.blade.php`, others | All `<img>` tags must have descriptive `alt` text for accessibility (WCAG 2.1 compliance). The footer logo images and course category images are the worst offenders. |
| 10 | **Lazy-load or conditionally load JS/CSS** | `layouts/master.blade.php` L35–52 | Use `defer` on scripts, load page-specific assets via `@push('scripts')` stacks, and consider bundling with Vite (which is already configured but seemingly underutilized). |
| 11 | **Add unit tests** | `tests/Unit/` | Write unit tests for: `User` verification code logic, `PurchaseService` status transitions, `CourseContent` JSON parsing, `Course::formattedPrice()`. These are fast, targeted, and complement the existing feature tests. |
| 12 | **Fix spelling: "Assesment" → "Assessment"** | Controller, Mail, routes, views | This is a breaking URL change, so add a `Route::redirect('/free-assesment', '/free-assessment')` and update all references. It's better to fix this now than after SEO and bookmarks accumulate. |
| 13 | **Dynamic page `<title>` tags** | `layouts/master.blade.php` L7 | Every page shows `<title>Croydon College of Excellence</title>`. Add a `@yield('title', 'Croydon College of Excellence')` and set `@section('title')` per page for SEO. |
| 14 | **`robots.txt` is wide open** | `public/robots.txt` | `Disallow:` (empty) allows all crawlers everywhere. Auth-protected routes like `/my-account`, `/checkout`, `/login` should be disallowed to prevent useless crawl traffic and index pollution. |
| 15 | **Missing `README.md`** | Project root | No README exists. A new developer would have no setup instructions, no architecture overview, and no onboarding path. |

### Low Priority

| # | Issue | Location | Detail |
|---|-------|----------|--------|
| 16 | **No model factories for non-User models** | `database/factories/` | Only `UserFactory.php` exists. Add factories for `Course`, `Purchase`, `QuizAttempt`, `LessonProgress` to make test setup faster and more expressive. |
| 17 | **No database indexes on frequently queried columns** | Migrations | `lesson_progress.user_id` + `course_slug` and `quiz_attempts.user_id` + `course_slug` combinations should have composite indexes for dashboard performance. |
| 18 | **`LoginHistory` model has no factory** | `app/Models/LoginHistory.php` | The Account Center and Dashboard both query login history. A factory would make testing these views simpler. |
| 19 | **No queued mail** | `QUEUE_CONNECTION=sync` in `.env` | All emails (contact, enrollment, verification codes) are sent synchronously, blocking the HTTP response. Switch to a queue driver (e.g., `database`) for production. |
| 20 | **Font Awesome loaded twice** | `master.blade.php` L35, L43 | Both CDN Font Awesome (`cdnjs` L35) and a local copy (`fontawesome.min.css` L43) are loaded. Remove one. |
| 21 | **Missing `rel="noopener"` on external links** | `home.blade.php` L31–49 | Social media links to Facebook, Instagram, LinkedIn open without `target="_blank"` but also lack `rel="noopener noreferrer"` for security. |
| 22 | **No pagination for login history** | `AccountCenterController` L64–67, `DashboardController` L46–49 | Login history is limited to 10 with `->limit(10)` but has no pagination. A user with many sessions can't see older entries. |
| 23 | **Consider adding a `CacheTag` or Redis for production** | `.env` | `CACHE_DRIVER=file` is fine for development but sluggish under load. Consider Redis for production. |

---

## Summary Scorecard

| Area | Rating | Notes |
|------|--------|-------|
| **Security** | ⭐⭐⭐⭐⭐ | Exemplary. HMAC-hashed codes, session fixation prevention, generic errors, Stripe signature verification, no price from browser. |
| **Architecture** | ⭐⭐⭐⭐ | Good service layer, clean content separation. Loses a star for the route duplication and duplicate logic. |
| **Code Quality** | ⭐⭐⭐⭐ | Excellent comments and documentation. Some dead code and a `.bak` file bring it down slightly. |
| **Testing** | ⭐⭐⭐⭐ | Strong feature tests. Needs unit tests and more factories. |
| **Frontend** | ⭐⭐⭐ | Functional but bloated asset loading, poor accessibility, static page titles. |
| **DevOps / Config** | ⭐⭐ | `.env` in git is the biggest issue. No CI/CD, no README, sync mail, file cache. |
| **Scalability** | ⭐⭐⭐ | Fine for current traffic. Would need queue workers, Redis, and asset bundling to scale. |

> **Overall: A solid, security-conscious Laravel application with strong business logic — held back by frontend performance, accessibility gaps, and a few configuration/hygiene issues that should be addressed before production launch.**
