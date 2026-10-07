@extends('backend.layouts.app')
@section('title', 'Admin Audit Trail')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                🛡️ Administrative Audit Trail
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Tamper-evident logs of administrative actions, manual enrollments, refunds & status mutations</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-light">← Dashboard</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">
        <div class="card card-flush shadow-sm">
            <div class="card-header pt-6">
                <!-- Search & Filter Form -->
                <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="w-100">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <div class="position-relative">
                                <input type="text"
                                    name="search"
                                    value="{{ request('search') }}"
                                    class="form-control form-control-sm form-control-solid ps-10"
                                    placeholder="Search admin, action, notes, IP..." />
                                <span class="position-absolute top-50 translate-middle-y ms-3 text-muted">
                                    <i class="bi bi-search"></i>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select name="action" class="form-select form-select-sm form-select-solid">
                                <option value="">All Action Types</option>
                                @foreach($actionTypes as $type)
                                    <option value="{{ $type }}" {{ request('action') === $type ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $type)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-light">Reset</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th>Timestamp</th>
                                <th>Admin User</th>
                                <th>Action</th>
                                <th>Target Entity</th>
                                <th>Notes / Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-700 fs-7">
                            @forelse($logs as $log)
                            @php
                                $badgeColor = match(true) {
                                    str_contains($log->action, 'refund')    => 'badge-light-danger text-danger',
                                    str_contains($log->action, 'enroll')    => 'badge-light-success text-success',
                                    str_contains($log->action, 'delete')    => 'badge-light-danger text-danger',
                                    str_contains($log->action, 'toggle')    => 'badge-light-warning text-warning',
                                    str_contains($log->action, 'export')    => 'badge-light-info text-info',
                                    default                                 => 'badge-light-primary text-primary',
                                };
                            @endphp
                            <tr>
                                <td class="text-nowrap text-gray-600">
                                    {{ $log->created_at->format('d M Y, H:i:s') }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-25px symbol-circle me-2 bg-light-primary">
                                            <span class="symbol-label text-primary fw-bold fs-8">
                                                {{ strtoupper(substr($log->user_name, 0, 1)) }}
                                            </span>
                                        </div>
                                        <span class="fw-bold text-gray-800">{{ $log->user_name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $badgeColor }} fw-bold fs-8">
                                        {{ ucwords(str_replace('_', ' ', $log->action)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->auditable_type)
                                        <span class="badge badge-light-secondary fs-8">
                                            {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                        </span>
                                    @else
                                        <span class="text-muted fs-8">—</span>
                                    @endif
                                </td>
                                <td class="max-w-300px text-break">
                                    {{ $log->notes ?: '—' }}
                                </td>
                                <td class="font-monospace fs-8 text-muted">
                                    {{ $log->ip_address ?: '—' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-6">
                                    No audit log entries recorded yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
