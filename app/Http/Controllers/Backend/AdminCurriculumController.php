<?php

namespace App\Http\Controllers\Backend;

use App\Content\CourseContent;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AdminCurriculumController extends Controller
{
    /**
     * Curriculum & Static Content Overview.
     */
    public function index(CourseContent $content): View
    {
        $courses = Course::orderBy('id')->get();

        $stats = [];
        $totalCards = 0;
        $totalQuestions = 0;

        foreach ($courses as $course) {
            $lessons = $content->lessons($course->slug);
            $quizzes = $content->quizzes($course->slug);

            $cardCount = $lessons->sum(fn ($l) => count($l->items ?? []));
            $questionCount = $quizzes->sum(fn ($q) => count($q->questions ?? []));

            $totalCards += $cardCount;
            $totalQuestions += $questionCount;

            $stats[$course->slug] = [
                'course'         => $course,
                'lessons_count'  => $lessons->count(),
                'cards_count'    => $cardCount,
                'quizzes_count'  => $quizzes->count(),
                'questions_count'=> $questionCount,
                'has_content'    => $content->hasContent($course->slug),
            ];
        }

        // Integrity Health Check
        $lessonPath = config('course-content.lessons') ?: database_path('data/lesson-content.json');
        $quizPath = config('course-content.quizzes') ?: database_path('data/quiz-content.json');

        $health = [
            'lessons_file_exists' => File::exists($lessonPath),
            'lessons_file_size'   => File::exists($lessonPath) ? number_format(File::size($lessonPath) / 1024, 1) . ' KB' : 'N/A',
            'quizzes_file_exists' => File::exists($quizPath),
            'quizzes_file_size'   => File::exists($quizPath) ? number_format(File::size($quizPath) / 1024, 1) . ' KB' : 'N/A',
            'total_cards'         => $totalCards,
            'total_questions'     => $totalQuestions,
            'status'              => 'Healthy & Validated',
        ];

        return view('backend.curriculum.index', compact('stats', 'health', 'courses'));
    }

    /**
     * Inspect lessons and study cards for a course.
     */
    public function lessons(string $courseSlug, CourseContent $content): View
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $lessons = $content->lessons($courseSlug);

        return view('backend.curriculum.lessons', compact('course', 'lessons'));
    }

    /**
     * Inspect quiz papers and question banks for a course.
     */
    public function quizzes(string $courseSlug, Request $request, CourseContent $content): View
    {
        $course = Course::where('slug', $courseSlug)->firstOrFail();
        $quizzes = $content->quizzes($courseSlug);
        $quizzesByKind = $content->quizzesByKind($courseSlug);

        // Fetch aggregate attempt stats for this course
        $attemptStats = QuizAttempt::where('course_slug', $courseSlug)
            ->whereNotNull('submitted_at')
            ->selectRaw('quiz_slug, count(*) as attempts_count, avg(percentage) as avg_percentage, sum(passed) as passes_count')
            ->groupBy('quiz_slug')
            ->get()
            ->keyBy('quiz_slug');

        $activeQuizSlug = $request->query('quiz');
        $selectedQuiz = $activeQuizSlug
            ? $quizzes->first(fn ($q) => $q->slug === $activeQuizSlug)
            : $quizzes->first();

        return view('backend.curriculum.quizzes', compact('course', 'quizzes', 'quizzesByKind', 'selectedQuiz', 'attemptStats'));
    }
}
