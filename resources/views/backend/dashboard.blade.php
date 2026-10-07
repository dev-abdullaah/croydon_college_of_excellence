@extends('backend.layouts.app')
@section('title', 'Croydon College Administration Dashboard')

@section('page')
<!--begin::Main-->
<div class="app-main flex-column flex-row-fluid" id="kt_app_main">
    <div class="d-flex flex-column flex-column-fluid">
        <!--begin::Toolbar-->
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
            <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">⚡ Croydon College Administration Dashboard</h1>
                    <span class="text-muted fs-7 fw-semibold mt-1">Real-time revenue, purchase conversion stats & student enrollment performance</span>
                </div>
                <div class="d-flex align-items-center gap-2 gap-lg-3 flex-wrap">
                    @include('backend.layouts.partials.date_range_picker')
                    <a href="{{ route('admin.analytics.index') }}" class="btn btn-sm fw-bold btn-light-primary">
                        <i class="bi bi-graph-up me-1"></i> Analytics
                    </a>
                    <a href="{{ route('admin.admissions.index') }}" class="btn btn-sm fw-bold btn-info">Admissions</a>
                    <a href="{{ route('admin.courses.index') }}" class="btn btn-sm fw-bold btn-primary">Courses</a>
                </div>
            </div>
        </div>
        <!--end::Toolbar-->

        <!--begin::Content-->
        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div id="kt_app_content_container" class="app-container container-xxl">
                
                <div class="card card-flush">
                    <div class="card-body p-6">
                
                <!-- Metrics Row -->
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Total Revenue Card -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-100" style="background-color: #009ef7; background-image:url('{{ asset('admin-assets/media/svg/shapes/wave-bg-purple.svg') }}')">
                            <div class="card-header pt-5">
                                <div class="card-title d-flex flex-column">
                                    <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">£ {{ number_format($totalRevenue, 2) }}</span>
                                    <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Total Revenue</span>
                                </div>
                            </div>
                            <div class="card-body d-flex align-items-end pt-0">
                                <div class="d-flex align-items-center me-2 text-white">
                                    <span class="fs-7">Today: £ {{ number_format($todayRevenue, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Admissions Card -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-100" style="background-color: #50cd89; background-image:url('{{ asset('admin-assets/media/svg/shapes/wave-bg-purple.svg') }}')">
                            <div class="card-header pt-5">
                                <div class="card-title d-flex flex-column">
                                    <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">{{ $admittedLearners ?? $totalPaidPurchases }}</span>
                                    <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Admitted Learners (Active)</span>
                                </div>
                            </div>
                            <div class="card-body d-flex align-items-end pt-0">
                                <div class="d-flex align-items-center me-2 text-white">
                                    <span class="fs-7">Total Applications: {{ $totalAdmissions ?? $totalPurchases }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Admissions Card -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-100" style="background-color: #ffc700;">
                            <div class="card-header pt-5">
                                <div class="card-title d-flex flex-column">
                                    <span class="fs-2hx fw-bold text-dark me-2 lh-1 ls-n2">{{ $pendingAdmissions ?? $pendingPurchases }}</span>
                                    <span class="text-dark opacity-75 pt-1 fw-semibold fs-6">Pending Admission Requests</span>
                                </div>
                            </div>
                            <div class="card-body d-flex align-items-end pt-0">
                                <a href="{{ route('admin.admissions.index', ['status' => 'pending']) }}" class="text-dark fw-bold fs-7 hover-underline">Review Pending →</a>
                            </div>
                        </div>
                    </div>

                    <!-- Average Admission Fee (AAF) Card -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-100" style="background-color: #7239ea;">
                            <div class="card-header pt-5">
                                <div class="card-title d-flex flex-column">
                                    <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">£ {{ number_format($avgAdmissionFee ?? $avgPurchaseValue, 2) }}</span>
                                    <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Avg Admission Fee (AAF)</span>
                                </div>
                            </div>
                            <div class="card-body d-flex align-items-end pt-0">
                                <span class="text-white fs-7">Active Courses: {{ $activeCourses }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Pills Row -->
                <div class="row g-5 mb-5">
                    <div class="col-12">
                        <div class="card card-flush py-4 px-6 d-flex flex-row flex-wrap justify-content-between align-items-center gap-3">
                            <span class="fw-bold fs-5 text-gray-800">Admissions Quick Breakdown:</span>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('admin.admissions.index', ['status' => 'all']) }}" class="btn btn-sm btn-light-secondary fw-bold">All Applications ({{ $totalAdmissions ?? $totalPurchases }})</a>
                                <a href="{{ route('admin.admissions.index', ['status' => 'pending']) }}" class="btn btn-sm btn-light-warning fw-bold">⏳ Pending ({{ $pendingAdmissions ?? $pendingPurchases }})</a>
                                <a href="{{ route('admin.admissions.index', ['status' => 'admitted']) }}" class="btn btn-sm btn-light-success fw-bold">🎓 Admitted ({{ $admittedLearners ?? $totalPaidPurchases }})</a>
                                <a href="{{ route('admin.admissions.index', ['status' => 'revoked']) }}" class="btn btn-sm btn-light-danger fw-bold">🛑 Revoked ({{ $revokedAdmissions ?? $refundedPurchases }})</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tables Row -->
                <div class="row g-5 g-xl-8 mb-5">
                    <!-- Top Selling Products / Courses -->
                    <div class="col-xl-5">
                        <div class="card card-flush h-100">
                            <div class="card-header pt-7">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold text-gray-800 fs-4">🔥 Top Selling Courses</span>
                                    <span class="text-gray-400 mt-1 fw-semibold fs-7">Ranked by enrollment volume</span>
                                </h3>
                            </div>
                            <div class="card-body pt-2 scroll-y me-n2 pe-2" style="height: 380px; max-height: 380px; overflow-y: auto;">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-3">
                                        <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
                                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                                <th>Course</th>
                                                <th class="text-end">Enrollments</th>
                                                <th class="text-end">Sales Value</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-600">
                                            @forelse($topCourses as $top)
                                            <tr>
                                                <td class="text-gray-800 fw-bold text-hover-primary fs-7">
                                                    {{ Str::limit($top->name, 35) }}
                                                </td>
                                                <td class="text-end fw-bold text-dark fs-7">
                                                    <span class="badge badge-light-success fs-7">{{ $top->total_qty }} enrolled</span>
                                                </td>
                                                <td class="text-end fw-bold text-gray-800 fs-7">
                                                    £ {{ number_format($top->total_sales, 2) }}
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No course enrollments recorded yet.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Admissions Activity -->
                    <div class="col-xl-7">
                        <div class="card card-flush h-100">
                            <div class="card-header pt-7">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold text-gray-800 fs-4">⚡ Recent Admissions &amp; Applications</span>
                                    <span class="text-gray-400 mt-1 fw-semibold fs-7">Live admission requests &amp; approved learner feed</span>
                                </h3>
                                <div class="card-toolbar">
                                    <a href="{{ route('admin.admissions.index') }}" class="btn btn-sm btn-light">View All Admissions</a>
                                </div>
                            </div>
                            <div class="card-body pt-2 scroll-y me-n2 pe-2" style="height: 380px; max-height: 380px; overflow-y: auto;">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-3">
                                        <thead style="position: sticky; top: 0; background: #fff; z-index: 2;">
                                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                                <th>Ref #</th>
                                                <th>Learner</th>
                                                <th>Course</th>
                                                <th>Fee</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-600">
                                            @forelse($recentAdmissions ?? $recentPurchases as $rOrder)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('admin.admissions.show', $rOrder) }}" class="text-gray-800 text-hover-primary fw-bold fs-7">
                                                        #{{ $rOrder->id }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="text-gray-800 fw-bold fs-7">{{ $rOrder->student->name ?? $rOrder->customer_name ?? 'Learner' }}</span>
                                                        <span class="text-muted fs-8">{{ $rOrder->contact_phone ?? $rOrder->student?->phone ?? $rOrder->customer_email }}</span>
                                                    </div>
                                                </td>
                                                <td class="fs-7 text-gray-700">
                                                    {{ Str::limit($rOrder->course->name ?? 'Course', 25) }}
                                                </td>
                                                <td class="fw-bold text-dark fs-7">
                                                    £ {{ number_format($rOrder->amount / 100, 2) }}
                                                </td>
                                                <td>
                                                    <span class="badge {{ $rOrder->statusBadgeClass() }} fw-bold fs-8">
                                                        {{ $rOrder->statusLabel() }}
                                                    </span>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No admission applications recorded yet.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Inquiries Section -->
                <div class="row g-5 g-xl-8">
                    <div class="col-12">
                        <div class="card card-flush">
                            <div class="card-header pt-7">
                                <h3 class="card-title align-items-start flex-column">
                                    <span class="card-label fw-bold text-gray-800 fs-4">📬 Recent Contact Inquiries & Leads</span>
                                    <span class="text-gray-400 mt-1 fw-semibold fs-7">Latest messages received from prospective students</span>
                                </h3>
                                <div class="card-toolbar">
                                    <a href="{{ route('admin.submissions.index') }}" class="btn btn-sm btn-light-primary">View All Inquiries</a>
                                </div>
                            </div>
                            <div class="card-body pt-2">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-3">
                                        <thead>
                                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Subject</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th class="text-end">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fw-semibold text-gray-600">
                                            @forelse($recentInquiries as $inquiry)
                                            <tr>
                                                <td class="text-gray-800 fw-bold">{{ $inquiry->name }}</td>
                                                <td>{{ $inquiry->email }}</td>
                                                <td>
                                                    <a href="{{ route('admin.submissions.show', $inquiry) }}" class="text-hover-primary text-gray-800">
                                                        {{ Str::limit($inquiry->subject ?: $inquiry->message, 40) }}
                                                    </a>
                                                </td>
                                                <td><span class="badge badge-light-info fs-8">{{ ucfirst($inquiry->type) }}</span></td>
                                                <td>
                                                    <span class="badge {{ $inquiry->status === 'unread' || $inquiry->status === 'new' ? 'badge-light-danger' : 'badge-light-success' }} fs-8">
                                                        {{ ucfirst($inquiry->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-end text-muted fs-7">{{ $inquiry->created_at->diffForHumans() }}</td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No inquiries received yet.</td>
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

            </div>
        </div>
        <!--end::Content-->
    </div>
</div>
<!--end:::Main-->
@endsection
