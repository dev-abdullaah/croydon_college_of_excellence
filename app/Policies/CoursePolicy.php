<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Whether the user may open the paid content belonging to a course.
     *
     * The whole decision lives in User::hasPurchased(), so the policy, the
     * middleware and the views can never disagree about who owns what.
     */
    public function view(User $user, Course $course): bool
    {
        return $user->hasPurchased($course);
    }

    /**
     * Alias so `Gate::authorize('access-content', $course)` reads well.
     */
    public function accessContent(User $user, Course $course): bool
    {
        return $this->view($user, $course);
    }
}
