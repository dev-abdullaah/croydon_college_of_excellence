@extends('backend.layouts.app')
@section('title', 'Inquiry from ' . $submission->name)

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📩 Lead Inquiry: {{ $submission->name }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Review contact information, applicant metadata, and follow-up notes</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.submissions.index') }}" class="btn btn-sm btn-light">← Back to Inbox</a>
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
            <!-- Left Column: Lead Details & Message -->
            <div class="col-xl-8">
                <!-- Applicant Contact Card -->
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <div class="d-flex align-items-center">
                            <span class="badge {{ $submission->typeBadgeClass() }} fs-7 me-3">{{ $submission->typeLabel() }}</span>
                            <h3 class="card-title fw-bold text-gray-800 m-0">{{ $submission->subject ?: 'Submission Details' }}</h3>
                        </div>
                        <div class="card-toolbar">
                            <a href="mailto:{{ $submission->email }}?subject=Re: {{ urlencode($submission->subject ?: 'Croydon College of Excellence Inquiry') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-reply me-1"></i> Reply via Email
                            </a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <span class="text-muted fs-7 d-block">Sender Name:</span>
                                <span class="fw-bold fs-6 text-gray-900">{{ $submission->name }}</span>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted fs-7 d-block">Email Address:</span>
                                <a href="mailto:{{ $submission->email }}" class="fw-bold fs-6 text-primary">{{ $submission->email }}</a>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted fs-7 d-block">Phone Number:</span>
                                <span class="fw-bold fs-6 text-gray-900">{{ $submission->phone ?: 'Not provided' }}</span>
                            </div>
                        </div>

                        <!-- Structured Metadata (if available) -->
                        @if($submission->metadata && is_array($submission->metadata))
                        <div class="p-4 bg-light rounded mb-5">
                            <h4 class="fs-6 fw-bold text-gray-800 mb-3">Structured Application Metadata</h4>
                            <div class="row g-3">
                                @if(isset($submission->metadata['dob']))
                                <div class="col-sm-6">
                                    <span class="text-muted fs-7 d-block">Date of Birth:</span>
                                    <span class="fw-semibold text-gray-800">{{ $submission->metadata['dob'] }}</span>
                                </div>
                                @endif

                                @if(isset($submission->metadata['gender']))
                                <div class="col-sm-6">
                                    <span class="text-muted fs-7 d-block">Gender:</span>
                                    <span class="fw-semibold text-gray-800 text-capitalize">{{ $submission->metadata['gender'] }}</span>
                                </div>
                                @endif

                                @if(isset($submission->metadata['guardian_name']))
                                <div class="col-sm-6">
                                    <span class="text-muted fs-7 d-block">Guardian Name:</span>
                                    <span class="fw-semibold text-gray-800">{{ $submission->metadata['guardian_name'] ?: 'N/A' }}</span>
                                </div>
                                @endif

                                @if(isset($submission->metadata['guardian_contact']))
                                <div class="col-sm-6">
                                    <span class="text-muted fs-7 d-block">Guardian Contact:</span>
                                    <span class="fw-semibold text-gray-800">{{ $submission->metadata['guardian_contact'] ?: 'N/A' }}</span>
                                </div>
                                @endif

                                @if(isset($submission->metadata['qualification']))
                                <div class="col-sm-6">
                                    <span class="text-muted fs-7 d-block">Teaching Qualification:</span>
                                    <span class="fw-semibold text-gray-800">{{ $submission->metadata['qualification'] }}</span>
                                </div>
                                @endif

                                @if(isset($submission->metadata['address']))
                                <div class="col-12">
                                    <span class="text-muted fs-7 d-block">Residential Address:</span>
                                    <span class="fw-semibold text-gray-800">{{ $submission->metadata['address'] }}</span>
                                </div>
                                @endif

                                @if(isset($submission->metadata['subjects']) && is_array($submission->metadata['subjects']))
                                <div class="col-12">
                                    <span class="text-muted fs-7 d-block mb-1">Selected Subjects / Modules:</span>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($submission->metadata['subjects'] as $subject)
                                            <span class="badge badge-light-primary fw-bold">{{ $subject }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Message Body -->
                        <div class="border-top pt-4">
                            <h4 class="fs-6 fw-bold text-gray-800 mb-2">Message Content</h4>
                            <div class="p-4 bg-light rounded text-gray-800 fs-6" style="white-space: pre-wrap;">{{ $submission->message ?: 'No message body provided.' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Follow-up & Administrator Notes -->
            <div class="col-xl-4">
                <div class="card card-flush shadow-sm mb-5">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Lead Workflow Status</h3>
                    </div>
                    <div class="card-body pt-0">
                        <form method="POST" action="{{ route('admin.submissions.notes', $submission->id) }}">
                            @csrf
                            @method('PATCH')

                            <div class="mb-4">
                                <label class="form-label fw-bold">Processing Status</label>
                                <select name="status" class="form-select form-select-solid">
                                    <option value="new" {{ $submission->status === 'new' ? 'selected' : '' }}>New Lead</option>
                                    <option value="contacted" {{ $submission->status === 'contacted' ? 'selected' : '' }}>Contacted / In Progress</option>
                                    <option value="resolved" {{ $submission->status === 'resolved' ? 'selected' : '' }}>Resolved / Enrolled</option>
                                    <option value="archived" {{ $submission->status === 'archived' ? 'selected' : '' }}>Archived</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Internal Administrator Notes</label>
                                <textarea name="admin_notes" class="form-control form-control-solid" rows="5" placeholder="Add follow-up notes, phone call summaries, or next steps...">{{ old('admin_notes', $submission->admin_notes) }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">Save Notes</button>
                        </form>
                    </div>
                </div>

                <div class="card card-flush shadow-sm">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800">Submission Metadata</h3>
                    </div>
                    <div class="card-body pt-0 fs-7">
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span class="text-muted">Received Date:</span>
                            <span class="fw-bold">{{ $submission->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span class="text-muted">First Inspected:</span>
                            <span class="fw-bold text-success">{{ $submission->read_at ? $submission->read_at->format('d M Y, H:i') : 'Just now' }}</span>
                        </div>
                        <div class="mt-4 pt-2">
                            <form method="POST" action="{{ route('admin.submissions.destroy', $submission->id) }}" onsubmit="return confirm('Are you sure you want to permanently delete this submission?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-light-danger w-100 fw-bold">
                                    <i class="fas fa-trash me-1"></i> Delete Submission
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
