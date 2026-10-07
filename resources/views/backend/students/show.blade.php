@extends('backend.layouts.app')
@section('title', 'Student Profile: ' . $student->name)

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                👤 Student Dossier: {{ $student->name }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Detailed academic performance, purchased courses, and security logs</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.students.index') }}" class="btn btn-sm btn-light">← Back to Students</a>
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

        <!-- Profile Header Card -->
        <div class="card mb-6 shadow-sm">
            <div class="card-body pt-9 pb-0">
                <div class="d-flex flex-wrap flex-sm-nowrap mb-3">
                    <div class="me-7 mb-4">
                        <div class="symbol symbol-100px symbol-circle">
                            <span class="symbol-label bg-light-primary text-primary fw-bolder fs-2tx">
                                {{ strtoupper(substr($student->name, 0, 2)) }}
                            </span>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="text-gray-900 fs-2 fw-bold me-2">{{ $student->name }}</span>
                                    @if($student->isActive())
                                        <span class="badge badge-light-success fw-bold fs-8">Active Account</span>
                                    @else
                                        <span class="badge badge-light-danger fw-bold fs-8">Suspended Account</span>
                                    @endif

                                    @if($student->email_verified_at)
                                        <span class="badge badge-light-primary fw-bold fs-8 ms-2">Email Verified</span>
                                    @else
                                        <span class="badge badge-light-warning fw-bold fs-8 ms-2">Unverified</span>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2 text-muted">
                                    <span class="me-4"><i class="fas fa-envelope me-1"></i> {{ $student->email }}</span>
                                    <span class="me-4"><i class="fas fa-calendar-alt me-1"></i> Joined: {{ $student->created_at->format('d M Y') }}</span>
                                    <span><i class="fas fa-book me-1"></i> Purchases: {{ $purchases->where('status', 'paid')->count() }}</span>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#kt_modal_enroll_student">
                                    <i class="fas fa-graduation-cap me-1"></i> Enroll in Course
                                </button>

                                <a href="{{ route('admin.students.impersonate', $student->id) }}" class="btn btn-sm btn-light-info fw-bold" onclick="return confirm('Log in as student {{ $student->name }}? You will be redirected to the learner portal.');">
                                    <i class="fas fa-user-secret me-1"></i> Login as Student
                                </a>

                                <form method="POST" action="{{ route('admin.students.reset-password', $student->id) }}" onsubmit="return confirm('Send password reset email to {{ $student->email }}?');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-light-warning fw-bold">
                                        <i class="fas fa-key me-1"></i> Reset Password
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.students.toggle-status', $student->id) }}" onsubmit="return confirm('Are you sure you want to change this student\'s account status?');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $student->isActive() ? 'btn-light-danger' : 'btn-light-success' }} fw-bold">
                                        <i class="fas {{ $student->isActive() ? 'fa-user-slash' : 'fa-user-check' }} me-1"></i>
                                        {{ $student->isActive() ? 'Suspend Account' : 'Activate Account' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nav tabs -->
                <ul class="nav nav-custom nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-5 fw-bold mb-0">
                    <li class="nav-item">
                        <a class="nav-link text-active-primary pb-4 active" data-bs-toggle="tab" href="#kt_tab_purchases">
                            Course Admissions ({{ $purchases->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-active-primary pb-4" data-bs-toggle="tab" href="#kt_tab_progress">
                            Lesson Progress ({{ $lessonProgress->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-active-primary pb-4" data-bs-toggle="tab" href="#kt_tab_quizzes">
                            Quiz Attempts ({{ $quizAttempts->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-active-primary pb-4" data-bs-toggle="tab" href="#kt_tab_emails">
                            Secondary Emails ({{ $student->emails->count() }})
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-active-primary pb-4" data-bs-toggle="tab" href="#kt_tab_logins">
                            Login Audit ({{ $loginHistories->count() }})
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab contents -->
        <div class="tab-content" id="myTabContent">
            <!-- TAB 1: ADMISSIONS -->
            <div class="tab-pane fade show active" id="kt_tab_purchases" role="tabpanel">
                <div class="card card-flush shadow-sm">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gy-4">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-500 text-uppercase">
                                        <th>Admission ID</th>
                                        <th>Course Title</th>
                                        <th>Tuition Fee</th>
                                        <th>Access Status</th>
                                        <th>Payment Method</th>
                                        <th>Date</th>
                                        <th class="text-end">Manage</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-6">
                                    @forelse($purchases as $purchase)
                                    <tr>
                                        <td>#{{ $purchase->id }}</td>
                                        <td class="fw-bold">{{ $purchase->course?->name ?? 'Course #' . $purchase->course_id }}</td>
                                        <td>£{{ number_format($purchase->amount / 100, 2) }}</td>
                                        <td>
                                            @php
                                                $badge = match($purchase->status) {
                                                    'paid', 'admitted' => 'badge-light-success text-success',
                                                    'pending'          => 'badge-light-warning text-warning',
                                                    'revoked', 'failed'=> 'badge-light-danger text-danger',
                                                    default            => 'badge-light-secondary text-secondary',
                                                };
                                                $label = match($purchase->status) {
                                                    'paid', 'admitted' => 'Admitted (Active)',
                                                    'pending'          => 'Pending Approval',
                                                    'revoked'          => 'Access Revoked',
                                                    'failed'           => 'Declined',
                                                    default            => ucfirst($purchase->status),
                                                };
                                            @endphp
                                            <span class="badge {{ $badge }} text-uppercase fw-bold fs-8">{{ $label }}</span>
                                        </td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $purchase->payment_method ?? 'Offline')) }}</td>
                                        <td>{{ $purchase->created_at->format('d M Y, H:i') }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.admissions.show', $purchase->id) }}" class="btn btn-sm btn-light-primary">
                                                Manage Admission →
                                            </a>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">No course admissions recorded for this student.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: LESSON PROGRESS -->
            <div class="tab-pane fade" id="kt_tab_progress" role="tabpanel">
                <div class="card card-flush shadow-sm">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gy-4">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-500 text-uppercase">
                                        <th>Course Slug</th>
                                        <th>Lesson Slug</th>
                                        <th>Completed At</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-6">
                                    @forelse($lessonProgress as $progress)
                                    <tr>
                                        <td><span class="badge badge-light-primary">{{ $progress->course_slug }}</span></td>
                                        <td class="fw-semibold">{{ $progress->lesson_slug }}</td>
                                        <td class="text-success fw-bold"><i class="fas fa-check-circle text-success me-1"></i> {{ $progress->completed_at ? $progress->completed_at->format('d M Y, H:i') : '-' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-5">No lessons completed yet.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: QUIZ ATTEMPTS -->
            <div class="tab-pane fade" id="kt_tab_quizzes" role="tabpanel">
                <div class="card card-flush shadow-sm">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gy-4">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-500 text-uppercase">
                                        <th>Quiz / Paper</th>
                                        <th>Course</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Outcome</th>
                                        <th>Time Taken</th>
                                        <th>Submitted</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-6">
                                    @forelse($quizAttempts as $attempt)
                                    <tr>
                                        <td class="fw-bold">{{ $attempt->quiz_slug }}</td>
                                        <td><span class="badge badge-light-secondary">{{ $attempt->course_slug }}</span></td>
                                        <td>{{ $attempt->score }} / {{ $attempt->total }}</td>
                                        <td>{{ $attempt->percentage }}%</td>
                                        <td>
                                            @if($attempt->passed)
                                                <span class="badge badge-light-success fs-8">PASSED</span>
                                            @else
                                                <span class="badge badge-light-danger fs-8">FAILED</span>
                                            @endif
                                        </td>
                                        <td>{{ $attempt->time_taken_seconds ? gmdate("i:s", $attempt->time_taken_seconds) : '-' }}</td>
                                        <td>{{ $attempt->created_at->format('d M Y, H:i') }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-5">No mock tests or quiz attempts recorded.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: SECONDARY EMAILS -->
            <div class="tab-pane fade" id="kt_tab_emails" role="tabpanel">
                <div class="card card-flush shadow-sm">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gy-4">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-500 text-uppercase">
                                        <th>Email Address</th>
                                        <th>Primary Status</th>
                                        <th>Verified Status</th>
                                        <th>Verified Date</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-6">
                                    <tr class="bg-light">
                                        <td class="fw-bold">{{ $student->email }}</td>
                                        <td><span class="badge badge-primary">Primary Account Email</span></td>
                                        <td>
                                            @if($student->email_verified_at)
                                                <span class="badge badge-light-success">Verified</span>
                                            @else
                                                <span class="badge badge-light-warning">Pending</span>
                                            @endif
                                        </td>
                                        <td>{{ $student->email_verified_at ? $student->email_verified_at->format('d M Y') : '-' }}</td>
                                    </tr>
                                    @foreach($student->emails as $secEmail)
                                    <tr>
                                        <td>{{ $secEmail->email }}</td>
                                        <td>
                                            @if($secEmail->is_primary)
                                                <span class="badge badge-light-primary">Primary</span>
                                            @else
                                                <span class="text-muted">Secondary</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($secEmail->is_verified)
                                                <span class="badge badge-light-success">Verified</span>
                                            @else
                                                <span class="badge badge-light-warning">Unverified</span>
                                            @endif
                                        </td>
                                        <td>{{ $secEmail->verified_at ? $secEmail->verified_at->format('d M Y') : '-' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: LOGIN AUDIT -->
            <div class="tab-pane fade" id="kt_tab_logins" role="tabpanel">
                <div class="card card-flush shadow-sm">
                    <div class="card-body p-6">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gy-3">
                                <thead>
                                    <tr class="fw-bold fs-7 text-gray-500 text-uppercase">
                                        <th>Timestamp</th>
                                        <th>IP Address</th>
                                        <th>Device</th>
                                        <th>Browser</th>
                                        <th>Operating System</th>
                                        <th>Auth Status</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-7">
                                    @forelse($loginHistories as $history)
                                    <tr>
                                        <td>{{ $history->login_at ? $history->login_at->format('d M Y, H:i:s') : $history->created_at->format('d M Y, H:i:s') }}</td>
                                        <td class="font-monospace">{{ $history->ip_address }}</td>
                                        <td>{{ $history->device_type ?: 'Desktop' }}</td>
                                        <td>{{ $history->browser ?: '-' }}</td>
                                        <td>{{ $history->operating_system ?: '-' }}</td>
                                        <td>
                                            @if($history->status === 'success')
                                                <span class="badge badge-light-success">Success</span>
                                            @else
                                                <span class="badge badge-light-danger">Failed</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">No login history recorded.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Content-->

<!-- Modal: Manual Admission -->
<div class="modal fade" id="kt_modal_enroll_student" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold">🎓 Manually Admit Student to Course</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                    <i class="bi bi-x fs-2"></i>
                </div>
            </div>
            <form action="{{ route('admin.students.enroll', $student->id) }}" method="POST">
                @csrf
                <div class="modal-body py-6 px-lg-8">
                    <p class="text-muted fs-7 mb-4">
                        Direct manual admission approves the student into the course with manual/offline payment verification, immediately unlocking active course access.
                    </p>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Select Course</label>
                        <select name="course_id" class="form-select form-select-solid" required>
                            <option value="">-- Choose Course --</option>
                            @foreach($availableCourses ?? [] as $c)
                                <option value="{{ $c->id }}" {{ $student->hasPurchased($c->id) ? 'disabled' : '' }}>
                                    {{ $c->name }} (£{{ number_format($c->price / 100, 2) }})
                                    {{ $student->hasPurchased($c->id) ? '(Already Admitted)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold required">Payment / Admission Method</label>
                        <select name="payment_method" class="form-select form-select-solid" required>
                            <option value="bank_transfer">Bank Transfer / BACS</option>
                            <option value="cash">Cash in Office</option>
                            <option value="card_offline">Card Terminal (In-Person / Phone)</option>
                            <option value="scholarship">Scholarship / Bursary</option>
                            <option value="complimentary">Complimentary / Administrator Review</option>
                            <option value="other">Other External Sponsor</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Internal Notes / Payment Reference</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="2" placeholder="e.g. Paid in reception receipt #1049, verified bank ref..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Confirm Admission &amp; Grant Access</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
