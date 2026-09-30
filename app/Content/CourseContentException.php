<?php

namespace App\Content;

use RuntimeException;

/**
 * Something is wrong with the course content files.
 *
 * Thrown while reading them, so a mistake is a loud, immediate failure naming the
 * file and the place in it, rather than a learner meeting a paper with a missing
 * question or an answer key pointing at nothing.
 */
class CourseContentException extends RuntimeException
{
    /**
     * @param  string  $where  the file, and the place in it
     */
    public static function unreadable(string $where, string $why): self
    {
        return new self("The course content at [{$where}] could not be read: {$why}");
    }

    public static function invalid(string $where, string $why): self
    {
        return new self("The course content at [{$where}] is not usable: {$why}");
    }
}
