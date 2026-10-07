<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Course;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $query = Purchase::with(['student', 'course']);

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('stripe_checkout_session_id', 'like', "%{$search}%")
                  ->orWhere('stripe_payment_intent_id', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $counts = [
            'all'      => Purchase::count(),
            'paid'     => Purchase::where('status', Purchase::STATUS_PAID)->count(),
            'pending'  => Purchase::where('status', Purchase::STATUS_PENDING)->count(),
            'failed'   => Purchase::where('status', Purchase::STATUS_FAILED)->count(),
            'refunded' => Purchase::where('status', Purchase::STATUS_REFUNDED)->count(),
        ];

        $purchases = $query->latest()->paginate(20)->withQueryString();
        $courses = Course::orderBy('name')->get();

        return view('backend.purchases.index', compact('purchases', 'courses', 'counts'));
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load(['student', 'course']);

        return view('backend.purchases.show', compact('purchase'));
    }

    public function updateStatus(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'status'                 => ['required', 'string', 'in:paid,pending,failed,refunded,cancelled,expired'],
            'process_stripe_refund'  => ['nullable', 'boolean'],
            'refund_reason'          => ['nullable', 'string', 'max:255'],
        ]);

        $oldStatus = $purchase->status;
        $targetStatus = $validated['status'];
        $attributes = ['status' => $targetStatus];

        if ($targetStatus === Purchase::STATUS_PAID && $purchase->paid_at === null) {
            $attributes['paid_at'] = now();
        }

        // If transitioning to refunded and admin requested Stripe gateway refund
        $stripeRefundNote = null;
        if ($targetStatus === Purchase::STATUS_REFUNDED) {
            if ($purchase->refunded_at === null) {
                $attributes['refunded_at'] = now();
            }

            if ($request->boolean('process_stripe_refund') && $purchase->stripe_payment_intent_id) {
                $stripeSecret = config('stripe.secret');
                if (! empty($stripeSecret)) {
                    try {
                        \Stripe\Stripe::setApiKey($stripeSecret);
                        $stripeRefund = \Stripe\Refund::create([
                            'payment_intent' => $purchase->stripe_payment_intent_id,
                            'reason'         => 'requested_by_customer',
                        ]);

                        $metadata = $purchase->metadata ?? [];
                        $metadata['stripe_refund_id'] = $stripeRefund->id;
                        $metadata['stripe_refund_status'] = $stripeRefund->status;
                        $attributes['metadata'] = $metadata;
                        $stripeRefundNote = "Stripe refund {$stripeRefund->id} executed successfully.";
                    } catch (\Exception $e) {
                        Log::error("Stripe refund failed for purchase #{$purchase->id}: " . $e->getMessage());
                        return back()->with('error', "Stripe gateway refund failed: " . $e->getMessage());
                    }
                }
            }
        }

        $purchase->update($attributes);

        // Record Audit Log
        AdminAuditLog::record(
            action: $targetStatus === Purchase::STATUS_REFUNDED ? 'purchase_refunded' : 'purchase_status_updated',
            auditable: $purchase,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $targetStatus],
            notes: "Purchase #{$purchase->id} status changed from {$oldStatus} to {$targetStatus}." . ($stripeRefundNote ? " {$stripeRefundNote}" : '')
        );

        $msg = "Purchase #{$purchase->id} status updated to {$purchase->status}.";
        if ($targetStatus === Purchase::STATUS_REFUNDED) {
            $msg .= " Student course access has been revoked." . ($stripeRefundNote ? " {$stripeRefundNote}" : '');
        }

        return back()->with('success', $msg);
    }

    /**
     * Export purchases as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Purchase::with(['student', 'course']);

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($courseId = $request->input('course_id')) {
            $query->where('course_id', $courseId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('stripe_checkout_session_id', 'like', "%{$search}%")
                  ->orWhere('stripe_payment_intent_id', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $fileName = 'cce_purchases_export_' . now()->format('Y-m-d_His') . '.csv';

        AdminAuditLog::record(
            action: 'purchases_exported',
            notes: 'Exported purchases CSV report with filters: ' . json_encode($request->only(['status', 'course_id', 'search']))
        );

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Purchase ID',
                'Student Name',
                'Student Email',
                'Course Name',
                'Amount (£)',
                'Currency',
                'Status',
                'Stripe Checkout Session ID',
                'Stripe Payment Intent ID',
                'Created At',
                'Paid At',
                'Refunded At',
            ]);

            $query->latest()->chunk(100, function ($purchases) use ($handle) {
                foreach ($purchases as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->student->name ?? $p->customer_name ?? 'Guest',
                        $p->student->email ?? $p->customer_email ?? 'N/A',
                        $p->course->name ?? 'Course #' . $p->course_id,
                        number_format($p->amount / 100, 2, '.', ''),
                        strtoupper($p->currency ?? 'gbp'),
                        ucfirst($p->status),
                        $p->stripe_checkout_session_id ?? '',
                        $p->stripe_payment_intent_id ?? '',
                        $p->created_at?->format('Y-m-d H:i:s') ?? '',
                        $p->paid_at?->format('Y-m-d H:i:s') ?? '',
                        $p->refunded_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
