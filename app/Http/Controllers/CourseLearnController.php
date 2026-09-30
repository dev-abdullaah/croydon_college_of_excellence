<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\LearningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The learning area for a course a learner owns.
 *
 * Everything here sits behind the `purchased` middleware, so a request only
 * reaches these methods if a `paid` purchase exists for the signed-in user.
 * The controllers resolve the course from the URL and never trust anything
 * about it from the browser.
 */
class CourseLearnController extends Controller
{
    public function __construct(private readonly LearningService $learning) {}

    /**
     * The course hub: every lesson, every paper, and how far through them
     * this learner has got.
     */
    public function index(Request $request, Course $course): View
    {
        $overview = $this->learning->courseOverview($request->user(), $course);

        return view('website.pages.learn.index', [
            'course' => $course,
            'lessons' => $overview['lessons'],
            'read' => $overview['read'],
            'groups' => $overview['groups'],
            'progress' => $overview['progress'],
            'best' => $overview['best'],
        ]);
    }
}
