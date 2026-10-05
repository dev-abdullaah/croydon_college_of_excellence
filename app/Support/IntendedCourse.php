<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Facades\Session;

/**
 * Remember the course a visitor clicked Buy for, using only the slug.
 *
 * Never store or follow a URL. Always re-resolve the slug to an active Course
 * before using it.
 *
 * The reason this is a slug and not a path is the same reason the login flow
 * has to be careful about `session()->invalidate()`. A URL in the session is
 * an open redirect waiting to happen: anything that can write to it - another
 * part of this app, a shared session, a later refactor - can aim the customer
 * at a host of somebody else's choosing. A slug is not a location. It only
 * means "this course, on this site", and it is re-resolved through the database
 * on the way out, so a slug for a course that has been retired or deactivated
 * resolves to nothing rather than to somewhere unexpected.
 */
class IntendedCourse
{
    public const SESSION_KEY = 'checkout.intended_course';

    /**
     * Remember a course slug for the checkout flow.
     */
    public static function remember(string $slug): void
    {
        Session::put(self::SESSION_KEY, $slug);
    }

    /**
     * Get the remembered slug without removing it.
     */
    public static function peek(): ?string
    {
        $slug = Session::get(self::SESSION_KEY);

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * Get and remove the remembered slug.
     */
    public static function pull(): ?string
    {
        $slug = self::peek();

        self::forget();

        return $slug;
    }

    /**
     * Forget any remembered course slug.
     */
    public static function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Check if a course slug is remembered.
     */
    public static function has(): bool
    {
        return self::peek() !== null;
    }

    /**
     * The remembered course, re-resolved from the database.
     *
     * The only way to read this class's contents. Anything in the session can
     * be stale, forged or simply left over from three purchases ago, so the
     * slug is treated as a hint and the answer only exists if it currently
     * names an active course.
     */
    public static function resolve(): ?Course
    {
        $slug = self::peek();

        if ($slug === null) {
            return null;
        }

        return Course::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * The remembered course, if this user still has to buy it.
     *
     * The one question three places need answered identically: where does
     * somebody go once they are verified? A course they already own is not
     * worth a review page, and a slug that no longer resolves to an active
     * course is worth nothing at all. Answering it in one place keeps those
     * three from drifting apart, and keeps the check for "have they paid"
     * pointing at the single `hasPurchased` the rest of the app uses.
     *
     * Does not consume the memory - the slug survives to keep the flow intact
     * if they back out of the review page.
     */
    public static function resolveIfUnowned(?Student $student): ?Course
    {
        $course = self::resolve();

        if ($course === null || $student === null) {
            return $course;
        }

        return $student->hasPurchased($course) ? null : $course;
    }
}
