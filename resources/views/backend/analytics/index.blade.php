@extends('backend.layouts.app')
@section('title', 'Analytics & Academic Performance')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📊 Platform Analytics & Academic Metrics
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Course completion rates, mock test performance, and revenue intelligence</span>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3 flex-wrap">
            @include('backend.layouts.partials.date_range_picker')
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        <!-- Financial KPI Cards -->
        <div class="row g-5 g-xl-8 mb-6">
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-primary shadow-sm h-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">£{{ number_format($totalRevenue, 2) }}</span>
                            <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Gross Paid Revenue</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <span class="text-white fs-7">{{ $totalPaidPurchases }} confirmed admissions</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-success shadow-sm h-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">£{{ number_format($avgOrderValue, 2) }}</span>
                            <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Average Admission Fee</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <span class="text-white fs-7">Across all courses</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-info shadow-sm h-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">{{ $overallPassRate }}%</span>
                            <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Global Mock Test Pass Rate</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <span class="text-white fs-7">Pass criteria: score ≥ 50%</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-flush bg-dark shadow-sm h-100">
                    <div class="card-header pt-5">
                        <div class="card-title d-flex flex-column">
                            <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">{{ $totalQuizAttempts }}</span>
                            <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Total Test Sittings</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <span class="text-white fs-7">Average Score: {{ $avgScorePercentage }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Performance Matrix -->
        <div class="card card-flush shadow-sm mb-6">
            <div class="card-header pt-5">
                <h3 class="card-title fw-bold text-gray-800">Course Engagement & Completion Matrix</h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-striped table-hover fs-7 gy-4">
                        <thead>
                            <tr class="text-start text-gray-700 fw-bold fs-7 text-uppercase gs-0 bg-light">
                                <th class="min-w-200px ps-3">Course</th>
                                <th class="min-w-100px text-center">Admitted Learners</th>
                                <th class="min-w-120px text-center">Revenue (£)</th>
                                <th class="min-w-120px text-center">Lessons</th>
                                <th class="min-w-150px text-center">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @foreach($courseStats as $stat)
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-gray-900 fs-6">{{ $stat['course']->name }}</span>
                                    <span class="text-muted fs-8 d-block"><code>{{ $stat['course']->slug }}</code></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light-primary text-primary fw-bold fs-7">{{ $stat['paid_count'] }}</span>
                                </td>
                                <td class="text-center fw-bold text-gray-900 fs-6">
                                    £{{ number_format($stat['revenue'], 2) }}
                                </td>
                                <td class="text-center">
                                    {{ $stat['total_lessons'] }} lessons
                                </td>
                                <td class="text-center">
                                    @if($stat['total_lessons'] > 0)
                                        <div class="d-flex align-items-center justify-content-center">
                                            <span class="fw-bold me-2">{{ $stat['completion_rate'] }}%</span>
                                            <div class="progress w-100px h-6px bg-light-success">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $stat['completion_rate'] }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted fs-8">Practice pack (No lessons)</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quiz Performance Breakdown -->
        <div class="card card-flush shadow-sm">
            <div class="card-header pt-5">
                <h3 class="card-title fw-bold text-gray-800">Mock Tests & Quiz Performance Breakdown</h3>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-striped table-hover fs-7 gy-3">
                        <thead>
                            <tr class="text-start text-gray-700 fw-bold fs-7 text-uppercase gs-0 bg-light">
                                <th class="min-w-200px ps-3">Quiz / Mock Paper</th>
                                <th class="min-w-160px">Course</th>
                                <th class="min-w-100px text-center">Total Attempts</th>
                                <th class="min-w-120px text-center">Average Score</th>
                                <th class="min-w-120px text-center">Pass Rate</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @forelse($quizBreakdown as $quiz)
                            <tr>
                                <td class="ps-3 fw-bold text-gray-900">{{ $quiz->quiz_slug }}</td>
                                <td><span class="badge badge-light-secondary">{{ $quiz->course_slug }}</span></td>
                                <td class="text-center">{{ $quiz->total_attempts }}</td>
                                <td class="text-center fw-bold">{{ $quiz->avg_percentage }}%</td>
                                <td class="text-center">
                                    @if($quiz->pass_rate >= 75)
                                        <span class="badge badge-light-success fw-bold fs-8">{{ $quiz->pass_rate }}%</span>
                                    @elseif($quiz->pass_rate >= 50)
                                        <span class="badge badge-light-warning fw-bold fs-8">{{ $quiz->pass_rate }}%</span>
                                    @else
                                        <span class="badge badge-light-danger fw-bold fs-8">{{ $quiz->pass_rate }}%</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">No mock test attempt data available yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
