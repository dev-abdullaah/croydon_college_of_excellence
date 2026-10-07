@extends('backend.layouts.app')
@section('title', 'Purchase Audit: #' . $purchase->id)

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                🔍 Purchase Audit #{{ $purchase->id }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Stripe transaction reconciliation, customer terms consent, and gateway identifiers</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.purchases.index') }}" class="btn btn-sm btn-light">← Back to Purchases</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center p-4 mb-5">
            <i class="fas fa-check-circle fs-3 text-success me-3"></i>
            <span class="fw-semibold">{{ session('success') }}</span>
        </div>
        @endif

        <div class="row g-5 g-xl-8">
            <!-- Left Column: Purchase Summary & Status Update -->
            <div class="col-xl-4">
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Financial Summary</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-column gap-3 mb-6">
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted">Total Charged:</span>
                                <span class="fw-bolder fs-4 text-gray-900">£{{ number_format($purchase->amount / 100, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted">Currency:</span>
                                <span class="fw-bold text-uppercase">{{ $purchase->currency }}</span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted">Payment Status:</span>
                                @php
                                    $statusBadge = match($purchase->status) {
                                        'paid'      => 'badge-light-success text-success',
                                        'pending'   => 'badge-light-warning text-warning',
                                        'failed'    => 'badge-light-danger text-danger',
                                        'refunded'  => 'badge-light-secondary text-secondary',
                                        default     => 'badge-light-info text-info',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }} fw-bolder text-uppercase fs-7">{{ $purchase->status }}</span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted">Initiated:</span>
                                <span class="fw-semibold">{{ $purchase->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted">Paid Timestamp:</span>
                                <span class="fw-semibold text-success">{{ $purchase->paid_at ? $purchase->paid_at->format('d M Y, H:i:s') : '-' }}</span>
                            </div>
                            @if($purchase->refunded_at)
                            <div class="d-flex justify-content-between border-bottom pb-2">
                                <span class="text-muted">Refunded Timestamp:</span>
                                <span class="fw-semibold text-danger">{{ $purchase->refunded_at->format('d M Y, H:i:s') }}</span>
                            </div>
                            @endif
                            @if($purchase->failure_reason)
                            <div class="p-3 bg-light-danger rounded">
                                <span class="text-danger fw-semibold fs-7">Failure: {{ $purchase->failure_reason }}</span>
                            </div>
                            @endif
                        </div>

                        <!-- Update Status Form -->
                        <div class="separator my-4"></div>
                        <h4 class="fs-6 fw-bold mb-3 text-gray-800">Reconcile / Update Status</h4>
                        
                        <div class="alert bg-light-warning d-flex align-items-center p-3 mb-3 border border-warning border-opacity-25 rounded">
                            <i class="bi bi-shield-exclamation fs-3 text-warning me-2"></i>
                            <span class="fs-8 text-gray-700">Setting to <strong>Refunded</strong> automatically revokes the student's access to this course.</span>
                        </div>

                        <form method="POST" action="{{ route('admin.purchases.status', $purchase->id) }}" onsubmit="return confirm('Update status for Purchase #{{ $purchase->id }}?');">
                            @csrf
                            @method('PATCH')
                            <div class="mb-3">
                                <label class="form-label fs-7 fw-bold text-gray-700">New Status</label>
                                <select name="status" class="form-select form-select-solid" id="statusSelect">
                                    <option value="paid" {{ $purchase->status === 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="pending" {{ $purchase->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="failed" {{ $purchase->status === 'failed' ? 'selected' : '' }}>Failed</option>
                                    <option value="refunded" {{ $purchase->status === 'refunded' ? 'selected' : '' }}>Refunded (Revokes Access)</option>
                                    <option value="cancelled" {{ $purchase->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>

                            <div class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="checkbox" name="process_stripe_refund" value="1" id="processStripeRefund" {{ $purchase->stripe_payment_intent_id ? '' : 'disabled' }}>
                                <label class="form-check-label fs-7" for="processStripeRefund">
                                    Trigger live Stripe Gateway Refund
                                    @if(!$purchase->stripe_payment_intent_id) 
                                        <span class="text-muted d-block fs-8">(Unavailable: No Stripe Payment Intent recorded)</span> 
                                    @endif
                                </label>
                            </div>

                            <button type="submit" class="btn btn-sm btn-light-primary w-100 fw-bold">Update Purchase Status</button>
                        </form>
                    </div>
                </div>

                <!-- Terms Consent Audit -->
                <div class="card card-flush shadow-sm">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Terms & Consent Audit</h3>
                    </div>
                    <div class="card-body pt-0 fs-7">
                        <p class="text-muted">Evidence of terms acceptance recorded when student pressed Pay:</p>
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span class="text-muted">Terms Accepted At:</span>
                            <span class="fw-bold">{{ $purchase->terms_accepted_at ? $purchase->terms_accepted_at->format('d M Y, H:i:s') : 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Terms Version:</span>
                            <span class="badge badge-light-info fw-bold">v{{ $purchase->terms_version ?: '1.0' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Student, Course, Stripe Audit -->
            <div class="col-xl-8">
                <!-- Learner & Course Card -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Purchaser & Course Details</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <h4 class="fs-6 fw-bold text-gray-700 mb-3">Student Profile</h4>
                                @if($purchase->student)
                                    <div class="d-flex flex-column gap-1">
                                        <a href="{{ route('admin.students.show', $purchase->student_id) }}" class="fs-5 fw-bold text-primary text-hover-primary">
                                            {{ $purchase->student->name }}
                                        </a>
                                        <span class="text-muted fs-7"><i class="fas fa-envelope me-1"></i> {{ $purchase->student->email }}</span>
                                        <span class="text-muted fs-7"><i class="fas fa-id-badge me-1"></i> Student ID: #{{ $purchase->student->id }}</span>
                                    </div>
                                @else
                                    <div class="text-muted">
                                        <span>Name: {{ $purchase->customer_name ?: 'Guest' }}</span><br>
                                        <span>Email: {{ $purchase->customer_email ?: 'N/A' }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6 ps-md-6">
                                <h4 class="fs-6 fw-bold text-gray-700 mb-3">Purchased Course</h4>
                                @if($purchase->course)
                                    <div class="d-flex flex-column gap-1">
                                        <span class="fs-5 fw-bold text-gray-900">{{ $purchase->course->name }}</span>
                                        <span class="text-muted fs-7">Slug: <code>{{ $purchase->course->slug }}</code></span>
                                        <span class="text-muted fs-7">Catalog Price: £{{ number_format($purchase->course->price / 100, 2) }}</span>
                                    </div>
                                @else
                                    <span class="text-muted">Course #{{ $purchase->course_id }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stripe Transaction Identifiers Card -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Stripe Gateway Audit Trail</h3>
                        @if($purchase->stripe_payment_intent_id)
                        <div class="card-toolbar">
                            <a href="https://dashboard.stripe.com/payments/{{ $purchase->stripe_payment_intent_id }}" target="_blank" class="btn btn-sm btn-light-primary fw-bold">
                                View in Stripe Dashboard ↗
                            </a>
                        </div>
                        @endif
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex flex-column gap-3">
                            <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted fs-8 text-uppercase fw-bold d-block">Stripe Checkout Session ID</span>
                                    <span class="font-monospace fs-7 text-gray-900">{{ $purchase->stripe_checkout_session_id ?: 'None' }}</span>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted fs-8 text-uppercase fw-bold d-block">Stripe Payment Intent ID</span>
                                    <span class="font-monospace fs-7 text-gray-900">{{ $purchase->stripe_payment_intent_id ?: 'None' }}</span>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted fs-8 text-uppercase fw-bold d-block">Stripe Customer ID</span>
                                    <span class="font-monospace fs-7 text-gray-900">{{ $purchase->stripe_customer_id ?: 'None' }}</span>
                                </div>
                            </div>

                            <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted fs-8 text-uppercase fw-bold d-block">Stripe Event ID</span>
                                    <span class="font-monospace fs-7 text-gray-900">{{ $purchase->stripe_event_id ?: 'None' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Raw Metadata Card -->
                @if($purchase->metadata && count($purchase->metadata) > 0)
                <div class="card card-flush shadow-sm">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Transaction Metadata</h3>
                    </div>
                    <div class="card-body pt-0">
                        <pre class="bg-light p-4 rounded fs-7 mb-0"><code>{{ json_encode($purchase->metadata, JSON_PRETTY_PRINT) }}</code></pre>
                    </div>
                </div>
                @endif
            </div>
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
