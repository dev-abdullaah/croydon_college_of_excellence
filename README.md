# Croydon College of Excellence

A Laravel 13 educational platform with Stripe-based course purchasing, lesson reading, and quiz testing.

## Stack

- **Framework**: Laravel 13
- **PHP**: 8.3+
- **Database**: MySQL 8+
- **Payments**: Stripe Checkout
- **Frontend**: Bootstrap 5, jQuery
- **Testing**: PHPUnit 12

## Features

- **Course Catalog**: Public pages for Life in the UK Course (£99) and 24 Mock Tests (£49)
- **Stripe Checkout**: Secure server-side pricing, idempotent webhooks, concurrent session lock
- **Learning Area**: Lesson cards with study/answer mode, progress tracking
- **Quiz System**: Knowledge checks, classroom mocks, mock tests with timer & scoring
- **Account Center**: Security (password), email management, active sessions
- **Email Verification**: 6-digit HMAC-hashed codes with rate limiting & lockout

## Quick Start

```bash
# Clone
git clone <repo-url>
cd croydon_college_of_excellence

# Install dependencies
composer install
npm install && npm run build

# Environment
cp .env.example .env
# Edit .env with your DB, Stripe, mail credentials

# Setup
php artisan key:generate
php artisan migrate --seed
php artisan course:import  # imports JSON course content to DB

# Serve
php artisan serve
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `APP_KEY` | Laravel app key (run `php artisan key:generate`) |
| `DB_*` | MySQL connection |
| `STRIPE_KEY` / `STRIPE_SECRET` | Stripe API keys |
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret |
| `MAIL_*` | SMTP credentials |
| `MAIL_COLLEGE_INBOX` | Recipient for contact/enrollment/assessment forms |

## Database

```bash
php artisan migrate --seed        # Fresh install with seeders
php artisan migrate:fresh --seed  # Reset & reseed
```

## Course Content

Course content lives in `database/data/` as JSON files:

```
database/data/
├── courses.json              # Course metadata
├── life-in-the-uk-course/    # Lessons, knowledge checks, mocks
│   ├── lessons.json
│   ├── knowledge-checks.json
│   └── papers.json
└── 24-mock-tests/
    ├── lessons.json
    └── papers.json
```

Import to DB (required for learning area):
```bash
php artisan course:import
```

## Testing

```bash
# Full suite (requires MySQL)
DB_CONNECTION=mysql DB_DATABASE=cce_test php artisan test

# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature
```

## Key Commands

| Command | Purpose |
|---------|---------|
| `php artisan course:import` | Import JSON course content to DB |
| `php artisan payments:doctor` | Verify Stripe config |
| `php artisan prune:unverified` | Delete old unverified accounts |
| `php artisan course:export` | Export course content to JSON |

## Architecture

- **Services**: `PurchaseService`, `StripeService`, `CourseContent`, `CatalogService`
- **Content**: JSON files → `CourseContent` singleton → DB via import
- **Auth**: Custom code-based email verification (HMAC-hashed, rate-limited)
- **Payments**: Stripe Checkout + webhook (idempotent, never downgrades paid)
- **Learning**: Lesson cards (AJAX progress), papers (full-page submit)

## Security

- HMAC-hashed verification codes (keyed with `APP_KEY`)
- Session regeneration on login
- Stripe webhook signature verification
- Server-side pricing only
- Generic error messages (no enumeration)
- CSRF on all forms
- Rate limiting on auth/mail endpoints

## Deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`
2. Configure real `MAIL_MAILER=smtp`, `STRIPE_*` keys
3. `QUEUE_CONNECTION=database` (or redis) + run queue workers
4. `CACHE_DRIVER=redis` recommended
5. Run `npm run build` for production assets
6. Set up cron for `schedule:run` (prune unverified, etc.)

## License

Proprietary - Croydon College of Excellence