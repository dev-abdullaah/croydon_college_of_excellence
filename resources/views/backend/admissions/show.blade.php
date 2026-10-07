@extends('backend.layouts.app')
@section('title', 'Admission Record #' . $admission->id . ' - ' . ($admission->student->name ?? 'Learner'))

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-2">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                Admission Record #{{ $admission->id }} &mdash; {{ $admission->student->name ?? 'Learner' }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Review applicant details, manage manual payment approval, and control course availability</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.admissions.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left fa-xs me-1"></i> Back to Admissions
            </a>
            @if($admission->student)
                <a href="{{ route('admin.students.impersonate', $admission->student) }}" class="btn btn-sm btn-light-info">
                    <i class="fas fa-user-secret fa-xs me-1"></i> View Portal as Student
                </a>
            @endif
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center p-4 mb-5 shadow-sm">
            <i class="fas fa-check-circle fs-3 text-success me-3"></i>
            <span class="fw-semibold">{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center p-4 mb-5 shadow-sm">
            <i class="fas fa-exclamation-circle fs-3 text-danger me-3"></i>
            <span class="fw-semibold">{{ session('error') }}</span>
        </div>
        @endif

        <!-- Status Card Alert -->
        <div class="card card-flush shadow-sm mb-6 border border-2 {{ $admission->isAdmitted() ? 'border-success bg-light-success' : ($admission->isPending() ? 'border-warning bg-light-warning' : 'border-danger bg-light-danger') }}">
            <div class="card-body p-5 d-flex flex-wrap align-items-center justify-content-between gap-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge {{ $admission->statusBadgeClass() }} fs-5 px-4 py-2">
                        {{ $admission->statusLabel() }}
                    </span>
                    <div>
                        <div class="fs-5 fw-bold text-dark">
                            Course: {{ $admission->course->name }}
                        </div>
                        <div class="text-muted fs-8">
                            Fee: £{{ number_format($admission->amount / 100, 2) }}
                            @if($admission->admitted_at)
                                &middot; Admitted on {{ $admission->admitted_at->format('M d, Y H:i') }}
                            @endif
                            @if($admission->admittedBy)
                                by {{ $admission->admittedBy->name }}
                            @endif
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if($admission->isPending())
                        <form method="POST" action="{{ route('admin.admissions.approve', $admission) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success px-4 py-2 fw-bold">
                                <i class="fas fa-check me-1"></i> Approve &amp; Unlock Course
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.admissions.reject', $admission) }}" class="d-inline" onsubmit="return confirm('Decline this admission request?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-light-dark px-3 py-2">
                                Decline
                            </button>
                        </form>
                    @elseif($admission->isAdmitted())
                        <form method="POST" action="{{ route('admin.admissions.revoke', $admission) }}" class="d-inline" onsubmit="return confirm('Revoke course access from {{ $admission->student->name }}? The learner will immediately lose access to this course.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger px-4 py-2 fw-bold">
                                <i class="fas fa-ban me-1"></i> Revoke Course Access
                            </button>
                        </form>
                    @elseif($admission->isRevoked())
                        <form method="POST" action="{{ route('admin.admissions.approve', $admission) }}" class="d-inline" onsubmit="return confirm('Restore course access for {{ $admission->student->name }}?');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success px-4 py-2 fw-bold">
                                <i class="fas fa-redo me-1"></i> Restore &amp; Re-activate Access
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8">
            <!-- Left Column: Learner & Application Details -->
            <div class="col-xl-6">
                <!-- Learner Card -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">
                            👤 Learner Profile
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        @if($admission->student)
                        <div class="table-responsive">
                            <table class="table align-middle fs-7 gy-3">
                                <tbody>
                                    <tr>
                                        <td class="text-muted fw-semibold w-150px">Full Name:</td>
                                        <td class="fw-bold text-gray-800">
                                            <a href="{{ route('admin.students.show', $admission->student) }}">
                                                {{ $admission->student->name }}
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Email Address:</td>
                                        <td class="fw-bold text-gray-800">
                                            <a href="mailto:{{ $admission->student->email }}">
                                                {{ $admission->student->email }}
                                            </a>
                                            @if($admission->student->email_verified_at)
                                                <span class="badge badge-light-success fs-9 ms-1">Verified</span>
                                            @else
                                                <span class="badge badge-light-warning fs-9 ms-1">Unverified</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Contact Phone:</td>
                                        <td class="fw-bold">
                                            @if($admission->contact_phone || $admission->student->phone)
                                                <a href="tel:{{ $admission->contact_phone ?? $admission->student->phone }}" class="text-primary fs-7">
                                                    <i class="fas fa-phone-alt fa-xs me-1"></i> {{ $admission->contact_phone ?? $admission->student->phone }}
                                                </a>
                                            @else
                                                <span class="text-muted">Not provided</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Account Status:</td>
                                        <td>
                                            @if($admission->student->isActive())
                                                <span class="badge badge-light-success fs-8">Active Account</span>
                                            @else
                                                <span class="badge badge-light-danger fs-8">Suspended</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Registered At:</td>
                                        <td class="text-gray-700">{{ $admission->student->created_at?->format('M d, Y H:i') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-secondary">
                            Guest learner details: {{ $admission->customer_name }} ({{ $admission->customer_email }})
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Application Notes from Learner -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">
                            📝 Notes from Learner
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        @if($admission->learner_notes)
                            <div class="p-4 bg-light rounded text-gray-800 fs-7 border">
                                "{{ $admission->learner_notes }}"
                            </div>
                        @else
                            <span class="text-muted fs-7">No notes or special requirements submitted by learner.</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Course, Payment & Administrator Actions -->
            <div class="col-xl-6">
                <!-- Course Details Card -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">
                            📚 Enrolled Course &amp; Fees
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle fs-7 gy-3">
                                <tbody>
                                    <tr>
                                        <td class="text-muted fw-semibold w-175px">Course Title:</td>
                                        <td class="fw-bold text-dark fs-6">{{ $admission->course->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Standard Price:</td>
                                        <td class="fw-bold">£{{ number_format($admission->course->price / 100, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Agreed Tuition Fee:</td>
                                        <td class="fw-bold text-primary fs-6">£{{ number_format($admission->amount / 100, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Payment Method:</td>
                                        <td>
                                            <span class="badge badge-light-primary fw-bold fs-7">
                                                {{ ucfirst(str_replace('_', ' ', $admission->payment_method ?? 'Pending')) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">Application Date:</td>
                                        <td class="text-gray-700">{{ $admission->requested_at?->format('M d, Y H:i:s') ?? $admission->created_at->format('M d, Y H:i:s') }}</td>
                                    </tr>
                                    @if($admission->admitted_at)
                                    <tr>
                                        <td class="text-muted fw-semibold">Admission Approved:</td>
                                        <td class="text-success fw-bold">{{ $admission->admitted_at->format('M d, Y H:i:s') }}</td>
                                    </tr>
                                    @endif
                                    @if($admission->revoked_at)
                                    <tr>
                                        <td class="text-muted fw-semibold">Access Revoked:</td>
                                        <td class="text-danger fw-bold">{{ $admission->revoked_at->format('M d, Y H:i:s') }}</td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Internal Administrator Notes Card -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">
                            🔒 Internal Admissions Notes &amp; Payment Verification
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <form method="POST" action="{{ route('admin.admissions.notes', $admission) }}">
                            @csrf
                            @method('PATCH')

                            <div class="mb-4">
                                <label class="form-label fw-semibold fs-8 text-muted">Payment Method Verified</label>
                                <select name="payment_method" class="form-select form-select-solid form-select-sm">
                                    <option value="bank_transfer" {{ $admission->payment_method === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer / BACS</option>
                                    <option value="cash" {{ $admission->payment_method === 'cash' ? 'selected' : '' }}>Cash Received</option>
                                    <option value="card" {{ $admission->payment_method === 'card' ? 'selected' : '' }}>Card Payment (Phone / POS)</option>
                                    <option value="scholarship" {{ $admission->payment_method === 'scholarship' ? 'selected' : '' }}>Scholarship / Grant</option>
                                    <option value="other" {{ $admission->payment_method === 'other' ? 'selected' : '' }}>Other Sponsor</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold fs-8 text-muted">Internal Office Notes / Bank Reference</label>
                                <textarea name="admin_notes" rows="3" class="form-control form-control-solid fs-7" placeholder="e.g. Bank statement ref 4091 verified on 06 Oct. Learner phoned and confirmed.">{{ old('admin_notes', $admission->admin_notes) }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-save fa-xs me-1"></i> Save Office Notes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
