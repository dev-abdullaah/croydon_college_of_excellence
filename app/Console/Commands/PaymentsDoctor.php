<?php

namespace App\Console\Commands;

use App\Content\CourseContent;
use App\Content\CourseContentException;
use App\Models\Course;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Reports whether the paid-course integration is ready to take money.
 *
 * Almost every support ticket about a "the customer paid but cannot download"
 * problem turns out to be one of the things this command checks, so it is
 * worth running before every deploy and after every `.env` change.
 */
class PaymentsDoctor extends Command
{
    protected $signature = 'payments:doctor';

    protected $description = 'Check that the Stripe paid-course integration is configured and ready';

    public function handle(StripeService $stripe, CourseContent $content): int
    {
        $failures = 0;

        $row = function (string $label, bool $ok, string $detail = '') use (&$failures) {
            $this->components->twoColumnDetail(
                $label,
                $ok ? '<fg=green>OK</>' : '<fg=red>MISSING</>'
            );

            if ($detail !== '') {
                $this->line('           '.$detail);
            }

            if (! $ok) {
                $failures++;
            }
        };

        // Something worth knowing that does not stop the site working.
        $warn = function (string $label, string $detail = '') {
            $this->components->twoColumnDetail($label, '<fg=yellow>NOTE</>');

            if ($detail !== '') {
                $this->line('           '.$detail);
            }
        };

        $this->components->info('Stripe credentials');

        $key = (string) config('stripe.key');
        $secret = (string) config('stripe.secret');

        $row('STRIPE_KEY', filled($key), $key ? $this->mask($key) : 'pk_test_... from the Stripe dashboard');
        $row('STRIPE_SECRET', filled($secret), $secret ? $this->mask($secret) : 'sk_test_... server only');

        $mode = match (true) {
            $key === '' || $secret === '' => null,
            $stripe->usingLiveKeys() => 'LIVE - real payments',
            default => 'TEST - no real money moves',
        };

        $this->line('  Mode: '.($mode ?? 'unknown'));

        if (app()->environment('production') && $mode !== 'LIVE - real payments') {
            $this->components->warn('This is a production environment but the keys are not live.');
        }

        if (app()->environment('local', 'testing') && $mode === 'LIVE - real payments') {
            $this->components->warn('Live keys are configured outside production. Be careful.');
        }

        $this->newLine();
        $this->components->info('Webhook');

        $webhookSecret = (string) config('stripe.webhook.secret');

        $row('STRIPE_WEBHOOK_SECRET', filled($webhookSecret), $webhookSecret ? $this->mask($webhookSecret) : 'shown once, when the endpoint is created');
        $row('route '.route('stripe.webhook', [], false), true, 'POST');

        $this->newLine();
        $this->components->info('Catalogue');

        if (! Schema::hasTable('courses')) {
            $this->components->error('The courses table does not exist. Run: php artisan migrate --seed');

            return self::FAILURE;
        }

        $courses = Course::ordered()->get();

        if ($courses->isEmpty()) {
            $this->components->error('The catalogue is empty. Run: php artisan db:seed');

            return self::FAILURE;
        }

        foreach ($courses as $course) {
            $priceId = $course->stripePriceId();

            $row(
                $course->name.' ('.$course->slug.')',
                filled($priceId),
                $course->formattedPrice().' - '.($priceId ?: 'no STRIPE_*_PRICE_ID')
            );
        }

        $this->newLine();

        // The learning content is what a buyer actually gets. It is not in the
        // database: it is the JSON files in the repository, so this reads them
        // and reports what a learner would actually find.
        $this->components->info('Course content');

        $files = [
            'Lesson cards' => config('course-content.lessons'),
            'Papers' => config('course-content.quizzes'),
        ];

        foreach ($files as $label => $configured) {
            $path = is_string($configured) ? (str_starts_with($configured, '/') ? $configured : base_path($configured)) : null;

            $row(
                $label,
                $path !== null && is_file($path),
                $path ?? 'no path configured in config/course-content.php'
            );
        }

        try {
            $loaded = $content->summary();
        } catch (CourseContentException $e) {
            $row('Readable', false, $e->getMessage());

            $loaded = null;
        }

        if ($loaded !== null) {
            $row(
                'Readable',
                true,
                "{$loaded['lessons']} lesson(s), {$loaded['cards']} study card(s), "
                ."{$loaded['quizzes']} paper(s), {$loaded['questions']} question(s)"
            );

            foreach ($courses as $course) {
                $lessons = $content->lessons($course->slug)->count();
                $papers = $content->quizzes($course->slug)->count();

                $row(
                    '  '.$course->slug,
                    $lessons > 0 || $papers > 0,
                    "{$lessons} lesson(s), {$papers} paper(s)"
                );
            }
        }

        $this->newLine();

        if ($failures > 0) {
            $this->components->error($failures.' problem(s) found. See docs/PAID_COURSES.md.');

            return self::FAILURE;
        }

        $this->components->info('Everything is configured. Ready for '.$mode.'.');

        return self::SUCCESS;
    }

    /**
     * Show enough of a key to identify it, never enough to use it.
     */
    protected function mask(string $value): string
    {
        $length = strlen($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 7).str_repeat('*', 6).substr($value, -4);
    }
}
