<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Student;

class CoursePolicy
{
    /**
     * Whether the student may open the paid content belonging to a course.
     *
     * The whole decision lives in Student::hasPurchased(), so the policy, the
     * middleware and the views can never disagree about who owns what.
     */
    public function view(Student $student, Course $course): bool
    {
        return $student->hasPurchased($course);
    }

    /**
     * Alias so `Gate::authorize('access-content', $course)` reads well.
     */
    public function accessContent(Student $student, Course $course): bool
    {
        return $this->view($student, $course);
    }
}
