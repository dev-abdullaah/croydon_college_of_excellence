<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminStudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Student::query()->withCount('purchases');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'suspended') {
                $query->where('is_active', false);
            }
        }

        if ($verified = $request->input('verified')) {
            if ($verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($verified === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        if ($purchases = $request->input('purchases')) {
            if ($purchases === 'has_purchases') {
                $query->has('purchases');
            } elseif ($purchases === 'no_purchases') {
                $query->doesntHave('purchases');
            }
        }

        $totalStudents = Student::count();
        $activeStudents = Student::where('is_active', true)->count();
        $enrolledStudents = Student::has('purchases')->count();
        $verifiedStudents = Student::whereNotNull('email_verified_at')->count();

        $students = $query->latest()->paginate(20)->withQueryString();

        return view('backend.students.index', compact(
            'students',
            'totalStudents',
            'activeStudents',
            'enrolledStudents',
            'verifiedStudents'
        ));
    }

    public function show(Student $student): View
    {
        $student->load([
            'purchases.course',
            'emails',
            'lessonProgress',
            'quizAttempts' => fn ($q) => $q->latest(),
            'loginHistories' => fn ($q) => $q->latest()->take(20),
        ]);

        $purchases = $student->purchases;
        $quizAttempts = $student->quizAttempts;
        $lessonProgress = $student->lessonProgress;
        $loginHistories = $student->loginHistories;
        $availableCourses = Course::where('is_active', true)->orderBy('sort_order')->get();

        return view('backend.students.show', compact('student', 'purchases', 'quizAttempts', 'lessonProgress', 'loginHistories', 'availableCourses'));
    }

    public function toggleStatus(Student $student): RedirectResponse
    {
        $oldState = (bool) $student->isActive();
        $student->is_active = ! $oldState;
        $student->save();

        $state = $student->is_active ? 'activated' : 'suspended';

        AdminAuditLog::record(
            action: 'student_status_toggled',
            auditable: $student,
            oldValues: ['is_active' => $oldState],
            newValues: ['is_active' => $student->is_active],
            notes: "Student {$student->name} ({$student->email}) account status changed to {$state}."
        );

        return back()->with('success', "Student account for {$student->name} has been {$state}.");
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        if ($request->filled('password')) {
            $request->validate([
                'password' => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
            ]);

            $student->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->input('password')),
            ]);

            AdminAuditLog::record(
                action: 'student_password_reset',
                auditable: $student,
                notes: "Admin manually updated password for student {$student->name} ({$student->email})."
            );

            return back()->with('success', "Password for {$student->name} updated successfully.");
        }

        Password::broker('students')->sendResetLink(['email' => $student->email]);

        AdminAuditLog::record(
            action: 'student_password_reset_link_dispatched',
            auditable: $student,
            notes: "Dispatched password reset email link to {$student->email}."
        );

        return back()->with('success', "Password reset instructions sent to {$student->email}.");
    }

    public function sendPasswordReset(Student $student): RedirectResponse
    {
        return $this->resetPassword(request(), $student);
    }

    /**
     * Manually enroll a student into a course.
     */
    public function enroll(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'course_id'      => ['required', 'exists:courses,id'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,card_offline,scholarship,complimentary,other'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $course = Course::findOrFail($validated['course_id']);

        if ($student->hasPurchased($course->id)) {
            return back()->with('error', "Student is already enrolled in '{$course->name}'.");
        }

        $purchase = Purchase::create([
            'student_id'        => $student->id,
            'course_id'         => $course->id,
            'customer_email'    => $student->email,
            'customer_name'     => $student->name,
            'contact_phone'     => $student->phone,
            'amount'            => $course->price,
            'currency'          => 'gbp',
            'payment_method'    => $validated['payment_method'],
            'admin_notes'       => $validated['notes'] ?? null,
            'status'            => Purchase::STATUS_PAID,
            'paid_at'           => now(),
            'admitted_at'       => now(),
            'admitted_by'       => Auth::guard('admin')->id(),
            'terms_accepted_at' => now(),
            'terms_version'     => 'manual-admin-1.0',
            'metadata'          => [
                'enrollment_type' => 'manual_admin',
                'payment_method'  => $validated['payment_method'],
                'admin_id'        => Auth::guard('admin')->id(),
                'admin_name'      => Auth::guard('admin')->user()?->name,
                'admin_notes'     => $validated['notes'] ?? null,
            ],
        ]);

        AdminAuditLog::record(
            action: 'manual_student_enrollment',
            auditable: $purchase,
            newValues: [
                'student_id'     => $student->id,
                'course_id'      => $course->id,
                'amount'         => $course->price,
                'payment_method' => $validated['payment_method'],
            ],
            notes: "Manually enrolled {$student->name} into '{$course->name}' via " . ucfirst(str_replace('_', ' ', $validated['payment_method'])) . "."
        );

        return back()->with('success', "Student {$student->name} successfully enrolled in {$course->name}.");
    }

    /**
     * Impersonate student (Login as Student).
     */
    public function impersonate(Student $student): RedirectResponse
    {
        $adminId = Auth::guard('admin')->id();
        session()->put('impersonating_admin_id', $adminId);
        Auth::guard('web')->login($student);

        AdminAuditLog::record(
            action: 'student_impersonated',
            auditable: $student,
            notes: "Admin began impersonating student {$student->name} ({$student->email})."
        );

        return redirect()->route('dashboard')->with('info', "You are now logged in as student {$student->name}.");
    }

    /**
     * Stop student impersonation and return to admin session.
     */
    public function stopImpersonating(): RedirectResponse
    {
        Auth::guard('web')->logout();
        session()->forget('impersonating_admin_id');

        return redirect()->route('admin.students.index')->with('success', 'Returned to admin session.');
    }

    /**
     * Export students as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Student::query()->withCount('purchases');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'suspended') {
                $query->where('is_active', false);
            }
        }

        if ($verified = $request->input('verified')) {
            if ($verified === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($verified === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        $fileName = 'cce_students_export_' . now()->format('Y-m-d_His') . '.csv';

        AdminAuditLog::record(
            action: 'students_exported',
            notes: 'Exported students CSV report with filters: ' . json_encode($request->only(['search', 'status', 'verified']))
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Student ID',
                'Name',
                'Email',
                'Status',
                'Email Verified',
                'Total Purchases',
                'Registered At',
            ]);

            $query->latest()->chunk(100, function ($students) use ($handle) {
                foreach ($students as $s) {
                    fputcsv($handle, [
                        $s->id,
                        $s->name,
                        $s->email,
                        $s->isActive() ? 'Active' : 'Suspended',
                        $s->email_verified_at ? 'Yes (' . $s->email_verified_at->format('Y-m-d') . ')' : 'No',
                        $s->purchases_count,
                        $s->created_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
