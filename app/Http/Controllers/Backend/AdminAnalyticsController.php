<?php

namespace App\Http\Controllers\Backend;

use App\Content\CourseContent;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Models\Purchase;
use App\Models\QuizAttempt;
use App\Support\DateRangeFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request, CourseContent $courseContent): View
    {
        [$startDate, $endDate, $dateRangePreset] = DateRangeFilter::resolve($request);

        // Base purchase query with optional date filtering
        $paidPurchasesQuery = Purchase::whereIn('status', [Purchase::STATUS_PAID, Purchase::STATUS_ADMITTED]);
        $quizAttemptsQuery = QuizAttempt::query();

        if ($startDate && $endDate) {
            $paidPurchasesQuery->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('paid_at', [$startDate, $endDate])
                  ->orWhereBetween('admitted_at', [$startDate, $endDate]);
            });
            $quizAttemptsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Financial metrics
        $totalPaidPurchases = (clone $paidPurchasesQuery)->count();
        $totalRevenuePence = (clone $paidPurchasesQuery)->sum('amount');
        $totalRevenue = $totalRevenuePence / 100;
        $avgPurchaseValue = $totalPaidPurchases > 0 ? $totalRevenue / $totalPaidPurchases : 0;
        $avgOrderValue = $avgPurchaseValue;

        // Per-Course Analytics
        $courses = Course::withCount([
            'purchases as paid_count' => function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', [Purchase::STATUS_PAID, Purchase::STATUS_ADMITTED]);
                if ($startDate && $endDate) {
                    $q->where(function ($sq) use ($startDate, $endDate) {
                        $sq->whereBetween('paid_at', [$startDate, $endDate])
                           ->orWhereBetween('admitted_at', [$startDate, $endDate]);
                    });
                }
            },
        ])->get();

        $courseStats = [];
        foreach ($courses as $course) {
            $revenueQuery = Purchase::where('course_id', $course->id)
                ->whereIn('status', [Purchase::STATUS_PAID, Purchase::STATUS_ADMITTED]);

            if ($startDate && $endDate) {
                $revenueQuery->where(function ($sq) use ($startDate, $endDate) {
                    $sq->whereBetween('paid_at', [$startDate, $endDate])
                       ->orWhereBetween('admitted_at', [$startDate, $endDate]);
                });
            }

            $revenuePence = $revenueQuery->sum('amount');

            // Total lessons in content file
            $lessons = $courseContent->lessons($course->slug);
            $totalLessonsCount = $lessons->count();

            // Unique students who bought this course
            $paidStudentIdsQuery = Purchase::where('course_id', $course->id)
                ->whereIn('status', [Purchase::STATUS_PAID, Purchase::STATUS_ADMITTED]);

            if ($startDate && $endDate) {
                $paidStudentIdsQuery->whereBetween('paid_at', [$startDate, $endDate]);
            }

            $paidStudentIds = $paidStudentIdsQuery->pluck('student_id')->unique();

            // Optimized: Single Grouped Query eliminating the previous N+1 loop bottleneck
            $completedAllCount = 0;
            if ($totalLessonsCount > 0 && $paidStudentIds->isNotEmpty()) {
                $completedAllCount = LessonProgress::where('course_slug', $course->slug)
                    ->whereIn('student_id', $paidStudentIds)
                    ->select('student_id')
                    ->groupBy('student_id')
                    ->havingRaw('count(*) >= ?', [$totalLessonsCount])
                    ->get()
                    ->count();
            }

            $completionRate = $paidStudentIds->count() > 0
                ? round(($completedAllCount / $paidStudentIds->count()) * 100, 1)
                : 0;

            $courseStats[] = [
                'course'          => $course,
                'revenue'         => $revenuePence / 100,
                'paid_count'      => $course->paid_count,
                'total_lessons'   => $totalLessonsCount,
                'completion_rate' => $completionRate,
            ];
        }

        // Quiz Analytics
        $totalQuizAttempts = (clone $quizAttemptsQuery)->count();
        $passedAttempts = (clone $quizAttemptsQuery)
            ->where(function ($q) {
                $q->where('passed', true)->orWhere('percentage', '>=', 50);
            })->count();

        $overallPassRate = $totalQuizAttempts > 0 ? round(($passedAttempts / $totalQuizAttempts) * 100, 1) : 0;
        $avgScorePercentage = $totalQuizAttempts > 0 ? round((float) (clone $quizAttemptsQuery)->avg('percentage'), 1) : 0;

        // Quiz breakdown by paper
        $breakdownQuery = QuizAttempt::select(
            'quiz_slug',
            'course_slug',
            DB::raw('count(*) as total_attempts'),
            DB::raw('avg(percentage) as avg_percentage'),
            DB::raw('sum(case when passed = 1 or percentage >= 50 then 1 else 0 end) as passed_count')
        );

        if ($startDate && $endDate) {
            $breakdownQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $quizBreakdown = $breakdownQuery
            ->groupBy('quiz_slug', 'course_slug')
            ->orderByDesc('total_attempts')
            ->get()
            ->map(function ($row) {
                $passRate = $row->total_attempts > 0 ? round(($row->passed_count / $row->total_attempts) * 100, 1) : 0;
                $row->pass_rate = $passRate;
                $row->avg_percentage = round((float) $row->avg_percentage, 1);
                return $row;
            });

        return view('backend.analytics.index', compact(
            'totalRevenue',
            'totalPaidPurchases',
            'avgPurchaseValue',
            'avgOrderValue',
            'courseStats',
            'totalQuizAttempts',
            'overallPassRate',
            'avgScorePercentage',
            'quizBreakdown',
            'dateRangePreset',
            'startDate',
            'endDate'
        ));
    }
}
