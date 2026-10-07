<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionApprovedMail;
use App\Models\AdminAuditLog;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminAdmissionController extends Controller
{
    /**
     * Display listing of student admissions.
     */
    public function index(Request $request): View
    {
        $query = Admission::with(['student', 'course', 'admittedBy']);

        // Tab / Status filter
        $status = $request->input('status', 'all');
        if ($status === 'pending') {
            $query->pending();
        } elseif ($status === 'admitted') {
            $query->admitted();
        } elseif ($status === 'revoked') {
            $query->revoked();
        } elseif ($status === 'rejected') {
            $query->where('status', Admission::STATUS_REJECTED);
        }

        // Course filter
        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('contact_phone', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('course', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $admissions = $query->latest('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => Admission::count(),
            'pending'  => Admission::pending()->count(),
            'admitted' => Admission::admitted()->count(),
            'revoked'  => Admission::revoked()->count(),
            'revenue'  => Admission::admitted()->sum('amount') / 100,
        ];

        $courses = Course::orderBy('name')->get();
        $students = Student::orderBy('name')->get();

        return view('backend.admissions.index', compact('admissions', 'stats', 'courses', 'students'));
    }

    /**
     * Display a single admission record.
     */
    public function show(Admission $admission): View
    {
        $admission->load(['student', 'course', 'admittedBy', 'coupon']);
        return view('backend.admissions.show', compact('admission'));
    }

    /**
     * Approve admission and grant learner course access.
     */
    public function approve(Request $request, Admission $admission): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
            'admin_notes'    => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $admission->status;

        $admission->status = Admission::STATUS_ADMITTED;
        $admission->admitted_at = now();
        $admission->paid_at ??= now();
        $admission->admitted_by = Auth::guard('admin')->id();

        if (! empty($validated['payment_method'])) {
            $admission->payment_method = $validated['payment_method'];
        }
        if (isset($validated['admin_notes'])) {
            $admission->admin_notes = $validated['admin_notes'];
        }

        $admission->save();

        AdminAuditLog::record(
            action: 'admission_approved',
            auditable: $admission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => Admission::STATUS_ADMITTED, 'admitted_by' => $admission->admitted_by],
            notes: "Approved admission #{$admission->id} for {$admission->student->name} into '{$admission->course->name}' via " . ucfirst(str_replace('_', ' ', $admission->payment_method ?? 'manual payment')) . "."
        );

        // Dispatch approved notification email to student
        try {
            Mail::to($admission->student->email)->send(new AdmissionApprovedMail($admission));
        } catch (Throwable $e) {
            Log::error('Admission approved email notification failed', ['error' => $e->getMessage()]);
        }

        return back()->with('success', "Admission approved! Student {$admission->student->name} now has active access to '{$admission->course->name}'.");
    }

    /**
     * Revoke learner course access.
     */
    public function revoke(Request $request, Admission $admission): RedirectResponse
    {
        $oldStatus = $admission->status;

        $admission->status = Admission::STATUS_REVOKED;
        $admission->revoked_at = now();

        if ($notes = $request->input('admin_notes')) {
            $admission->admin_notes = $notes;
        }

        $admission->save();

        AdminAuditLog::record(
            action: 'admission_revoked',
            auditable: $admission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => Admission::STATUS_REVOKED],
            notes: "Revoked course access for student {$admission->student->name} on '{$admission->course->name}'."
        );

        return back()->with('success', "Course access for {$admission->student->name} has been revoked and is now unavailable to the learner.");
    }

    /**
     * Reject / decline an admission request.
     */
    public function reject(Request $request, Admission $admission): RedirectResponse
    {
        $oldStatus = $admission->status;
        $admission->status = Admission::STATUS_REJECTED;

        if ($notes = $request->input('admin_notes')) {
            $admission->admin_notes = $notes;
        }

        $admission->save();

        AdminAuditLog::record(
            action: 'admission_rejected',
            auditable: $admission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => Admission::STATUS_REJECTED],
            notes: "Declined admission request #{$admission->id} for {$admission->student->name} on '{$admission->course->name}'."
        );

        return back()->with('success', "Admission request for {$admission->student->name} has been declined.");
    }

    /**
     * Manually admit a student directly to a course (walk-in / phone payment).
     */
    public function manualAdmit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id'     => ['required', 'exists:students,id'],
            'course_id'      => ['required', 'exists:courses,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'amount'         => ['nullable', 'numeric', 'min:0'],
            'admin_notes'    => ['nullable', 'string', 'max:2000'],
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $course = Course::findOrFail($validated['course_id']);

        $amount = isset($validated['amount'])
            ? (int) round($validated['amount'] * 100)
            : $course->price;

        $admission = Admission::updateOrCreate(
            [
                'student_id' => $student->id,
                'course_id'  => $course->id,
            ],
            [
                'amount'            => $amount,
                'currency'          => strtolower($course->currency ?? 'gbp'),
                'payment_method'    => $validated['payment_method'],
                'contact_phone'     => $student->phone,
                'customer_name'     => $student->name,
                'customer_email'    => $student->email,
                'admin_notes'       => $validated['admin_notes'] ?? null,
                'status'            => Admission::STATUS_ADMITTED,
                'requested_at'      => now(),
                'admitted_at'       => now(),
                'paid_at'           => now(),
                'admitted_by'       => Auth::guard('admin')->id(),
                'terms_accepted_at' => now(),
                'terms_version'     => 'admin_manual',
            ]
        );

        AdminAuditLog::record(
            action: 'admission_manually_created',
            auditable: $admission,
            newValues: $admission->toArray(),
            notes: "Administrator directly admitted student {$student->name} into '{$course->name}' via " . ucfirst(str_replace('_', ' ', $validated['payment_method'])) . "."
        );

        // Dispatch approved notification email to student
        try {
            Mail::to($student->email)->send(new AdmissionApprovedMail($admission));
        } catch (Throwable $e) {
            Log::error('Manual admission approved email failed', ['error' => $e->getMessage()]);
        }

        return back()->with('success', "Student {$student->name} successfully admitted into '{$course->name}'. Course access is active.");
    }

    /**
     * Update internal admin notes or payment method.
     */
    public function updateNotes(Request $request, Admission $admission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes'    => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        $admission->update($validated);

        return back()->with('success', "Admission notes updated.");
    }

    /**
     * Export admissions report as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Admission::with(['student', 'course', 'admittedBy']);

        if ($status = $request->input('status')) {
            if ($status === 'pending') {
                $query->pending();
            } elseif ($status === 'admitted') {
                $query->admitted();
            } elseif ($status === 'revoked') {
                $query->revoked();
            }
        }

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('contact_phone', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $fileName = 'cce_admissions_report_' . now()->format('Y-m-d_His') . '.csv';

        AdminAuditLog::record(
            action: 'admissions_exported',
            notes: 'Exported student admissions CSV report with filters: ' . json_encode($request->only(['status', 'course_id', 'search']))
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Admission ID',
                'Learner Name',
                'Learner Email',
                'Contact Phone',
                'Course Name',
                'Tuition Fee (£)',
                'Payment Method',
                'Admission Status',
                'Requested At',
                'Admitted At',
                'Revoked At',
                'Admitted By Admin',
                'Learner Application Notes',
                'Internal Admin Notes',
            ]);

            $query->latest('id')->chunk(100, function ($admissions) use ($handle) {
                foreach ($admissions as $a) {
                    fputcsv($handle, [
                        $a->id,
                        $a->student->name ?? $a->customer_name ?? 'N/A',
                        $a->student->email ?? $a->customer_email ?? 'N/A',
                        $a->contact_phone ?? $a->student?->phone ?? 'N/A',
                        $a->course->name ?? 'Course #' . $a->course_id,
                        number_format($a->amount / 100, 2, '.', ''),
                        ucfirst(str_replace('_', ' ', $a->payment_method ?? 'N/A')),
                        $a->statusLabel(),
                        $a->requested_at?->format('Y-m-d H:i:s') ?? $a->created_at?->format('Y-m-d H:i:s') ?? '',
                        $a->admitted_at?->format('Y-m-d H:i:s') ?? $a->paid_at?->format('Y-m-d H:i:s') ?? '',
                        $a->revoked_at?->format('Y-m-d H:i:s') ?? '',
                        $a->admittedBy->name ?? 'System',
                        $a->learner_notes ?? '',
                        $a->admin_notes ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
