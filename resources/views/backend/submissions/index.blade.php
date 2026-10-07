@extends('backend.layouts.app')
@section('title', 'Inquiries & Leads Inbox')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📬 Inbound Leads & Contact Inquiries
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Review admissions inquiries, course enrollments, free assessment requests, and tutor applications</span>
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

        <div class="card card-flush shadow-sm">
            <!--begin::Card header-->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <form method="GET" action="{{ route('admin.submissions.index') }}" class="d-flex flex-wrap align-items-center gap-3 w-100">
                    <!-- Search -->
                    <div class="d-flex align-items-center position-relative my-1">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid w-250px ps-4" placeholder="Name, email, phone or subject..." />
                    </div>

                    <!-- Type Filter -->
                    <select name="type" class="form-select form-select-solid w-175px">
                        <option value="">Form: All Types</option>
                        <option value="contact" {{ request('type') === 'contact' ? 'selected' : '' }}>General Contact</option>
                        <option value="enrollment" {{ request('type') === 'enrollment' ? 'selected' : '' }}>Course Enrollment</option>
                        <option value="assessment" {{ request('type') === 'assessment' ? 'selected' : '' }}>Free Assessment</option>
                        <option value="tutor" {{ request('type') === 'tutor' ? 'selected' : '' }}>Become a Tutor</option>
                    </select>

                    <!-- Read Status Filter -->
                    <select name="read_status" class="form-select form-select-solid w-150px">
                        <option value="">Read: All</option>
                        <option value="unread" {{ request('read_status') === 'unread' ? 'selected' : '' }}>Unread Only</option>
                        <option value="read" {{ request('read_status') === 'read' ? 'selected' : '' }}>Read Only</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    @if(request()->hasAny(['search', 'type', 'read_status']))
                        <a href="{{ route('admin.submissions.index') }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                    <a href="{{ route('admin.submissions.export', request()->query()) }}" class="btn btn-sm btn-light-success border ms-auto" title="Export current filtered submissions to CSV">
                        <i class="fas fa-file-csv fa-xs me-1"></i> Export CSV
                    </a>
                </form>
            </div>
            <!--end::Card header-->

            <!--begin::Card body-->
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-striped table-hover fs-7 gy-3">
                        <thead>
                            <tr class="text-start text-gray-700 fw-bold fs-7 text-uppercase gs-0 bg-light">
                                <th class="min-w-120px ps-3">Form Type</th>
                                <th class="min-w-160px">Applicant Name</th>
                                <th class="min-w-160px">Email</th>
                                <th class="min-w-110px">Phone</th>
                                <th class="min-w-180px">Subject / Details</th>
                                <th class="min-w-90px text-center">Status</th>
                                <th class="min-w-120px">Date</th>
                                <th class="min-w-100px text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @forelse($submissions as $submission)
                            <tr class="{{ $submission->isRead() ? '' : 'fw-bold bg-light-warning' }}">
                                <td class="ps-3">
                                    <span class="badge {{ $submission->typeBadgeClass() }} fs-8">{{ $submission->typeLabel() }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('admin.submissions.show', $submission->id) }}" class="text-gray-900 text-hover-primary fw-bold fs-6">
                                        {{ $submission->name }}
                                    </a>
                                </td>
                                <td>{{ $submission->email }}</td>
                                <td>{{ $submission->phone ?: '-' }}</td>
                                <td>
                                    <span class="text-gray-800">{{ Str::limit($submission->subject ?: $submission->message, 45) }}</span>
                                </td>
                                <td class="text-center">
                                    @if($submission->isRead())
                                        <span class="badge badge-light-secondary fs-8">Read</span>
                                    @else
                                        <span class="badge badge-light-warning text-warning fw-bold fs-8">NEW</span>
                                    @endif
                                </td>
                                <td class="text-muted fs-7">{{ $submission->created_at->format('d M Y, H:i') }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.submissions.show', $submission->id) }}" class="btn btn-sm btn-icon btn-light-primary" title="View Submission">
                                            <i class="fas fa-eye fs-7"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.submissions.read', $submission->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-icon btn-light" title="{{ $submission->isRead() ? 'Mark as Unread' : 'Mark as Read' }}">
                                                <i class="fas {{ $submission->isRead() ? 'fa-envelope' : 'fa-envelope-open' }} fs-7"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-6">No inquiry submissions found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap pt-4">
                    <div class="text-muted fs-7">
                        Showing {{ $submissions->firstItem() ?? 0 }} to {{ $submissions->lastItem() ?? 0 }} of {{ $submissions->total() }} submissions
                    </div>
                    <div>
                        {{ $submissions->links() }}
                    </div>
                </div>
            </div>
            <!--end::Card body-->
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
