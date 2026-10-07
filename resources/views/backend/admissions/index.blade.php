@extends('backend.layouts.app')
@section('title', 'Student Admissions & Course Access')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-2">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                🎓 Student Admissions &amp; Course Access
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Review pending admission applications, verify offline payments, and grant or revoke learning access</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_manual_admit">
                <i class="fas fa-user-plus fa-xs me-1"></i> Direct Admission
            </button>
            <a href="{{ route('admin.admissions.export', request()->query()) }}" class="btn btn-sm btn-light-success border">
                <i class="fas fa-file-csv fa-xs me-1"></i> Export CSV
            </a>
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

        <!-- KPI Metrics Row -->
        <div class="row g-5 g-xl-8 mb-5">
            <!-- Pending Requests -->
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('admin.admissions.index', ['status' => 'pending']) }}" class="card card-flush bg-light-warning border border-warning border-dashed h-100 text-decoration-none">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7 d-flex align-items-center justify-content-between">
                            Pending Requests
                            <span class="badge badge-warning">Action Needed</span>
                        </span>
                        <div class="fs-2hx fw-bold text-warning mt-1">{{ number_format($stats['pending']) }}</div>
                        <span class="text-muted fs-8">Awaiting phone contact &amp; fee payment</span>
                    </div>
                </a>
            </div>

            <!-- Admitted Learners -->
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('admin.admissions.index', ['status' => 'admitted']) }}" class="card card-flush bg-light-success border border-success border-dashed h-100 text-decoration-none">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7">Admitted Learners (Active)</span>
                        <div class="fs-2hx fw-bold text-success mt-1">{{ number_format($stats['admitted']) }}</div>
                        <span class="text-muted fs-8">Currently studying with full access</span>
                    </div>
                </a>
            </div>

            <!-- Revoked Access -->
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('admin.admissions.index', ['status' => 'revoked']) }}" class="card card-flush bg-light-danger border border-danger border-dashed h-100 text-decoration-none">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7">Access Revoked / Suspended</span>
                        <div class="fs-2hx fw-bold text-danger mt-1">{{ number_format($stats['revoked']) }}</div>
                        <span class="text-muted fs-8">Courses made unavailable</span>
                    </div>
                </a>
            </div>

            <!-- Total Fee Revenue -->
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-light-primary border border-primary border-dashed h-100">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7">Total Tuition Fees</span>
                        <div class="fs-2hx fw-bold text-primary mt-1">£{{ number_format($stats['revenue'], 2) }}</div>
                        <span class="text-muted fs-8">From confirmed admissions</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-flush shadow-sm">
            <!-- Filter Bar -->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <form method="GET" action="{{ route('admin.admissions.index') }}" class="d-flex flex-wrap align-items-center gap-3 w-100">
                    <!-- Search Input -->
                    <div class="d-flex align-items-center position-relative my-1">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid w-250px ps-4" placeholder="Search name, phone, email, course..." />
                    </div>

                    <!-- Course Filter -->
                    <select name="course_id" class="form-select form-select-solid w-200px">
                        <option value="">All Courses &amp; Tests</option>
                        @foreach($courses as $c)
                            <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>

                    <!-- Status Filter Tabs -->
                    <select name="status" class="form-select form-select-solid w-175px">
                        <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Status: All Admissions</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>⏳ Pending Requests Only</option>
                        <option value="admitted" {{ request('status') === 'admitted' ? 'selected' : '' }}>🎓 Admitted (Active)</option>
                        <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>🛑 Revoked / Inactive</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Declined Requests</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    @if(request()->hasAny(['search', 'course_id', 'status']))
                        <a href="{{ route('admin.admissions.index') }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-striped table-hover fs-7 gy-3">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                                <th>Ref #</th>
                                <th>Learner</th>
                                <th>Course / Test</th>
                                <th>Tuition Fee</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Applied At</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 fw-semibold">
                            @forelse($admissions as $admission)
                            <tr>
                                <td>
                                    <span class="badge badge-light-dark font-monospace fs-8">
                                        #{{ $admission->id }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <a href="{{ route('admin.students.show', $admission->student_id) }}" class="text-gray-800 text-hover-primary fw-bold">
                                            {{ $admission->student->name ?? $admission->customer_name ?? 'Guest Learner' }}
                                        </a>
                                        <span class="text-muted fs-8">{{ $admission->student->email ?? $admission->customer_email }}</span>
                                        @if($admission->contact_phone || $admission->student?->phone)
                                            <a href="tel:{{ $admission->contact_phone ?? $admission->student?->phone }}" class="text-primary fs-8 fw-semibold mt-1">
                                                <i class="fas fa-phone-alt fa-xs me-1"></i> {{ $admission->contact_phone ?? $admission->student?->phone }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-light-info fw-bold fs-7">
                                        {{ $admission->course->name ?? 'Course #' . $admission->course_id }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">£{{ number_format($admission->amount / 100, 2) }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-light-secondary fs-8">
                                        {{ ucfirst(str_replace('_', ' ', $admission->payment_method ?? 'Not specified')) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $admission->statusBadgeClass() }} fs-8 fw-bold">
                                        {{ $admission->statusLabel() }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted fs-8">
                                        {{ $admission->requested_at?->format('M d, Y H:i') ?? $admission->created_at->format('M d, Y H:i') }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        @if($admission->isPending())
                                            <!-- Approve Admission -->
                                            <form method="POST" action="{{ route('admin.admissions.approve', $admission) }}" class="d-inline" onsubmit="return confirm('Approve admission for {{ $admission->student->name }} and grant course access?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success px-3 py-1 fs-8" title="Approve fee payment and unlock course access">
                                                    <i class="fas fa-check fa-xs me-1"></i> Approve &amp; Admit
                                                </button>
                                            </form>
                                        @elseif($admission->isAdmitted())
                                            <!-- Revoke Access -->
                                            <form method="POST" action="{{ route('admin.admissions.revoke', $admission) }}" class="d-inline" onsubmit="return confirm('Revoke course access from {{ $admission->student->name }}? The learner will no longer be able to access the course.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-danger px-3 py-1 fs-8" title="Make course unavailable to learner">
                                                    <i class="fas fa-ban fa-xs me-1"></i> Revoke Access
                                                </button>
                                            </form>
                                        @elseif($admission->isRevoked())
                                            <!-- Restore Admission -->
                                            <form method="POST" action="{{ route('admin.admissions.approve', $admission) }}" class="d-inline" onsubmit="return confirm('Restore course access for {{ $admission->student->name }}?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light-success px-3 py-1 fs-8" title="Restore course access">
                                                    <i class="fas fa-redo fa-xs me-1"></i> Restore Access
                                                </button>
                                            </form>
                                        @endif

                                        <!-- View Details -->
                                        <a href="{{ route('admin.admissions.show', $admission) }}" class="btn btn-icon btn-sm btn-light-primary" title="View Admission Record">
                                            <i class="fas fa-eye fa-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-10 text-muted">
                                    <i class="fas fa-user-graduate fs-2x mb-3 d-block text-gray-400"></i>
                                    No student admission records found matching current criteria.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    {{ $admissions->links() }}
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Content-->

<!-- Direct Manual Admission Modal -->
<div class="modal fade" id="kt_modal_manual_admit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Direct Student Admission</h3>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.admissions.manual-admit') }}">
                @csrf
                <div class="modal-body py-6 px-9">
                    <!-- Student Selection -->
                    <div class="mb-5">
                        <label class="form-label required fw-semibold">Select Student</label>
                        <select name="student_id" class="form-select form-select-solid" required>
                            <option value="">-- Choose registered student --</option>
                            @foreach($students as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Course Selection -->
                    <div class="mb-5">
                        <label class="form-label required fw-semibold">Select Course / Mock Test</label>
                        <select name="course_id" class="form-select form-select-solid" required>
                            <option value="">-- Choose course --</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} (£{{ number_format($c->price / 100, 2) }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-5">
                        <label class="form-label required fw-semibold">Payment Method Verified</label>
                        <select name="payment_method" class="form-select form-select-solid" required>
                            <option value="bank_transfer">Bank Transfer / BACS</option>
                            <option value="cash">Cash Received in Office</option>
                            <option value="card">Card Payment (Terminal / Phone)</option>
                            <option value="scholarship">Scholarship / Free Grant</option>
                            <option value="other">Other External Sponsor</option>
                        </select>
                    </div>

                    <!-- Fee Amount -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Tuition Fee Paid (£)</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-solid" placeholder="Leave empty for standard catalogue price" />
                    </div>

                    <!-- Admin Notes -->
                    <div class="mb-5">
                        <label class="form-label fw-semibold">Internal Notes / Receipt Reference</label>
                        <textarea name="admin_notes" rows="2" class="form-control form-control-solid" placeholder="e.g. Receipt #1042 issued by administrator"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Admit Student &amp; Unlock Access</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
