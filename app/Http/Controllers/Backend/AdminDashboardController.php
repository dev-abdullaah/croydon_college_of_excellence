<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Admission;
use App\Models\ContactSubmission;
use App\Models\Course;
use App\Models\Student;
use App\Support\DateRangeFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        [$startDate, $endDate, $dateRangePreset] = DateRangeFilter::resolve($request);

        $admissionsQuery = Admission::query();
        $admittedQuery = Admission::admitted();
        $studentsQuery = Student::query();

        if ($startDate && $endDate) {
            $admittedQuery->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('admitted_at', [$startDate, $endDate])
                  ->orWhere(function ($sq) use ($startDate, $endDate) {
                      $sq->whereNull('admitted_at')->whereBetween('paid_at', [$startDate, $endDate]);
                  });
            });
            $admissionsQuery->whereBetween('created_at', [$startDate, $endDate]);
            $studentsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $totalRevenuePence = (clone $admittedQuery)->sum('amount');
        $totalRevenue = $totalRevenuePence / 100;

        $todayRevenuePence = Admission::admitted()
            ->where(function ($q) {
                $q->whereDate('admitted_at', today())
                  ->orWhereDate('paid_at', today());
            })
            ->sum('amount');
        $todayRevenue = $todayRevenuePence / 100;

        $totalAdmissions = (clone $admissionsQuery)->count();
        $todayAdmissions = Admission::whereDate('created_at', today())->count();
        $admittedLearners = (clone $admittedQuery)->count();
        $pendingAdmissions = (clone $admissionsQuery)->where('status', Admission::STATUS_PENDING)->count();
        $revokedAdmissions = (clone $admissionsQuery)->where('status', Admission::STATUS_REVOKED)->count();

        $avgAdmissionFee = $admittedLearners > 0 ? ($totalRevenue / $admittedLearners) : 0;

        // Backward compatibility aliases
        $totalPurchases = $totalAdmissions;
        $todayPurchases = $todayAdmissions;
        $totalPaidPurchases = $admittedLearners;
        $pendingPurchases = $pendingAdmissions;
        $failedPurchases = 0;
        $refundedPurchases = $revokedAdmissions;
        $avgPurchaseValue = $avgAdmissionFee;
        $totalOrders = $totalAdmissions;
        $todayOrders = $todayAdmissions;
        $totalPaidOrders = $admittedLearners;
        $pendingOrders = $pendingAdmissions;
        $failedOrders = 0;
        $refundedOrders = $revokedAdmissions;
        $avgOrderValue = $avgAdmissionFee;

        $totalStudents = (clone $studentsQuery)->count();
        $allTimeStudents = Student::count();
        $verifiedStudents = Student::whereNotNull('email_verified_at')->count();

        $activeCourses = Course::where('is_active', true)->count();
        $totalCourses = Course::count();
        $unreadInquiries = ContactSubmission::unread()->count();

        $recentAdmissions = Admission::with(['student', 'course'])
            ->latest('id')
            ->take(10)
            ->get();
        $recentPurchases = $recentAdmissions;
        $recentOrders = $recentAdmissions;

        $recentInquiries = ContactSubmission::latest()
            ->take(5)
            ->get();

        $recentStudents = Student::withCount(['purchases', 'admissions'])
            ->latest()
            ->take(8)
            ->get();

        $topCourses = Course::withCount(['purchases' => function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', [Admission::STATUS_PAID, Admission::STATUS_ADMITTED]);
                if ($startDate && $endDate) {
                    $q->whereBetween('paid_at', [$startDate, $endDate]);
                }
            }])
            ->withSum(['purchases' => function ($q) use ($startDate, $endDate) {
                $q->whereIn('status', [Admission::STATUS_PAID, Admission::STATUS_ADMITTED]);
                if ($startDate && $endDate) {
                    $q->whereBetween('paid_at', [$startDate, $endDate]);
                }
            }], 'amount')
            ->orderByDesc('purchases_count')
            ->take(5)
            ->get()
            ->map(function ($course) {
                return (object) [
                    'name'        => $course->name,
                    'total_qty'   => $course->purchases_count,
                    'total_sales' => ($course->purchases_sum_amount ?? 0) / 100,
                ];
            });

        return view('backend.dashboard', compact(
            'totalRevenue',
            'todayRevenue',
            'totalAdmissions',
            'todayAdmissions',
            'admittedLearners',
            'pendingAdmissions',
            'revokedAdmissions',
            'avgAdmissionFee',
            'totalPurchases',
            'todayPurchases',
            'totalPaidPurchases',
            'pendingPurchases',
            'failedPurchases',
            'refundedPurchases',
            'avgPurchaseValue',
            'totalOrders',
            'todayOrders',
            'totalPaidOrders',
            'pendingOrders',
            'failedOrders',
            'refundedOrders',
            'avgOrderValue',
            'totalStudents',
            'allTimeStudents',
            'verifiedStudents',
            'activeCourses',
            'totalCourses',
            'unreadInquiries',
            'recentAdmissions',
            'recentPurchases',
            'recentOrders',
            'recentInquiries',
            'recentStudents',
            'topCourses',
            'dateRangePreset',
            'startDate',
            'endDate'
        ));
    }
}
