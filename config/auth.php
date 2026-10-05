<?php

use App\Models\Student;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'students',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent student provider.
    |
    | All authentication drivers have a student provider. This defines how the
    | students are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your student's data.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'students',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Student Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a student provider. This defines how the
    | students are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your student's data.
    |
    | If you have multiple student tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'students' => [
            'driver' => 'eloquent',
            'model' => Student::class,
        ],

        // 'students' => [
        //     'driver' => 'database',
        //     'table' => 'students',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verifying an Email Address by Code
    |--------------------------------------------------------------------------
    |
    | Verification is by mailed code rather than by link. A signed URL cannot
    | be guessed; a six digit code can be, so these numbers are what make it
    | safe rather than merely convenient.
    |
    | `expire` is in minutes and is deliberately short. A link could afford an
    | hour because whoever intercepted it still had to guess nothing; a code is
    | guessable, so it gets spent quickly.
    |
    | `max_attempts` is the real defence. A million possible codes sounds safe
    | until you divide it by the number of tries allowed: at these settings an
    | attacker gets five guesses and then waits out `lockout_minutes`. Raising
    | the code length without lowering this would undo the point.
    |
    */

    'verification_code' => [
        'expire' => env('VERIFY_CODE_EXPIRE', 15),
        'max_attempts' => env('VERIFY_CODE_MAX_ATTEMPTS', 5),
        'lockout_minutes' => env('VERIFY_CODE_LOCKOUT_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one student table or model in the application and you want to have
    | separate password reset settings based on the specific student types.
    |
    | The expiry time is the number of minutes that each reset token will
    | be considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a student must wait before
    | generating more password reset tokens. This prevents the student from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'students' => [
            'provider' => 'students',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | times out and the student is prompted to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => 10800,

];
