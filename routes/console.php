<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Housekeeping
|--------------------------------------------------------------------------
|
| Abandoned sign-ups accumulate: an account is created before the email
| address behind it has been proved, and most people who start do not
| finish. This clears out the ones that never verified and never bought
| anything, once a week of them have piled up.
|
| Daily rather than weekly because the default window is seven days, so a
| weekly run would hold a full extra week of rows on top of it. Without the
| scheduler running (cron not set up, `schedule:work` not started) nothing is
| lost, only accumulated: run `php artisan schedule:run` from cron, or the
| command by hand.
|
| See docs/PAID_COURSES.md for how to run it against a real database.
|
*/

Schedule::command('users:prune-unverified')->daily();
