# Paid Courses — Setup & Operations Guide

This is the operator's guide to the two paid courses on the Croydon College of
Excellence site:

| Course | Price | Includes |
| --- | --- | --- |
| **Life in the UK Course** | £99 | Lessons 1–10 (100 study cards each), Final Knowledge Checks for Lessons 1–10, 6 Classroom Mock Tests |
| **24 Mock Tests Package** | £49 | All 24 mock tests with answer keys |

They are **separate courses**. Buying one never unlocks the other, and each is
sold through its own Stripe Price.

A purchase opens one thing: a learning area where the lessons can be read and
the papers sat and marked. **Nothing is sold or served as a file.** See
[§5](#5-the-learning-area).

---

## Table of contents

1. [How it fits together](#1-how-it-fits-together)
2. [Requirements](#2-requirements)
3. [Environment variables](#3-environment-variables)
4. [Database](#4-database)
5. [The learning area](#5-the-learning-area)
6. [Stripe test setup](#6-stripe-test-setup)
7. [Webhook setup](#7-webhook-setup)
8. [Running the site locally](#8-running-the-site-locally)
9. [Testing payments locally](#9-testing-payments-locally)
10. [Running the automated test suite](#10-running-the-automated-test-suite)
11. [Going live](#11-going-live)
12. [Security notes](#12-security-notes)
13. [Troubleshooting](#13-troubleshooting)
14. [File map](#14-file-map)

---

## 1. How it fits together

```
  Homepage / course page
          │  GET /buy/{slug}                 (public, CSRF-free, no payment)
          ▼
  CheckoutController::start ──► session: the course SLUG only
          │
          ├─ guest ................. create account ──► verify email
          ├─ signed in, unverified .. verify email
          └─ signed in, verified .... checkout.review
                                            │
                                            │  POST /checkout/{slug}  (+ consent=1)
                                            ▼
                                   CheckoutController::store
                                            │
                                            ▼
                                   PurchaseService ──► StripeService ──► Stripe Checkout
                                            │  writes a `pending` purchase    │ customer pays
                                            │  + terms_accepted_at           ▼
                                            │                         POST /stripe/webhook
                                            │                                  │ verified signature
                                            │                                  ▼
                                            │                          StripeWebhookController
                                            │                                  │
                                            ▼                                  ▼
                                    purchases table ◄──────── PurchaseService → status = paid
                                            │
                                            ▼
                                   User::hasPurchased() → learning area unlocked
```

### The journey, end to end

A visitor never has to know which step they are on. The Buy button is a link, and
the server works out where the visitor actually is:

| Step | URL | What happens |
| --- | --- | --- |
| Buy | `GET /buy/{course}` (`checkout.start`) | Remembers the course **slug** and routes on: guest → create account; unverified → verify; verified → check your order; already owns it → My Account |
| Create account | `GET /register` | Shows the chosen course beside the form. The account is created but proves nothing until the code is entered |
| Verify email | `GET /email/verify` | A six-digit code is mailed. Entering it logs the customer in and carries them on |
| Check your order | `GET /checkout/{course}/review` (`checkout.review`) | Course, price and features from the database, plus the consent tick box |
| Pay | `POST /checkout/{course}` (`checkout.store`) | Refused without consent. Otherwise a Stripe Checkout Session and a redirect out to Stripe |
| Return | `GET /checkout/success` | As soon as Stripe says paid → **My Account**, with the new course highlighted. Otherwise a waiting page that re-checks itself |
| Cancel | `GET /checkout/cancel` | No payment was taken, with a retry that returns to `checkout.start` for the same course |

`checkout.start` is a safe `GET`: it writes one slug to the session and never
creates a payment. The journey also works for people who already have an
account — signing in with an intended course lands them on the check-your-order
page instead of the dashboard.

Two rules run through the whole design:

1. **Access depends on a verified payment, never on a URL.**
   The only thing that unlocks content is a row in `purchases` whose `status` is
   `paid`, and `paid` is only ever set from data Stripe itself has confirmed —
   via a signature-verified webhook, or by asking Stripe directly when the
   customer returns from Checkout. Reaching `/checkout/success?session_id=…` on
   its own grants nothing.

2. **Recording is idempotent.**
   Stripe delivers webhook events at least once. Every write is anchored on a
   unique Stripe identifier with a unique index behind it, so a replayed or
   out-of-order event updates the existing row instead of creating a second
   purchase.

### Purchase lifecycle

| Status | Meaning | Grants access? |
| --- | --- | --- |
| `pending` | Checkout started, Stripe has not confirmed money | No |
| `paid` | Stripe confirmed the payment | **Yes** |
| `failed` | Card declined | No |
| `cancelled` | Customer abandoned Checkout | No |
| `expired` | Stripe expired the session | No |
| `refunded` | Money returned | No (access revoked) |

Because access is a single `status = 'paid'` check, revoking it is a matter of
changing one column.

### The single source of truth

`config/catalog.php` describes both courses — names, copy, prices in pence and
the marketing bullets. The material itself is not described here; it lives in
the JSON content files the learning area reads.

* `CourseSeeder` copies that into the `courses` table.
* `CatalogService` falls back to the same config if the tables are missing or
  empty, so the homepage can never go blank or 500 because someone forgot to
  seed.

Edit copy and prices in `config/catalog.php`, then re-run the seeder.

---

## 2. Requirements

* PHP 8.3+ with the extensions Laravel 13 needs, plus `pdo_mysql` (or
  `pdo_sqlite` if you want to run the test suite with the default settings)
* Composer 2
* MySQL 5.7+ / MariaDB 10.3+ / PostgreSQL 10+ (the project already ships a
  MySQL `students` table; nothing exotic is required)
* A [Stripe](https://dashboard.stripe.com) account — use the **test** account
  while you are setting this up
* The [Stripe CLI](https://stripe.com/docs/stripe-cli) for local webhook
  forwarding

The Stripe PHP SDK (`stripe/stripe-php ^21.3`) is already a dependency. Cashier
is deliberately **not** used: this integration needs full control over webhook
idempotency and over exactly when a purchase row is written.

---

## 3. Environment variables

Copy `.env.example` to `.env` if you have not already, then add:

```env
# ---------------------------------------------------------------------------
# Paid courses (Stripe Checkout)
# ---------------------------------------------------------------------------

# Publishable key (safe for the browser): pk_test_... / pk_live_...
STRIPE_KEY=

# Secret key (server only, never commit this): sk_test_... / sk_live_...
STRIPE_SECRET=

# Signing secret of your webhook endpoint, shown once by Stripe when the
# endpoint is created:
STRIPE_WEBHOOK_SECRET=

# Seconds of clock drift tolerated when validating a webhook signature.
STRIPE_WEBHOOK_TOLERANCE=300

# Stripe Price ids, one per course (both one-off, both GBP):
STRIPE_COURSE_PRICE_ID=
STRIPE_MOCK_TEST_PRICE_ID=

# All courses are priced in British pounds.
STRIPE_CURRENCY=gbp

# Optional. Pin the Stripe API version; leave blank to use the version bundled
# with stripe/stripe-php.
# STRIPE_API_VERSION=2024-06-20
```

| Variable | Required | Where it comes from |
| --- | --- | --- |
| `STRIPE_KEY` | yes | Dashboard → Developers → API keys → **Publishable key** |
| `STRIPE_SECRET` | yes | Dashboard → Developers → API keys → **Secret key** (server only) |
| `STRIPE_WEBHOOK_SECRET` | yes | Shown once when you create a webhook endpoint, or by `stripe listen` |
| `STRIPE_WEBHOOK_TOLERANCE` | no | Defaults to `300` seconds |
| `STRIPE_COURSE_PRICE_ID` | yes | `price_…` id of the £99 one-off price |
| `STRIPE_MOCK_TEST_PRICE_ID` | yes | `price_…` id of the £49 one-off price |
| `STRIPE_CURRENCY` | no | Defaults to `gbp` |

No secret is hard-coded anywhere. If a key is missing the app degrades
gracefully: the buy button returns a friendly "payments are temporarily
unavailable" message and logs the reason, and the webhook endpoint returns
**500** rather than accepting an unverified event.

`.env` is tracked in this repository, so treat it as a local convenience file
only — never put live keys in it. In production, set these as real environment
variables (Hostinger/SkyPanel control panel → PHP version → environment
variables, or your web server / CI config) rather than writing them into a file
on disk.

### Checkout flow settings (`config/courses.php`)

The journey's tunables live in `config/courses.php`. Each has a working default,
so the site runs with no configuration at all; the environment variable is only
there for when you want to change one without a deploy.

| Key | Env override | Default | What it controls |
| --- | --- | --- | --- |
| `checkout_expiry_minutes` | `COURSES_CHECKOUT_EXPIRY_MINUTES` | `60` | How long a Stripe Checkout Session stays open. **Clamped to 30–1440 minutes**, because Stripe rejects anything outside that range and a mistyped value should be corrected here rather than turned into a failed payment at the moment somebody is trying to buy |
| `code_resend_cooldown_seconds` | `COURSES_CODE_RESEND_COOLDOWN` | `60` | Minimum gap between verification emails. Applies to sign-up, to signing in with an unverified account, and to the resend form |
| `success_refresh_interval_seconds` | — | `3` | How often the return-from-Stripe page re-checks while it waits for the webhook |
| `success_refresh_max_attempts` | — | `10` | How many times it re-checks before it stops and shows the support details. The `attempt` query parameter is clamped to this |
| `prune_unverified_days` | — | `7` | How long an account can stay unverified before `students:prune-unverified` may remove it |
| `terms_version` | — | `null` | Stored alongside `terms_accepted_at`. **Set this to something like `'2026-01'`** so a later change of wording can be traced |
| `consent_text` | — | *(see below)* | The wording of the tick box on the check-your-order page |

`consent_text` is the single place the consent wording lives. It is deliberately
one config value rather than text buried in a template, because **the wording
needs legal review before launch** — see the note at the end of this section.

### Cleaning up abandoned sign-ups

Account creation is the first step of buying, so every abandoned sign-up leaves
a row behind that will never be verified and never used:

```bash
php artisan students:prune-unverified --dry-run   # report only, deletes nothing
php artisan students:prune-unverified             # do it
php artisan students:prune-unverified --days=1    # clear a backlog after downtime
```

Scheduled daily in `routes/console.php`, so it only runs if the scheduler does:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

It deletes an account only if **all** of these are true: the email was never
verified, the account is older than the window, and there are no purchase rows
of any status — a pending purchase is enough to keep it, because somebody who
started paying is not a leftover. Each row is re-checked immediately before it
goes, so an account that was verified or paid for in the last second survives.
The log line carries counts only, never email addresses.

---

## 4. Database

The integration uses the database connection the project already had. If your
`.env` has no `DB_*` block, add one and create the database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=croydon_db
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

```sql
CREATE DATABASE croydon_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Keep the name in `.env` and the name you create in step with each other. A
mismatch is silent: the site runs happily against an empty database and every
page simply reports that the catalogue is empty. To confirm which one is in use:

```bash
php artisan tinker --execute="echo DB::connection()->getDatabaseName(), PHP_EOL;"
```

`php artisan payments:doctor` also catches the symptom, reporting the catalogue
as missing and exiting non-zero.

### Tables

Run the migrations:

```bash
php artisan migrate
```

This creates the tables the feature needs (the pre-existing `students` table is used
as-is):

**`courses`** — the catalogue
`id`, `name`, `slug` (unique), `badge`, `short_description`, `description`,
`price` (integer **pence**, so no floating point rounding), `currency` (3
chars, `gbp`), `features` (JSON, the marketing bullets), `stripe_price_key`
(which key in `config/stripe.php` to read), `stripe_price_id` (optional
per-course override), `is_active`, `sort_order`, timestamps.

There is no `course_documents` table: no course has files attached to it.

**`purchases`** — one row per payment attempt
`id`, `student_id`, `course_id`, `stripe_checkout_session_id` (**unique**),
`stripe_payment_intent_id` (**unique**), `stripe_customer_id`, `stripe_event_id`,
`customer_email`, `customer_name`, `amount` (pence), `currency`, `status`,
`metadata` (JSON), `paid_at`, `refunded_at`, `failure_reason`,
`terms_accepted_at`, `terms_version`, timestamps.

`terms_accepted_at` and `terms_version` are added by
`2026_10_02_011801_add_terms_accepted_to_purchases_table.php`. They record the
consent tick box on the check-your-order page, and are written **when the box is
ticked rather than when the money arrives** — so an acceptance that leads to an
abandoned or failed payment is still on file. `terms_version` comes from
`config('courses.terms_version')`, which is how you can show later which wording
somebody agreed to. Both columns are nullable and are only set when consent was
actually given; re-opening a checkout never moves `terms_accepted_at`, because
that would misrepresent when the customer agreed.

Both Stripe id columns are unique **when present**; MySQL and PostgreSQL allow
any number of `NULL`s in a unique index, which is what allows a checkout
session to be recorded before Stripe has told us anything about it. The unique
indexes, not a check-then-insert, are what make duplicate webhook deliveries
safe.

**`stripe_webhook_events`** — the idempotency ledger
`id`, `event_id` (**unique**), `type`, `livemode`, `payload`, `processed_at`,
timestamps. Every event id is claimed by inserting here; a duplicate delivery
loses the insert and is acknowledged with 200 and ignored. If the handler
throws, the claim is deleted so Stripe's retry can make progress.

**`lesson_progress`** and **`quiz_attempts`** — a learner's own state, the only
part of the learning area that needs a database. The content itself is not in
the database at all; see [§5](#5-the-learning-area).

### Seeding the catalogue

```bash
php artisan db:seed --class=CourseSeeder
```

or, on a fresh install, `php artisan migrate --seed`.

The seeder is idempotent — it matches courses on `slug`, so re-running it
updates the copy without touching any purchase a customer has already made.

`php artisan db:seed` (with no `--class`) also runs it, via `DatabaseSeeder`.

### Check the wiring at any time

```bash
php artisan payments:doctor
```

```
  INFO  Stripe credentials.

  STRIPE_KEY .............................................................. OK
           pk_test******Cdef
  STRIPE_SECRET ........................................................... OK
           sk_test******mnop
  Mode: TEST - no real money moves
  ...
  INFO  Course content.

  Lesson cards ........................................................... OK
           database/data/lesson-content.json
  Papers ................................................................. OK
           database/data/quiz-content.json
  Readable ............................................................... OK
           10 lesson(s), 1000 study card(s), 40 paper(s), 820 question(s)
  ...
  INFO  Everything is configured. Ready for TEST - no real money moves.
```

It exits non-zero if anything is missing, so it is safe to run from a deploy
script. It never prints a full key.

---

## 5. The learning area

Buying a course opens a learning area at `/my-account/courses/{course}`. A buyer
works through ten lessons reading study cards, sits the ten knowledge checks and
the six classroom papers, and is shown exactly which answers were right and
which were wrong with a score at the end. The £49 pack works the same way with
its 24 mock tests.

Access uses the `purchased` middleware, so **a URL is never enough** — typing
the address of a lesson or a paper from the other course gets a 403 (or a 404
for a slug that belongs to a different course).

### Where the content lives

The lessons and papers are **files**, not database rows. They live in two JSON
files in the repository, and the site serves them directly:

| File | Holds |
| --- | --- |
| `database/data/lesson-content.json` | 10 lessons × 100 study cards, for the £99 course |
| `database/data/quiz-content.json` | Every paper: 10 Knowledge Checks × 10 questions, 6 Classroom Mock Tests × 24 questions, 24 Mock Tests × 24 questions |

That is **10 lessons, 1 000 study cards, 40 papers and 820 questions**, every one
with its answer key and an explanation. The file is keyed by course slug; a
paper belongs to whatever course its slug sits under in the file, so the two
cannot disagree.

> **Only a learner's own progress is stored in the database.** Which lessons
> they have read (`lesson_progress`) and how they did on each paper
> (`quiz_attempts`) are keyed by course slug, lesson slug, quiz slug and
> question position — never by content row ids, because there are none.

A course is deployed by copying the code. There is no content to import and no
seeder to remember; if the files are in the repository, the site serves the
whole course.

### The `.docx` originals

The course started as four Word documents. Everything in them has been extracted
into the JSON files:

| Document it came from | Became | Course |
| --- | --- | --- |
| `Life in the UK Lesson 1-10.docx` | 10 lessons × 100 study cards | Life in the UK Course (£99) |
| `Life in the UK Lesson 1-10 Final Knowledge Checks.docx` | 10 Knowledge Checks × 10 questions | Life in the UK Course (£99) |
| `6 Classroom Mock Test.docx` | 6 Classroom Mock Tests × 24 questions | Life in the UK Course (£99) |
| `Total 24 Mock Tests Life in the UK.docx` | 24 Mock Tests × 24 questions | 24 Mock Tests Package (£49) |

> **The `.docx` files are build input, not product.** They were handed over to
> a designer and are not part of the repository. Nothing on the site needs
> them: the JSON files above are the only copy of the course, and the
> `course-files/` folder is not deployed.

### Building a server from nothing

```bash
php artisan migrate --seed
```

That seeds the catalogue and creates the two state tables. The content needs
nothing: the JSON files are already in the repository, so a fresh server is
serving all ten lessons and all forty papers before anyone logs in.

### Changing the content

**To change a lesson, a paper or one answer, edit the JSON file directly.** The
change is live on the next page load — there is no cache and no reload step.
Each file is laid out with one lesson (or one paper) per line, so a change shows
up as a one-line diff in review rather than a reformat of the whole file.

```bash
$EDITOR database/data/quiz-content.json
```

The site validates everything it reads and refuses to serve a broken file with a
message naming the file and the row, so an edit gone wrong shows up at once
instead of reaching a learner as a paper that cannot be finished.

**If the `.docx` originals do come back**, the extractor is still here and
still refuses to write a wrong course:

```bash
php artisan courses:extract --dry-run   # parse and report, write nothing
php artisan courses:extract             # parse and rewrite both JSON files
```

`--dry-run` prints what it found and every problem, and exits non-zero if there
are any. A healthy run:

```
  Lessons        10 (1000 study cards)
  knowledge_check 10 papers, 100 questions
  classroom_mock 6 papers, 144 questions
  mock_test 24 papers, 576 questions

No problems found. The documents parse cleanly.
```

### It refuses to write a wrong course

Whether it is reading the Word files or the JSON files, a write **stops before
touching anything** if the content does not line up. A course that teaches the
wrong answer is worse than a course that fails to load, so these are hard errors:

* a question does not have exactly four options
* a question has no valid correct option among them
* a question has no question text, or no explanation
* a study card has no question, or no answer

When the Word files are the source, the extractor also catches the things only a
document can reveal:

* a `.docx` is missing or unreadable
* a lesson heading is missing, or a lesson is not 1–10
* a paper has no answer key
* an answer key entry refers to a question the paper does not contain
* **the letter grid and the answer text in the key disagree**
* **the answer text no longer matches the option it points at** — the usual
  sign that options were reordered in a document and the key was not updated

A typical failure names the question and shows all three versions of the text:

```
  <ERROR> classroom_mock 5 question 14: the answer key text does not match option b.
         prompt: What is a parliamentary question?
         option: A formal promise to tell the truth to a minister or other authorised officeholder.
         answer_key: A formal question from a parliamentarian to a minister or other authorised officeholder.
```

Nothing is written in that case.

### Losing the database does not lose the course

The JSON files are the durable copy, and they are in the repository. A wiped
database, an old dump, a new server — the content travels with the code, so the
course is never lost. Only learners' progress would need rebuilding (or
accepting as lost), because that is the one thing that lives in the database.

`tests/Feature/CourseContentFilesTest.php` keeps the files honest: it checks
they hold the whole course with a complete answer key, that questions are
sittable and study cards complete, and that a broken file is refused with a
message naming exactly where it is broken.

### The learner state tables

Two tables are created by `php artisan migrate`:

**`lesson_progress`** — one row per learner per lesson read:
`id`, `student_id`, `course_slug`, `lesson_slug`, `completed_at`, timestamps.
Unique on (`student_id`, `course_slug`, `lesson_slug`), so a double-click cannot
create two rows.

**`quiz_attempts`** — one row per sitting of a paper:
`id`, `student_id`, `course_slug`, `quiz_slug`, `status`
(`in_progress`/`submitted`), `current_position`, `answers` (JSON map of question
**position** → `"a"`–`"d"`), `score`, `total`, `percentage`, `passed`,
`time_taken_seconds`, `started_at`, `submitted_at`, timestamps.

Everything is keyed by slug and position, never by a content row id, because the
content has no rows. A question is identified by its place on the paper, so a
half-finished attempt's answers follow the place, and a question moved in the
file becomes a different question at that place.

### How grading works

* Answers are saved **as the learner works through the paper**, so a refresh, a
  dropped connection or a closed tab loses nothing. Reopening the paper resumes
  it.
* **The score is always recomputed from the JSON content at the moment of
  submitting.** Nothing the browser posts can influence it, and editing a
  question afterwards cannot silently rewrite a stored result.
* The correct answer is **never sent to the browser** while a paper is in
  progress. It is only read on the server when the attempt is marked.
* Questions left blank count as wrong, as they do on the real test.
* Pass mark is **75%** — 18 of 24 on a full paper, matching the official Life in
  the UK Test. Full papers have a 45 minute limit; knowledge checks have 10.
* A result page is looked up constrained to the signed-in learner **and** the
  paper, so one learner's attempt id cannot be used to read another's answers.
* A posting that arrives after the paper was finished is rejected rather than
  quietly opening a second sitting, so a stale tab cannot manufacture an empty
  score in someone's history.

---

## 6. Stripe test setup

### 1. Get test API keys

Open the Stripe Dashboard in **test mode** (the toggle at the top right) and go
to *Developers → API keys*. Copy the **Publishable key** and **Secret key**
into `STRIPE_KEY` and `STRIPE_SECRET`. They start with `pk_test_` and
`sk_test_`.

### 2. Create two Stripe products and two prices

Each course needs **its own one-off price** — that is what stops the two
courses from ever being confused with one another.

*Go to Catalog → Products → Add product (in Stripe's dashboard, not our database):*

1. **Life in the UK Course** — add a price of **£99.00 GBP**, one-time payment.
2. **24 Mock Tests** — add a price of **£49.00 GBP**, one-time payment.

Then open each Stripe product's price and copy its `price_…` id into
`STRIPE_COURSE_PRICE_ID` and `STRIPE_MOCK_TEST_PRICE_ID` respectively.

Turn on **Active** and make sure the currency is GBP. The amounts live in
Stripe, not in the database, so they cannot be tampered with by a browser.

> If you ever change a price in Stripe, update the `price_…` id in `.env` too.
> The `courses.price` column drives what the website *displays*; the Stripe
> Price drives what Stripe actually *charges*. They should always agree.

---

## 7. Webhook setup

The webhook is the mechanism the whole system trusts. Point Stripe at:

```
POST https://your-domain.example/stripe/webhook
```

It is exempt from CSRF verification (Stripe cannot send a token) and
authenticates itself with a verified `Stripe-Signature` header instead, which
is strictly stronger than a CSRF token.

### Subscribe to these events

In the endpoint's event list, select:

| Event | What it does |
| --- | --- |
| `checkout.session.completed` | Records the purchase; marks it paid when Stripe says `payment_status = paid` |
| `checkout.session.async_payment_succeeded` | Same, for delayed payment methods |
| `checkout.session.expired` | Marks an abandoned session as expired |
| `payment_intent.succeeded` | Second confirmation path; also links the intent to the checkout row |
| `payment_intent.payment_failed` | Records a declined card |
| `charge.refunded` | Revokes access |

Any other event is logged and acknowledged with 200, so adding events to
Stripe later will not start erroring.

### Getting the signing secret

Stripe shows `whsec_…` **once**, immediately after you create the endpoint.
Copy it into `STRIPE_WEBHOOK_SECRET`. If you lose it, roll the endpoint
("Disable endpoint" then create a new one) to get a fresh one.

> Test-mode and live-mode endpoints have **different** signing secrets, and
> test-mode secrets are prefixed `whsec_…` the same way. Never point a
> production endpoint at a test secret or vice versa.

---

## 8. Running the site locally

```bash
composer install
cp .env.example .env          # if you have not already
php artisan key:generate
php artisan migrate --seed    # catalogue and the two learner-state tables
php artisan serve
```

That is the whole setup. The lessons and papers come from the JSON files in
`database/data/`, which are in the repository — no `.docx` files needed, no
content to import. `php artisan payments:doctor` confirms the content loaded and
will tell you if it did not.

---

## 9. Testing payments locally

### Forward webhooks with the Stripe CLI

Install the [Stripe CLI](https://stripe.com/docs/stripe-cli), log in with your
test account, and run:

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

It prints a signing secret on startup:

```
> Ready! You are now listening for events.
> Sign up to an account to get started: ...
✔ Listening on https://paddle-badly-...ngrok.io/
2024-01-01 12:00:00  ---> checkout.session.completed
  ✔ [Received 200]
```

Put the printed `whsec_…` into `STRIPE_WEBHOOK_SECRET`, then run
`php artisan config:clear` so Laravel picks it up.

While `stripe listen` is running you can also fire a synthetic event at your
own endpoint:

```bash
stripe trigger checkout.session.completed
stripe trigger payment_intent.payment_failed
```

To re-deliver a specific real event — the best way to test idempotency — open
*Workbench → Events* in the Stripe Dashboard, find the event and press
**Resend**. The `stripe listen` output shows the delivery attempts and their
responses; the second delivery of the same `evt_…` should be answered with
`{"message":"Already processed."}` and must not create a second purchase.

> The `payments` table and the CLI output are the two places to look when a
> payment "did not go through".

### Test cards

On the Stripe Checkout page (test mode) use:

| Card number | Outcome |
| --- | --- |
| `4242 4242 4242 4242` | **Succeeds** — the normal path |
| `4000 0000 0000 0002` | **Declined** — `payment_intent.payment_failed`, purchase recorded as `failed` |
| `4000 0025 0000 3155` | Requires **3D Secure** authentication |
| `4000 0000 0000 9995` | Succeeds but with **insufficient funds** |

Any future expiry date, any CVC and any postcode will do.

### Walking through a successful purchase

1. `php artisan serve` (port 8000) in one terminal, `stripe listen` in another.
2. Open <http://localhost:8000/>, scroll to **Prepare For The Official Life in
   the UK Test**.
3. Press **Buy Life in the UK Course — £99**. As a guest this goes to **Create
   Account**, with the course shown beside the form — you are not sent to a
   login page with the course forgotten.
4. Create the account, then type the six-digit code from the email. You are
   logged in automatically and land on **Check Your Order**.
5. Tick the consent box and press **Pay £99 securely with Stripe**.
6. Pay with `4242 4242 4242 4242`.
7. Stripe returns you to `/checkout/success`. The page asks Stripe about the
   session; because the payment genuinely completed, you are redirected to
   **My Account** with the course highlighted and a **Start learning** button —
   even if the webhook has not landed yet. If Stripe has not answered yet the
   page waits and re-checks by itself, a few seconds at a time, for up to ten
   attempts, and then says so calmly rather than spinning for ever.

Confirm it from the database:

```bash
php artisan tinker
>>> App\Models\Purchase::latest('id')->first()->toArray();
```

You should see `status => "paid"`, a `stripe_checkout_session_id`, a
`stripe_payment_intent_id`, `amount => 9900`, `currency => "gbp"`,
`paid_at`, and the `terms_accepted_at` / `terms_version` recorded when the box
was ticked.

### Not ticking the box

Press **Pay** without ticking consent and you are sent straight back to **Check
Your Order** with the reason attached. **No Stripe session is created and no
purchase row is written**, because a session opened without it would record an
acceptance that never happened.

### Walking through a failed payment

1. Start checkout again with `4000 0000 0000 0002`.
2. Stripe shows the decline and returns you to the cancel URL.
3. `purchases.status` is `failed` and **the learning area stays locked**.

### Walking through a cancelled payment

Press *Back* on the Stripe Checkout page. You land on `/checkout/cancel`, which
states that no payment was taken. No access is granted.

### The important negative tests

* Sign in as a **different** student and try the course hub the first student bought
  → **403**.
* Buy the £99 course, then try the 24 mock tests' hub → **403**.
* Try `/my-account/downloads/1` → **404**. There is no download route at all.
* Try `/course-files/Life%20in%20the%20UK%20Lesson%201-10.docx` → **404**.
* Visit `/checkout/success?session_id=cs_test_anything` without paying →
  the page says it is still confirming, and nothing is unlocked.
* Visit that URL while signed in as a **different** student → the other student's
  purchase is not shown.

---

## 10. Running the automated test suite

```bash
php artisan test
```

`tests/Feature/PaidCoursesTest.php` covers the catalogue, the homepage promo,
registration and sign-in, checkout, webhook signature verification,
idempotency, refunds, protected course access and the return trip from Stripe.
Stripe itself is mocked, so **no test ever touches the network and no real
money is involved**.

`tests/Feature/LearningAreaTest.php` covers reading lessons, sitting a paper one
question at a time, saving answers as the learner goes, grading, and the access
rules between the two courses.

`tests/Feature/CourseContentFilesTest.php` protects the durable copy of the
content: it checks the two JSON files hold the whole course with a complete
answer key, that every question is sittable and every study card complete, that
the site reads them back correctly, and that a broken file is refused with an
error naming exactly where it is broken.

Some tests read the original `.docx` files, which are not in the repository.
Those **skip** when the files are absent, so the suite is green either way —
around nine skips with the files gone.

The suite runs against an in-memory SQLite database by default (see
`phpunit.xml`), which needs the `pdo_sqlite` extension. To run it against
MySQL instead, create a test database and override the variables on the
command line — PHPUnit only sets an `<env>` value when it is not already set:

```bash
mysql -u root -p -e "CREATE DATABASE cce_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

DB_CONNECTION=mysql DB_PORT=3306 DB_DATABASE=cce_test php artisan test
```

Run just the paid-course tests with:

```bash
php artisan test --filter=PaidCoursesTest
```

---

## 11. Going live

Test mode is the default and nothing changes to go live except the keys, the
prices and the webhook secret.

1. **Create the live products.** In the Stripe Dashboard, switch off test mode
   and repeat step 2 of [§6](#6-stripe-test-setup): one Stripe product at £99.00 GBP
   and one at £49.00 GBP, both one-time payments, both active.
2. **Swap the keys.** Take the **live** publishable and secret keys
   (`pk_live_…`, `sk_live_…`) from *Developers → API keys* in live mode and set
   them in production's environment.
3. **Create a live webhook endpoint.** Add
   `https://your-domain.example/stripe/webhook` in live mode, subscribe to the
   same six events, and put **its** `whsec_…` in `STRIPE_WEBHOOK_SECRET`. The
   test and live secrets are different values — do not carry one across.
4. **Seed the catalogue** and **run the migrations** on production:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=CourseSeeder --force
   ```
5. **Verify**, then check:
   ```bash
   php artisan payments:doctor
   ```
   It must print `Mode: LIVE - real payments` and end with
   `Everything is configured.`
6. **Optimise and clear caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
7. **Place a real order end to end** and confirm:
   * the Stripe dashboard shows the payment under the correct Stripe product at the
     correct amount,
   * `purchases` has a `paid` row with the real session and intent ids,
   * the customer can open the learning area, and
   * `storage/logs/laravel.log` has no webhook errors.

### Rolling back to test mode

Set `STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET` back to the `test_…`
values and `php artisan config:clear` (or `config:cache`). Existing `paid`
purchases are unaffected.

> Payments are settled by Stripe into the NatWest business account configured
> on the Stripe account itself. This application never talks to the bank.

---

## 12. Security notes

* **Prices come from the server.** The browser posts a course slug and nothing
  else. The amount, currency and Stripe Price id are all read from the database
  and `config/stripe.php`. Posting `price=1` changes nothing — there is a test
  for exactly that.
* **Consent is required before a payment exists.** The tick box on
  check-your-order is validated as `accepted` before `beginCheckout()` is
  called, so no Stripe session and no `purchases` row can be created without
  it. What was agreed, and which version of the wording, is written to
  `terms_accepted_at` / `terms_version`.
* **The session holds slugs, never URLs.** The chosen course is remembered under
  `checkout.intended_course` as a slug and re-resolved against active courses
  on the server. Storing a URL would make the session a redirect target that
  anybody could plant; a slug cannot be pointed anywhere.
* **One payment per attempt.** A second press of Pay reuses the open Stripe
  Checkout Session instead of opening a second one, guarded by a per-student,
  per-course cache lock so two clicks arriving together still make one session.
* **No secret ever reaches a view or a log.** `STRIPE_SECRET` is read only by
  `StripeService`. Logs record ids, slugs and amounts, never keys or full
  card data. Stripe handles the card, so no card details touch this server.
* **Webhooks are verified.** Every event's `Stripe-Signature` is validated
  against the endpoint's signing secret, with a tolerance for clock drift, and
  the raw body is used for verification. A bad, missing or foreign signature is
  rejected with 403; a missing secret rejects with 500. Nothing is trusted
  before the signature checks out.
* **CSRF everywhere else.** The webhook is the only route exempt, and only
  because Stripe cannot send a token.
* **There is nothing to download.** No course sells or serves a file, so
  there is no file-handling code to get wrong and no path to traverse. The
  `.docx` originals are build input only and are not deployed.
* **Authorization on every protected route.** The `purchased` middleware
  resolves the course from the route parameter, then the `CoursePolicy`
  decides. Both call the one method that defines ownership,
  `User::hasPurchased()`.
* **Throttling.** Sign-in, registration and checkout POSTs are rate limited
  (`throttle:10,1` and `throttle:20,1`).
* **Input validation.** The success page validates the `session_id` shape
  before using it, and only ever displays a purchase belonging to the signed-in
  student. Registration and login validate and sanitise input; a failed login gives
  a deliberately vague message so the form cannot be used to discover which
  email addresses have accounts.
* **Session hygiene.** The session id is regenerated on sign in and sign out.

### The emailed verification code

The six-digit code is not stored. `User::issueVerificationCode()` writes

```php
hash_hmac('sha256', $this->getKey().'|'.$code, config('app.key'))
```

and `verificationCodeMatches()` compares with `hash_equals`. Binding the account
id into the message means two accounts sent the same six digits do not produce
the same stored value, and keying it with the app key means the stored value
cannot be reversed from a database dump alone — six digits is only a million
candidates, which a plain SHA-256 is trivially brute-forced offline.

This needs **no schema change and no migration**. It does mean that **codes
issued before the deploy stop working**, because they were stored under the old
plain-hash scheme. They expire in 15 minutes anyway, so a deploy is a good
moment to do it; anyone mid-sign-up is asked for a new code.

### Deploying the webhook securely

* The endpoint is `https://` only — Stripe will refuse a plain-HTTP endpoint in
  live mode, and the app should be behind TLS in any case.
* Do not put the webhook behind IP allow-listing. Stripe publishes its IP ranges
  and they change; signature verification is the authentication, and it is
  already in place.
* Do not add rate limiting or authentication middleware in front of it. The
  only legitimate client is Stripe, and the signature is what proves it.
* Keep `STRIPE_WEBHOOK_SECRET` in the environment, not in a committed file.
  Rotating it means rolling the endpoint in Stripe and updating the
  environment, then `php artisan config:clear`.
* Stripe retries failed deliveries for up to three days. The handler returns
  500 on an internal error and deletes its idempotency claim, so a retry will
  be processed rather than skipped.
* Monitor the endpoint in the Stripe dashboard (failed deliveries, and
  `2xx` response rate) and `storage/logs/laravel.log` for
  `Stripe webhook` lines.

---

## 13. Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| "Online payments are temporarily unavailable" | `STRIPE_SECRET` is blank. Check `.env`, then `php artisan config:clear`. |
| 500 on `/stripe/webhook`, log says "no signing secret configured" | `STRIPE_WEBHOOK_SECRET` is blank. |
| 403 on `/stripe/webhook` | The signature does not match. Usually a test secret against a live endpoint (or the reverse), or the secret was rotated. |
| Redirect to Stripe works, payment completes, but no access | The webhook is not reaching the app. Run `stripe listen` locally, and in production check the endpoint's response log in the Stripe dashboard. Watch `storage/logs/laravel.log` for `Stripe webhook handler failed`. |
| 403 in the learning area | The purchase is not `paid` — check its `status` and `paid_at`. |
| 404 on `/my-account/downloads/…` | Expected: that route was removed. Nothing is served as a file. |
| Homepage section is missing | The catalogue is empty and `config/catalog.php` returned nothing. Run `php artisan db:seed --class=CourseSeeder`. |
| Price on the site does not match Stripe | `courses.price` is what is displayed; the Stripe Price is what is charged. Update the `price_…` id in `.env` after changing a price in Stripe. |
| "No Stripe Price is configured for the … course" in the log | The course's `stripe_price_key` does not match a key in `config/stripe.php`, or the matching `.env` variable is empty. |
| "My Account" shows material but no **Start learning** | The course has no lessons or papers loaded. The JSON files in `database/data/` are missing from the repository, or a file is broken — `payments:doctor` reports which. |
| `payments:doctor` says a content file is `MISSING` | `database/data/lesson-content.json` or `quiz-content.json` is not in the repository. Restore it from version control, or rebuild both with `php artisan courses:extract` if the `.docx` files are available. |
| A lesson or paper 404s but exists | The content file was edited in a way the store refuses — check `storage/logs/laravel.log` for the `CourseContentException` naming the file and the row. Or the slug belongs to the other course: `{lesson}` and `{quiz}` are scoped to `{course}`, so a slug from the other course deliberately does not resolve. |
| An edit to a JSON file has no effect | There is no cache to clear on purpose — the files are read each request. If you ran `php artisan config:cache`, re-run it after editing `config/course-content.php` only; content edits are read live. |

---

## 14. File map

Everything added or changed for this feature:

**Configuration**
* `config/stripe.php` — keys, webhook secret, Price ids, currency
* `config/catalog.php` — the two courses: copy, prices, file mapping
* `config/courses.php` — checkout expiry, resend cooldown, success-page refresh,
  prune window, terms version, consent wording
* `.env.example` / `.env` — the `STRIPE_*` block

**Database**
* `database/migrations/2024_01_01_000001_create_courses_table.php`
* `database/migrations/2024_01_01_000003_create_purchases_table.php`
* `database/migrations/2026_10_02_011801_add_terms_accepted_to_purchases_table.php`
  — `terms_accepted_at`, `terms_version`
* `database/migrations/2024_01_01_000004_create_stripe_webhook_events_table.php`
* `database/migrations/2024_01_01_000009_create_lesson_progress_table.php`
* `database/migrations/2024_01_01_000010_create_quiz_attempts_table.php`
* `database/data/lesson-content.json`, `database/data/quiz-content.json` —
  **the course: lessons and papers, with their answer keys**
* `database/seeders/CourseSeeder.php` — the catalogue; wired into
  `DatabaseSeeder` so `migrate --seed` gives a complete site. There is no
  content seeder: the content is files, not rows.

**Domain**
* `app/Models/Course.php`, `Purchase.php`,
  `StripeWebhookEvent.php`, and `User::hasPurchased()` / `purchasedCourses()`
* `app/Models/LessonProgress.php` — which lessons a learner has read
* `app/Models/QuizAttempt.php` — one sitting of a paper per row
* `app/Services/StripeService.php` — the only place the Stripe SDK is used
* `app/Services/PurchaseService.php` — checkout, webhook handling, idempotency
* `app/Services/CatalogService.php` — read side, with a config fallback
* `app/Exceptions/PaymentException.php`

**Course content** ([§5](#5-the-learning-area))
* `app/Content/CourseContent.php` — the store: reads and validates the two JSON
  files once per request
* `app/Content/Lesson.php`, `LessonCard.php`, `Quiz.php`, `Question.php`,
  `CourseContentException.php` — the value objects the views and services use
* `app/Services/CourseContentExtractor.php` — `.docx` documents → the two JSON
  files, with the answer-key cross-checks
* `app/Services/CourseContentParser.php` — documents → lessons and papers,
  with the answer-key cross-checks
* `app/Services/CourseContentExtractionFailedException.php`
* `app/Support/DocxReader.php` — paragraph text out of a Word file
* `app/Support/ZipReader.php` — reads the zip container, no `ext-zip` needed
* `app/Support/CourseFiles.php` — the two content file paths
* `app/Support/IntendedCourse.php` — the chosen course across the journey:
  remembers the **slug**, resolves it to an active `Course`, forgets it
* `config/course-content.php` — where the store looks for the files
* `app/Services/QuizAttemptService.php` — starting, answering, marking a paper
* `app/Services/LearningService.php` — the course hub and the lesson reader

**HTTP**
* `app/Http/Controllers/CheckoutController.php` — course page, `checkout.start`
  routing, the review page, the checkout POST, and the return trip
* `app/Http/Controllers/StripeWebhookController.php` — signature verification, event ledger
* `app/Http/Controllers/CourseLearnController.php` — the course hub
* `app/Http/Controllers/LessonController.php` — reading a lesson, marking it read
* `app/Http/Controllers/QuizController.php` — sitting a paper, the result
* `app/Http/Controllers/DashboardController.php` — "My account"
* `app/Http/Controllers/HomeController.php` — homepage
* `app/Http/Controllers/Auth/LoginController.php`, `Auth/RegisterController.php`
* `app/Http/Middleware/EnsureCoursePurchased.php` — the `purchased` middleware
* `app/Policies/CoursePolicy.php`
* `routes/web.php`, `bootstrap/app.php` (middleware, routing and the CSRF
  exemption for the webhook), `app/Providers/AppServiceProvider.php` (the
  `purchased` alias, the API rate limit, the lesson/paper route bindings, and
  `/my-account` as the post-sign-in destination)

**Views** (all reuse the existing "rbt" theme and Bootstrap 5)
* `resources/views/website/partials/paid_courses.blade.php` — homepage promo
* `resources/views/website/partials/flash.blade.php` — flash messages
* `resources/views/website/partials/learn/styles.blade.php` — learning-area CSS
* `resources/views/website/pages/courses/show.blade.php`
* `resources/views/website/pages/dashboard.blade.php` — also links the learning area
* `resources/views/website/layouts/learn.blade.php` — shared learning-page chrome
* `resources/views/website/pages/learn/index.blade.php` — the course hub
* `resources/views/website/pages/learn/lesson.blade.php` — paged study cards
* `resources/views/website/pages/learn/play.blade.php` — one question at a time
* `resources/views/website/pages/learn/result.blade.php` — marks and score
* `resources/views/website/pages/auth/login.blade.php`, `register.blade.php`
* `resources/views/website/partials/checkout-steps.blade.php` — the shared
  stepper (Create account → Verify email → Check order → Pay)
* `resources/views/website/pages/checkout/review.blade.php` — the check-your-order page
* `resources/views/website/pages/checkout/success.blade.php` — the waiting page
* `resources/views/website/pages/checkout/cancel.blade.php`
* `layouts/header.blade.php`, `layouts/mobile_menu.blade.php`,
  `layouts/footer.blade.php` — account / sign-in navigation

**Tooling**
* `app/Console/Commands/PaymentsDoctor.php` — `php artisan payments:doctor`
* `app/Console/Commands/PruneUnverifiedStudents.php` — `php artisan students:prune-unverified`
* `app/Console/Commands/CoursesExtract.php` — `php artisan courses:extract`
  (only needed if the source `.docx` files come back; `--dry-run` reports
  without writing)
* `tests/Feature/PaidCoursesTest.php`, `tests/Feature/LearningAreaTest.php`,
  `tests/Feature/CheckoutJourneyTest.php`, `tests/Feature/PruneUnverifiedStudentsTest.php`,
  `tests/Feature/CourseContentFilesTest.php`

**Untouched by design**
* `course-files/` — no file was renamed, moved or edited. The catalogue and the
  extractor only ever read the existing filenames. Nothing on the site depends on
  these files: they were temporary, and the content they held is in the JSON
  files the site serves.

---

## Before you launch: three things to confirm

These are not coding tasks. They are decisions the code deliberately leaves to
the owner, and each one has a default that is a *placeholder*, not advice.

1. **The consent wording needs legal review.** The tick box on check-your-order
   reads, by default:

   > I agree to the Terms and Refund Policy. I understand I get immediate access
   > to digital content, so I lose my right to cancel within 14 days once access
   > begins.

   This is a starting point, not approved copy. Edit it in
   `config/courses.php` (`consent_text`) — nothing else needs to change — and
   set `terms_version` to something like `'2026-01'` at the same time, so you
   can later tell which wording a given customer agreed to.

2. **The policy page has to back the wording up.** The consent links to
   `/our-policy`. That page must actually cover refunds and explain the 14-day
   cancellation right being waived for digital content, because the site is
   asserting the waiver in the tick box. Check the link resolves on the live
   site, not just in a local view.

3. **Decide whether Stripe sends receipts.** `payment_intent_data.receipt_email`
   is set from the customer's address, and in live mode Stripe sends the receipt
   because of it. Check the outcome in Stripe Dashboard → Settings → Emails and
   confirm you are happy with what a customer receives. The site deliberately
   **never claims a receipt was sent** — it does not control that, and promising
   it is how people end up emailing to ask where it is.
