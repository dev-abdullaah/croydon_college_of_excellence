<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes accounts that were created but never verified.
 *
 * Sign-up is the first step of buying, and most people who start do not
 * finish: they mistype an address, get distracted, or abandon the flow. Each
 * of those leaves a row behind that will never be used and never verified.
 *
 * Deleting them is not only tidiness. The table is the list of people who
 * might one day be a customer, and an unverified row is somebody who has
 * proved nothing about the address on it. A table that is mostly those is
 * misleading to whoever looks at it, and a row per abandoned attempt is a
 * free way to make the database grow without limit.
 *
 * What makes a row safe to delete is spelled out in doNotDelete(), and the
 * two conditions it cares about are the two ways a row can be more than a
 * leftover. The command is scheduled daily and is deliberately conservative:
 * it would rather leave a row alone than remove one that matters.
 */
class PruneUnverifiedStudents extends Command
{
    protected $signature = 'students:prune-unverified
                            {--days= : Accounts older than this many days. Defaults to courses.prune_unverified_days}
                            {--dry-run : List what would be removed without deleting anything}';

    protected $description = 'Delete accounts that never verified their email and have made no purchases';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('courses.prune_unverified_days', 7));
        $dryRun = (bool) $this->option('dry-run');

        if ($days < 1) {
            $this->components->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $candidates = Student::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('purchases')
            ->orderBy('id');

        if (! (clone $candidates)->exists()) {
            $this->components->info('Nothing to prune: no unverified accounts older than '.$days.' day(s).');

            return self::SUCCESS;
        }

        /*
         | Each row is re-checked immediately before it goes, rather than
         | deleting the whole set on the strength of a query that was true a
         | moment ago. Somebody who verified their address, or who paid, in
         | between would otherwise lose a real account, and the only evidence
         | would be a log line with a count in it.
         |
         | lazyById rather than get(): these rows are small, but the set is
         | open-ended on a site that collects abandoned sign-ups, and holding
         | the whole result set in memory to delete it is a delete that can
         | take the request out. It also pages by primary key, so a row deleted
         | underneath the cursor cannot make the walk skip one.
         */
        $deleted = 0;
        $kept = 0;

        if ($dryRun) {
            $this->components->warn('Dry run: nothing will be deleted.');
        }

        foreach ($candidates->lazyById(200) as $student) {
            if ($this->doNotDelete($student)) {
                $kept++;

                continue;
            }

            if (! $dryRun) {
                $student->delete();
            }

            $deleted++;
        }

        /*
         | Counts only, and no email addresses. This runs on a schedule, so its
         | output lands in whatever collects cron output and is kept; an
         | address in a log file is a record of who tried to sign up here and
         | never finished, which is not something to be retaining by accident.
         |
         | The count key changes with the mode rather than lying in both: a
         | log saying "deleted 12" from a run that deleted nothing is worse
         | than no log line.
         */
        Log::info($dryRun ? 'Dry run: unverified accounts found.' : 'Pruned unverified accounts.', [
            'older_than_days' => $days,
            $dryRun ? 'would_delete' : 'deleted' => $deleted,
            'kept' => $kept,
        ]);

        if ($dryRun) {
            $this->components->warn($deleted.' would be deleted, '.$kept.' kept.');

            return self::SUCCESS;
        }

        $this->components->info('Deleted '.$deleted.' unverified account(s); kept '.$kept.'.');

        return self::SUCCESS;
    }

    /**
     * Is this account now somebody we must not delete?
     *
     * Checked on the live row rather than the one loaded earlier, so a
     * verification or a payment that happened in the last second counts.
     */
    private function doNotDelete(Student $student): bool
    {
        return DB::table('students')
            ->where('id', $student->id)
            ->where(function ($query) {
                $query->whereNotNull('email_verified_at')
                    ->orWhereExists(
                        fn ($sub) => $sub->selectRaw('1')
                            ->from('purchases')
                            ->whereColumn('purchases.student_id', 'students.id')
                    );
            })
            ->exists();
    }
}
