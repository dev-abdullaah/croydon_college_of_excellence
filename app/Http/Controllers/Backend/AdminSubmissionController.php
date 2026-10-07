<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $query = ContactSubmission::query();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($readStatus = $request->input('read_status')) {
            if ($readStatus === 'unread') {
                $query->whereNull('read_at');
            } elseif ($readStatus === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $submissions = $query->latest()->paginate(20)->withQueryString();

        $unreadCount = ContactSubmission::unread()->count();
        $enrollmentCount = ContactSubmission::where('type', 'enrollment')->count();
        $assessmentCount = ContactSubmission::where('type', 'assessment')->count();
        $tutorCount = ContactSubmission::where('type', 'tutor')->count();

        return view('backend.submissions.index', compact(
            'submissions',
            'unreadCount',
            'enrollmentCount',
            'assessmentCount',
            'tutorCount'
        ));
    }

    public function show(ContactSubmission $submission): View
    {
        $submission->markAsRead();

        return view('backend.submissions.show', compact('submission'));
    }

    public function toggleRead(ContactSubmission $submission): RedirectResponse
    {
        if ($submission->isRead()) {
            $submission->markAsUnread();
            $msg = 'Marked as unread.';
        } else {
            $submission->markAsRead();
            $msg = 'Marked as read.';
        }

        return back()->with('success', $msg);
    }

    public function updateNotes(Request $request, ContactSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string'],
            'status'      => ['required', 'string', 'in:new,contacted,resolved,archived'],
        ]);

        $oldStatus = $submission->status;
        $submission->update($validated);

        AdminAuditLog::record(
            action: 'submission_updated',
            auditable: $submission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $submission->status],
            notes: "Updated submission #{$submission->id} ({$submission->type}) notes and status to {$submission->status}."
        );

        return back()->with('success', 'Internal administrator notes saved.');
    }

    public function destroy(ContactSubmission $submission): RedirectResponse
    {
        AdminAuditLog::record(
            action: 'submission_deleted',
            notes: "Deleted submission #{$submission->id} from {$submission->name} ({$submission->email})."
        );

        $submission->delete();

        return redirect()->route('admin.submissions.index')
            ->with('success', 'Submission deleted successfully.');
    }

    /**
     * Export submissions as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ContactSubmission::query();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $fileName = 'cce_inquiries_export_' . now()->format('Y-m-d_His') . '.csv';

        AdminAuditLog::record(
            action: 'submissions_exported',
            notes: 'Exported inquiries/submissions CSV report.'
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID',
                'Type',
                'Name',
                'Email',
                'Phone',
                'Subject',
                'Message',
                'Status',
                'Read Status',
                'Created At',
            ]);

            $query->latest()->chunk(100, function ($submissions) use ($handle) {
                foreach ($submissions as $sub) {
                    fputcsv($handle, [
                        $sub->id,
                        ucfirst($sub->type),
                        $sub->name,
                        $sub->email,
                        $sub->phone ?? '',
                        $sub->subject ?? '',
                        $sub->message ?? '',
                        ucfirst($sub->status),
                        $sub->read_at ? 'Read' : 'Unread',
                        $sub->created_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
