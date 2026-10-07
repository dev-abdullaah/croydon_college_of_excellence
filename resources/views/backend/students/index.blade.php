@extends('backend.layouts.app')
@section('title', 'Student Database & Analytics')

@section('page')
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <div class="d-flex flex-column flex-column-fluid">
        <!--begin::Toolbar-->
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
            <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">👥 Student Database</h1>
                    <span class="text-muted fs-7 fw-semibold mt-1">Track student enrollment history, progress & account statuses</span>
                </div>
            </div>
        </div>
        <!--end::Toolbar-->

        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-xxl">
                
                <div class="card card-flush">
                    <div class="card-body p-6">

                        <!-- KPI Cards from Snapkart -->
                        <div class="row g-5 mb-5">
                            <div class="col-xl-3 col-md-6">
                                <div class="card card-flush h-100 bg-light-primary border border-primary border-opacity-25">
                                    <div class="card-body p-5">
                                        <div class="fs-4 text-gray-700 fw-semibold">Total Students</div>
                                        <div class="fs-2hx fw-bold text-primary my-1">{{ number_format($totalStudents) }}</div>
                                        <span class="fs-7 text-muted">Registered accounts</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="card card-flush h-100 bg-light-success border border-success border-opacity-25">
                                    <div class="card-body p-5">
                                        <div class="fs-4 text-gray-700 fw-semibold">Active Learners</div>
                                        <div class="fs-2hx fw-bold text-success my-1">{{ number_format($activeStudents) }}</div>
                                        <span class="fs-7 text-muted">Can log in & learn</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="card card-flush h-100 bg-light-info border border-info border-opacity-25">
                                    <div class="card-body p-5">
                                        <div class="fs-4 text-gray-700 fw-semibold">Enrolled Students</div>
                                        <div class="fs-2hx fw-bold text-info my-1">{{ number_format($enrolledStudents) }}</div>
                                        <span class="fs-7 text-muted">> 0 Paid courses</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6">
                                <div class="card card-flush h-100 bg-light-dark border border-secondary border-opacity-25">
                                    <div class="card-body p-5">
                                        <div class="fs-4 text-gray-700 fw-semibold">Verified Accounts</div>
                                        <div class="fs-2hx fw-bold text-dark my-1">{{ number_format($verifiedStudents) }}</div>
                                        <span class="fs-7 text-muted">Confirmed email</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Table Card -->
                        <div class="card card-flush">
                            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                                <form action="{{ route('admin.students.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-3 w-100">
                                    <div class="position-relative flex-grow-1" style="max-width: 320px;">
                                        <input type="text" name="search" class="form-control form-control-sm form-control-solid ps-10" placeholder="Search by name or email..." value="{{ request('search') }}">
                                        <span class="position-absolute top-50 translate-middle-y ms-3">
                                            <i class="bi bi-search text-gray-500"></i>
                                        </span>
                                    </div>
                                    <select name="status" class="form-select form-select-sm form-select-solid" style="max-width: 160px;">
                                        <option value="">All Statuses</option>
                                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                                        <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                    @if(request('search') || request('status'))
                                        <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-light">Reset</a>
                                    @endif
                                    <a href="{{ route('admin.students.export', request()->query()) }}" class="btn btn-sm btn-light-success border ms-auto d-inline-flex align-items-center gap-1" title="Export students to CSV">
                                        <i class="fas fa-file-csv fa-xs"></i> <span>Export CSV</span>
                                    </a>
                                </form>
                            </div>

                            <div class="card-body pt-0">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                                        <thead>
                                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                                <th>Student Info</th>
                                                <th>Email</th>
                                                <th>Enrolled Courses</th>
                                                <th>Status</th>
                                                <th>Joined Date</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-600">
                                            @forelse($students as $student)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol symbol-40px me-3">
                                                            <span class="symbol-label bg-light-primary text-primary fw-bold fs-4">
                                                                {{ strtoupper(substr($student->name ?? 'S', 0, 1)) }}
                                                            </span>
                                                        </div>
                                                        <div class="d-flex flex-column">
                                                            <a href="{{ route('admin.students.show', $student) }}" class="text-gray-800 text-hover-primary fw-bold">
                                                                {{ $student->name }}
                                                            </a>
                                                            @if($student->email_verified_at)
                                                                <span class="badge badge-light-success fs-9 align-self-start mt-1">Verified</span>
                                                            @else
                                                                <span class="badge badge-light-warning fs-9 align-self-start mt-1">Unverified</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code>{{ $student->email }}</code>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $student->purchases_count > 0 ? 'badge-light-success' : 'badge-light-secondary' }} fw-bold px-3 py-2">
                                                        {{ $student->purchases_count }} Courses
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $student->isActive() ? 'badge-light-success' : 'badge-light-danger' }} fw-bold fs-8">
                                                        {{ $student->isActive() ? 'Active' : 'Suspended' }}
                                                    </span>
                                                </td>
                                                <td class="text-muted fs-7">
                                                    {{ $student->created_at->format('d M Y') }}
                                                </td>
                                                <td class="text-end">
                                                    <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-light-primary fw-bold me-1">
                                                        👤 Profile
                                                    </a>
                                                    <form method="POST" action="{{ route('admin.students.toggle-status', $student) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm {{ $student->isActive() ? 'btn-light-danger' : 'btn-light-success' }} fw-bold">
                                                            {{ $student->isActive() ? 'Suspend' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">No students found matching the criteria.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-4">
                                    {{ $students->links() }}
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <!--end::Content-->
    </div>
</div>
@endsection
