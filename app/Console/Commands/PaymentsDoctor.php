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
    protected $signature = 'payments:doctor
                            {--expect= : The live domain this site serves, e.g. croydoncollegeofexcellence.co.uk. Tells the command this is a real site even when the .env says otherwise}';

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

        /*
         | Which environment is this, and is it the one you meant?
         |
         | This is first because it decides whether the checks below mean
         | anything. A live site whose .env still says APP_ENV=local fails
         | every other check in this command in ways that look like missing
         | configuration, when in fact the file is simply the wrong file.
         |
         | The usual cause is uploading a local .env to the server, because it
         | is committed and therefore always present in a copy of the project.
         | That single mistake produces a site that shows stack traces, builds
         | links pointing at localhost, and silently writes every email to a
         | log file instead of sending it. None of those announce themselves.
         */
        $this->components->info('Environment');

        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $url = (string) config('app.url');
        $host = parse_url($url, PHP_URL_HOST);

        /*
         * Whether this deserves production-grade scrutiny, which is not the
         * same question as what APP_ENV says.
         *
         * The failure this guards against is a developer's .env reaching the
         * server, and in exactly that case APP_ENV says `local`, so anything
         * that trusts APP_ENV to decide whether the site is live will sit down
         * and report everything fine. Worse, a local .env also carries
         * APP_URL=http://localhost, so from the command line there is no
         * evidence at all that a real site is running. Hence --expect: on a
         * live server you say what the domain is, and every check below is then
         * made against that claim instead of against a file that may be the
         * wrong one.
         */
        $expected = strtolower(trim((string) $this->option('expect')));
        $expected = rtrim(trim((string) preg_replace('#^https?://#', '', $expected)), '/');
        $expected = $expected === '' ? null : $expected;

        $isLocalHost = $host === null || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $looksLive = $expected !== null || ! $isLocalHost;

        $row(
            'APP_ENV',
            $env === 'production' || ! $looksLive,
            $env.' - '.$this->environmentAdvice($env, $looksLive)
        );

        if ($looksLive) {
            $row('APP_DEBUG', ! $debug, $debug
                ? 'ON: stack traces, including config values, are shown to visitors'
                : 'off');

            $row('APP_URL', ! $isLocalHost, $url.($isLocalHost
                ? ' - links in email, such as email verification, point at this machine'
                : ''));

            if ($expected !== null) {
                $row('Serving '.$expected, strtolower((string) $host) === $expected, (string) $host);
            }
        } else {
            $warn('APP_DEBUG', $debug ? 'on (fine anywhere but a live site)' : 'off');
            $warn('APP_URL', $url);
        }

        /*
         | Mail
         |
         | The interesting value is the transport the chosen mailer actually
         | resolved to, not the MAIL_MAILER that was asked for. config/mail.php
         | makes smtp mean "the live mailbox" only in production and the log
         | transport everywhere else, so on a live site a mailer of `smtp` that
         | reports a transport of `log` means the config was cached somewhere
         | else and every email this site sends is going to a file.
         */
        $this->newLine();
        $this->components->info('Mail');

        $mailer = (string) config('mail.default');
        $transport = (string) config('mail.mailers.'.$mailer.'.transport', $mailer);
        $sends = ! in_array($transport, ['log', 'array'], true);

        $row(
            'Mailer',
            $looksLive ? $sends : true,
            $mailer.' -> '.$transport.($sends ? '' : ' - messages are written to a log, not sent')
        );

        $from = (string) config('mail.from.address');

        $row('MAIL_FROM_ADDRESS', filled($from), $from);

        if ($looksLive && $sends) {
            $smtpHost = config('mail.mailers.'.$mailer.'.host');
            $smtpUser = config('mail.mailers.'.$mailer.'.username');

            $row('SMTP host', filled($smtpHost), (string) $smtpHost);
            $row('SMTP username', filled($smtpUser), $smtpUser
                ? $this->mask((string) $smtpUser)
                : 'no MAIL_USERNAME: the server will reject the login');
        }

        if (! $looksLive) {
            $this->line('           Mail is inert away from a live site by design: see config/mail.php.');
        }

        $this->newLine();
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

        // A live site must not quietly ship with the paywall down. It is off
        // on purpose while the material is being built, so this is a loud
        // note rather than a failure.
        if (! config('course-content.require_purchase')) {
            $this->components->warn('THE PAYWALL IS OFF: every signed-in account can open every course.');
            $this->line('           This is expected while the lessons and papers are being built.');
            $this->line('           Set COURSE_REQUIRE_PURCHASE=true before taking real payments.');
            $this->newLine();
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
     * Say whether this environment is the one the site should be running in.
     *
     * The interesting case is a live site reporting `local`, which is almost
     * always a developer's .env that reached the server. It is worth spelling
     * out, because on its own it looks like a harmless value.
     */
    protected function environmentAdvice(string $env, bool $looksLive): string
    {
        return match (true) {
            $env === 'production' => 'as expected for a live site',
            $looksLive => 'NOT production on a site serving a real domain: a local .env has been uploaded',
            $env === 'local' => 'a developer machine',
            $env === 'testing' => 'the test suite',
            default => 'unrecognised value',
        };
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
