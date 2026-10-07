@extends('backend.layouts.app')
@section('title', 'Certificates & Credentials Management')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-2">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📜 Certificate Registry &amp; Credentials
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Issue official course completion credentials and manage public verification records</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.certificates.export', request()->query()) }}" class="btn btn-sm btn-light-success border">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <button type="button" class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#kt_modal_issue_certificate">
                <i class="fas fa-plus me-1"></i> Issue Certificate
            </button>
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

        <!-- Metric Cards -->
        <div class="row g-5 g-xl-8 mb-6">
            <div class="col-xl-4 col-md-4">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-2hx fw-bold text-gray-900 lh-1">{{ $stats['total'] }}</span>
                            <span class="d-block text-muted fs-7 fw-semibold mt-1">Total Issued Credentials</span>
                        </div>
                        <span class="badge badge-light-primary p-3 fs-3">📜</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-4">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-2hx fw-bold text-success lh-1">{{ $stats['active'] }}</span>
                            <span class="d-block text-muted fs-7 fw-semibold mt-1">Active Verifiable Credentials</span>
                        </div>
                        <span class="badge badge-light-success p-3 fs-3">✅</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-4">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="fs-2hx fw-bold text-danger lh-1">{{ $stats['revoked'] }}</span>
                            <span class="d-block text-muted fs-7 fw-semibold mt-1">Revoked Credentials</span>
                        </div>
                        <span class="badge badge-light-danger p-3 fs-3">🚫</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card card-flush shadow-sm mb-6">
            <div class="card-body p-5">
                <form method="GET" action="{{ route('admin.certificates.index') }}" class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control form-control-sm border-start-0"
                                   placeholder="Search by student, serial #, hash..."
                                   value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>All Statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                            <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>Revoked Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                        @if(request()->anyFilled(['search', 'status', 'course_id']))
                            <a href="{{ route('admin.certificates.index') }}" class="btn btn-sm btn-light" title="Reset">
                                <i class="fas fa-undo"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Certificates Table Card -->
        <div class="card card-flush shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-6 mb-0">
                        <thead>
                            <tr class="fw-bold fs-7 text-gray-500 text-uppercase bg-light">
                                <th class="min-w-150px">Certificate Serial</th>
                                <th class="min-w-180px">Learner Name</th>
                                <th class="min-w-180px">Course</th>
                                <th class="min-w-100px">Grade</th>
                                <th class="min-w-120px">Issued Date</th>
                                <th class="min-w-100px">Status</th>
                                <th class="min-w-120px text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fs-6">
                            @forelse($certificates as $cert)
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <code class="fw-bold fs-7 text-dark">{{ $cert->certificate_number }}</code>
                                        <a href="{{ $cert->verificationUrl() }}" target="_blank" class="text-primary fs-8 text-hover-underline mt-1">
                                            <i class="fas fa-external-link-alt fa-xs me-1"></i> Public Registry
                                        </a>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-35px symbol-circle bg-light-primary text-primary fw-bold me-3">
                                            <span class="symbol-label">{{ strtoupper(substr($cert->student->name ?? 'L', 0, 1)) }}</span>
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.students.show', $cert->student) }}" class="text-gray-900 fw-bold text-hover-primary">
                                                {{ $cert->student->name }}
                                            </a>
                                            <span class="d-block text-muted fs-8">{{ $cert->student->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-gray-800">{{ $cert->course->name }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-light fw-bold fs-8">{{ $cert->grade ?? 'Passed' }}</span>
                                </td>
                                <td>
                                    <span class="text-gray-700 fs-7">{{ $cert->issued_at->format('d M Y') }}</span>
                                    <span class="d-block text-muted fs-8">by {{ $cert->issuedBy->name ?? 'System' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $cert->statusBadgeClass() }} fs-8 fw-bold text-uppercase">
                                        {{ $cert->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ $cert->verificationUrl() }}" target="_blank" class="btn btn-sm btn-icon btn-light-info" title="View Public Credential">
                                            <i class="fas fa-eye fa-xs"></i>
                                        </a>
                                        @if($cert->isActive())
                                            <button type="button" class="btn btn-sm btn-icon btn-light-danger"
                                                    title="Revoke Certificate"
                                                    onclick="openRevokeModal('{{ $cert->id }}', '{{ $cert->certificate_number }}', '{{ addslashes($cert->student->name) }}')">
                                                <i class="fas fa-ban fa-xs"></i>
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route('admin.certificates.restore', $cert) }}" class="d-inline" onsubmit="return confirm('Restore this certificate to active?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-icon btn-light-success" title="Restore Certificate">
                                                    <i class="fas fa-redo fa-xs"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-muted">
                                    <div class="fs-6 fw-semibold">No certificates found matching your criteria.</div>
                                    <span class="fs-7 text-muted">Issue credentials to admitted students who completed their courses.</span>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($certificates->hasPages())
                <div class="p-4 border-top">
                    {{ $certificates->links() }}
                </div>
                @endif
            </div>
        </div>

    </div>
</div>
<!--end::Content-->

<!-- Modal: Issue Certificate -->
<div class="modal fade" id="kt_modal_issue_certificate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold">📜 Issue Official Credential</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            <form action="{{ route('admin.certificates.store') }}" method="POST">
                @csrf
                <div class="modal-body py-6 px-lg-8">
                    <div class="mb-4">
                        <label class="form-label fw-bold required">Select Learner</label>
                        <select name="student_id" class="form-select form-select-solid" required>
                            <option value="">-- Choose Learner --</option>
                            @foreach($students as $st)
                                <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Select Course</label>
                        <select name="course_id" class="form-select form-select-solid" required>
                            <option value="">-- Choose Course --</option>
                            @foreach($courses as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Achievement / Grade</label>
                            <input type="text" name="grade" class="form-control form-control-solid" placeholder="e.g. Distinction, 92%, Pass" value="Passed">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Issue Date</label>
                            <input type="date" name="issued_at" class="form-control form-control-solid" value="{{ date('Y-m-d') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Generate &amp; Issue Credential</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Revoke Certificate -->
<div class="modal fade" id="kt_modal_revoke_certificate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold text-danger">🚫 Revoke Credential</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i>
                </div>
            </div>
            <form id="revokeForm" method="POST" action="">
                @csrf
                <div class="modal-body py-6 px-lg-8">
                    <p class="text-gray-700 fs-7 mb-4">
                        You are about to revoke certificate <strong id="revokeSerial"></strong> for learner <strong id="revokeName"></strong>. The public verification registry will mark this certificate as revoked.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold required">Reason for Revocation</label>
                        <textarea name="revocation_reason" class="form-control form-control-solid" rows="3" placeholder="e.g. Incomplete verification, exam malpractice, issued in error..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold">Confirm Revocation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRevokeModal(id, serial, name) {
    document.getElementById('revokeSerial').innerText = serial;
    document.getElementById('revokeName').innerText = name;
    document.getElementById('revokeForm').action = "{{ url('admin/certificates') }}/" + id + "/revoke";
    var modal = new bootstrap.Modal(document.getElementById('kt_modal_revoke_certificate'));
    modal.show();
}
</script>
@endsection
