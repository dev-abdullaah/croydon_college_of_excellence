<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover", "roundrobin"
    |
    */

    'mailers' => [
        /*
         * The live mailbox, and only the live mailbox.
         *
         * This one mailer is what production sends through. Everywhere else it
         * resolves to the log transport instead, and the SMTP credentials are
         * not even read.
         *
         * That is deliberate. This project's .env is committed, so the
         * production mailbox password is a file that travels: it gets copied
         * between machines, sits in backups, and ends up in whatever editor or
         * terminal scrollback is to hand. The next person to open a local .env
         * and see a real mailbox password has no way to tell whether the next
         * test registration is going to email a stranger.
         *
         * So a stray copy of the credentials cannot cause a send. A local
         * registration writes the email - including its verification link - to
         * the log, and nothing leaves the machine. The switch is on the
         * environment rather than on a value someone has to remember to set,
         * so there is no state in which local mail quietly becomes real.
         *
         * The branch is resolved when the config is loaded, which is what makes
         * it safe under `php artisan config:cache`: the cached copy is built on
         * the server, in production, with the server's own environment. Never
         * upload a bootstrap/cache/ directory built elsewhere. `payments:doctor`
         * reports the mailer this actually resolved to, so a cache copied from
         * a local machine is caught before launch rather than after.
         */
        'smtp' => env('APP_ENV', 'production') === 'production'
            ? [
                'transport' => 'smtp',
                'url' => env('MAIL_URL'),
                'host' => env('MAIL_HOST'),
                'port' => env('MAIL_PORT', 465),
                'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
                'username' => env('MAIL_USERNAME'),
                'password' => env('MAIL_PASSWORD'),
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ]
            : [
                'transport' => 'log',
                'channel' => env('MAIL_LOG_CHANNEL'),
            ],

        /*
         * A mailbox on your own machine, for when you want to read the email as
         * a message rather than as a line in a log file.
         *
         * Not used by default: it needs a Mailpit binary listening on 1025,
         * which nothing installs for you. It is here because the alternative
         * when you want to eyeball an email is to point MAIL_HOST at the live
         * mailbox, which is the thing this file exists to prevent.
         *
         * Note this is hard-coded to the loopback address. It is not a
         * configurable remote host, so like the mailer above it cannot reach
         * anything off the machine.
         */
        'mailpit' => [
            'transport' => 'smtp',
            'host' => '127.0.0.1',
            'port' => 1025,
            'encryption' => null,
            'username' => null,
            'password' => null,
        ],

        /*
         * Real SMTP on purpose, on any machine.
         *
         * The mailer above this one cannot send off a development machine, and
         * that is deliberate: it turns to the log transport unless APP_ENV is
         * production. It is the right default, because a checkout that carries
         * mailbox credentials should not post to a live inbox the moment
         * somebody registers.
         *
         * This one is the escape hatch for when you genuinely want the message
         * to arrive - testing verification against a real inbox, or checking
         * SPF and DKIM on mail you have to see land. It is not the default, so
         * it is never chosen by accident; you have to ask for it by name in
         * MAIL_MAILER.
         *
         * Two rules keep it from undoing the protection above.
         *
         * First, it reads the same MAIL_HOST and MAIL_USERNAME variables the
         * production mailer reads, and the tracked .env does not set them. Put
         * the credentials in .env.local, which is untracked, or export them in
         * your shell. A password in .env is a password in git.
         *
         * Second, it sends from MAIL_FROM_ADDRESS, which has to be an address
         * on the domain the relay is authorised for. Sending as hello@example.com
         * through this relay is refused by the receiving end or lands in spam,
         * so set it to the mailbox you are sending from.
         */
        'smtp-live' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT', 465),
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => null,
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | College Inbox Recipient
    |--------------------------------------------------------------------------
    |
    | The email address that receives all public-facing form submissions
    | (contact, assessment, enrollment, tutor). Centralised here so it can
    | be changed per environment without editing controllers or mail classes.
    |
    */

    'college_inbox' => env('MAIL_COLLEGE_INBOX', 'info@croydoncollegeofexcellence.co.uk'),

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
