<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Purchase;
use App\Models\Student;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The daily clean-up of accounts that were started and never finished.
 *
 * The narrowness is the whole point. An account row here is the record of
 * somebody who typed an address into a form, so deleting one that turns out
 * to matter - a customer who verified late, or paid - is worse than leaving
 * rows behind. So every test here is really about a row that must survive.
 */
class PruneUnverifiedStudentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_only_old_unverified_accounts_with_no_purchases(): void
    {
        config(['courses.prune_unverified_days' => 7]);

        $stale = $this->unverified('stale@example.com', 30);
        $fresh = $this->unverified('fresh@example.com', 2);

        $verifiedButOld = $this->unverified('verified@example.com', 30);
        $verifiedButOld->forceFill(['email_verified_at' => now()->subMonth()])->save();

        $unverifiedWithPurchase = $this->unverified('bought@example.com', 30);
        $this->purchaseFor($unverifiedWithPurchase);

        $this->artisan('students:prune-unverified')->assertSuccessful();

        $this->assertDatabaseMissing('students', ['email' => 'stale@example.com']);

        // The four reasons to keep an account.
        $this->assertDatabaseHas('students', ['email' => 'fresh@example.com']);
        $this->assertDatabaseHas('students', ['email' => 'verified@example.com']);
        $this->assertDatabaseHas('students', ['email' => 'bought@example.com']);
    }

    /**
     * A purchase of any status is enough to keep the account.
     *
     * The interesting case is the pending one: somebody who started paying and
     * never finished has a row that looks exactly like a row to be cleaned up,
     * and deleting it would throw away the only trace that they ever tried.
     */
    public function test_an_account_with_any_purchase_survives_even_if_unpaid(): void
    {
        config(['courses.prune_unverified_days' => 7]);

        $student = $this->unverified('abandoned@example.com', 30);

        $this->purchaseFor($student, Purchase::STATUS_PENDING);

        $this->artisan('students:prune-unverified')->assertSuccessful();

        $this->assertDatabaseHas('students', ['email' => 'abandoned@example.com']);
    }

    /**
     * Exactly on the boundary. Older than N days goes; exactly N does not,
     * because "older than" is what the config claims to mean.
     */
    public function test_the_cut_off_is_inclusive_of_older_accounts_only(): void
    {
        config(['courses.prune_unverified_days' => 7]);

        $exactly = $this->unverified('exactly@example.com', 7);
        $older = $this->unverified('older@example.com', 8);

        $this->artisan('students:prune-unverified')->assertSuccessful();

        $this->assertDatabaseHas('students', ['email' => 'exactly@example.com']);
        $this->assertDatabaseMissing('students', ['email' => 'older@example.com']);
    }

    /**
     * A dry run reports without touching anything. It has to be safe to run
     * against a real database while somebody works out how many rows there
     * are.
     */
    public function test_a_dry_run_deletes_nothing(): void
    {
        config(['courses.prune_unverified_days' => 7]);

        $this->unverified('stale@example.com', 30);

        $this->artisan('students:prune-unverified --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('students', ['email' => 'stale@example.com']);
    }

    /**
     * The window is overridable, which is how you would clear out a backlog
     * after a long period of the scheduler not running.
     */
    public function test_the_window_can_be_overridden(): void
    {
        config(['courses.prune_unverified_days' => 7]);

        $student = $this->unverified('stale@example.com', 3);

        // Inside the default seven-day window...
        $this->artisan('students:prune-unverified')->assertSuccessful();
        $this->assertDatabaseHas('students', ['email' => 'stale@example.com']);

        // ...but outside a one-day window.
        $this->artisan('students:prune-unverified --days=1')->assertSuccessful();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_a_nonsense_window_is_refused_rather_than_deleting_everything(): void
    {
        $this->unverified('stale@example.com', 30);

        $this->artisan('students:prune-unverified --days=0')->assertFailed();

        $this->assertDatabaseHas('students', ['email' => 'stale@example.com']);
    }

    /**
     * It runs on its own, on a schedule. A command nobody has wired up is
     * just a file.
     */
    public function test_it_is_scheduled_daily(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'students:prune-unverified'));

        $this->assertCount(1, $events, 'The command should be scheduled exactly once.');
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    private function unverified(string $email, int $daysOld): Student
    {
        return Student::factory()->unverified()->create([
            'email' => $email,
            'created_at' => now()->subDays($daysOld),
            'updated_at' => now()->subDays($daysOld),
        ]);
    }

    private function purchaseFor(Student $student, string $status = Purchase::STATUS_PAID): Purchase
    {
        $courseId = Course::query()->value('id')
            ?? Course::query()->create([
                'name' => 'A course',
                'slug' => 'a-course',
                'price' => 1000,
                'currency' => 'gbp',
                'is_active' => true,
            ])->id;

        return Purchase::create([
            'student_id' => $student->id,
            'course_id' => $courseId,
            'amount' => 1000,
            'currency' => 'gbp',
            'status' => $status,
        ]);
    }
}
