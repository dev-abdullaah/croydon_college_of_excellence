<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCertificateController extends Controller
{
    /**
     * Display listing of issued certificates.
     */
    public function index(Request $request): View
    {
        $query = Certificate::with(['student', 'course', 'issuedBy']);

        // Status filter
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'revoked') {
            $query->revoked();
        }

        // Course filter
        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        // Search query
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                  ->orWhere('verification_hash', 'like', "%{$search}%")
                  ->orWhere('grade', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('course', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $certificates = $query->latest('issued_at')->paginate(15)->withQueryString();

        $stats = [
            'total'   => Certificate::count(),
            'active'  => Certificate::active()->count(),
            'revoked' => Certificate::revoked()->count(),
        ];

        $courses = Course::orderBy('name')->get();
        $students = Student::orderBy('name')->get();

        return view('backend.certificates.index', compact('certificates', 'stats', 'courses', 'students'));
    }

    /**
     * Issue a new certificate to an admitted student.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_id'  => ['required', 'exists:courses,id'],
            'grade'      => ['nullable', 'string', 'max:50'],
            'issued_at'  => ['nullable', 'date'],
        ]);

        $student = Student::findOrFail($validated['student_id']);
        $course = Course::findOrFail($validated['course_id']);

        $certificateNumber = Certificate::generateNumber();
        $hash = Certificate::generateHash($certificateNumber, $student->id);

        $certificate = Certificate::create([
            'certificate_number' => $certificateNumber,
            'student_id'         => $student->id,
            'course_id'          => $course->id,
            'issued_at'          => $validated['issued_at'] ?? now(),
            'grade'              => $validated['grade'] ?: 'Passed',
            'verification_hash'  => $hash,
            'status'             => Certificate::STATUS_ACTIVE,
            'issued_by'          => Auth::guard('admin')->id(),
        ]);

        AdminAuditLog::record(
            action: 'certificate_issued',
            auditable: $certificate,
            newValues: $certificate->toArray(),
            notes: "Issued certificate {$certificate->certificate_number} to student {$student->name} for '{$course->name}'."
        );

        return back()->with('success', "Certificate {$certificate->certificate_number} successfully issued for {$student->name}.");
    }

    /**
     * Revoke an authentic certificate.
     */
    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $validated = $request->validate([
            'revocation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $oldStatus = $certificate->status;
        $certificate->update([
            'status'            => Certificate::STATUS_REVOKED,
            'revoked_at'        => now(),
            'revocation_reason' => $validated['revocation_reason'] ?? 'Revoked by college administration',
        ]);

        AdminAuditLog::record(
            action: 'certificate_revoked',
            auditable: $certificate,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => Certificate::STATUS_REVOKED, 'revocation_reason' => $certificate->revocation_reason],
            notes: "Revoked certificate {$certificate->certificate_number} of student {$certificate->student->name}."
        );

        return back()->with('success', "Certificate {$certificate->certificate_number} has been revoked.");
    }

    /**
     * Restore a previously revoked certificate.
     */
    public function restore(Certificate $certificate): RedirectResponse
    {
        $oldStatus = $certificate->status;
        $certificate->update([
            'status'            => Certificate::STATUS_ACTIVE,
            'revoked_at'        => null,
            'revocation_reason' => null,
        ]);

        AdminAuditLog::record(
            action: 'certificate_restored',
            auditable: $certificate,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => Certificate::STATUS_ACTIVE],
            notes: "Restored active status for certificate {$certificate->certificate_number} of student {$certificate->student->name}."
        );

        return back()->with('success', "Certificate {$certificate->certificate_number} restored to active status.");
    }

    /**
     * Export certificates registry to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Certificate::with(['student', 'course', 'issuedBy']);

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'revoked') {
                $query->revoked();
            }
        }

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        $fileName = 'cce_certificates_' . now()->format('Y-m-d_His') . '.csv';

        AdminAuditLog::record(
            action: 'certificates_exported',
            notes: 'Exported certificates registry CSV'
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Certificate Number',
                'Student Name',
                'Student Email',
                'Course Name',
                'Grade / Achievement',
                'Status',
                'Issued Date',
                'Revoked Date',
                'Issued By Admin',
                'Verification URL',
                'Digital Fingerprint Hash',
            ]);

            $query->latest('issued_at')->chunk(100, function ($certs) use ($handle) {
                foreach ($certs as $c) {
                    fputcsv($handle, [
                        $c->certificate_number,
                        $c->student->name ?? 'N/A',
                        $c->student->email ?? 'N/A',
                        $c->course->name ?? 'Course #' . $c->course_id,
                        $c->grade ?? 'Passed',
                        ucfirst($c->status),
                        $c->issued_at?->format('Y-m-d H:i:s') ?? '',
                        $c->revoked_at?->format('Y-m-d H:i:s') ?? '',
                        $c->issuedBy->name ?? 'System',
                        $c->verificationUrl(),
                        $c->verification_hash,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
