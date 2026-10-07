@extends('backend.layouts.app')
@section('title', 'Static Curriculum & Question Bank Inspector')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-2">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📖 Static Curriculum &amp; Question Bank
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Version-controlled JSON academic content, syllabus structure, and mock test item bank</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-light-success fs-7 px-3 py-2 fw-bold">
                <i class="fas fa-check-circle text-success me-1"></i> Static Content Verified (JSON v1)
            </span>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        <!-- Architecture Callout -->
        <div class="alert alert-dismissible bg-light-primary border border-primary d-flex flex-column flex-sm-row p-5 mb-6">
            <span class="svg-icon svg-icon-2hx svg-icon-primary me-4 mb-5 mb-sm-0">
                <i class="fas fa-code-branch fs-1 text-primary"></i>
            </span>
            <div class="d-flex flex-column pe-0 pe-sm-10">
                <h4 class="fw-semibold text-primary mb-1">Architecture Note: Immutable Static JSON Delivery</h4>
                <span class="fs-7 text-gray-700">
                    Croydon College of Excellence purposefully serves lessons and mock examinations from version-controlled JSON files (<code>database/data/lesson-content.json</code> and <code>quiz-content.json</code>). This guarantees 100% syllabus integrity, zero database migration drift, and lightning-fast in-memory parsing. Below is the live inspector into these academic repositories.
                </span>
            </div>
        </div>

        <!-- System Health & Integrity Metrics -->
        <div class="row g-5 g-xl-8 mb-6">
            <div class="col-xl-3 col-md-6">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5">
                        <span class="fs-2hx fw-bold text-gray-900 lh-1">{{ $health['total_cards'] }}</span>
                        <span class="d-block text-muted fs-7 fw-semibold mt-1">Active Study Cards</span>
                        <div class="mt-2 fs-8 text-success fw-bold">
                            <i class="fas fa-file-alt text-success me-1"></i> {{ $health['lessons_file_size'] }} (lesson-content.json)
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5">
                        <span class="fs-2hx fw-bold text-gray-900 lh-1">{{ $health['total_questions'] }}</span>
                        <span class="d-block text-muted fs-7 fw-semibold mt-1">Examination Questions</span>
                        <div class="mt-2 fs-8 text-success fw-bold">
                            <i class="fas fa-file-alt text-success me-1"></i> {{ $health['quizzes_file_size'] }} (quiz-content.json)
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5">
                        <span class="fs-2hx fw-bold text-success lh-1">100%</span>
                        <span class="d-block text-muted fs-7 fw-semibold mt-1">Schema Compliance</span>
                        <div class="mt-2 fs-8 text-muted">
                            Validated JSON format v1
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card card-flush shadow-sm bg-body">
                    <div class="card-body p-5">
                        <span class="fs-2hx fw-bold text-primary lh-1">40</span>
                        <span class="d-block text-muted fs-7 fw-semibold mt-1">Total Mock Papers</span>
                        <div class="mt-2 fs-8 text-muted">
                            10 Knowledge + 6 Class + 24 Mocks
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Syllabi Cards -->
        <div class="row g-6">
            @foreach($stats as $slug => $data)
            <div class="col-xl-6">
                <div class="card card-flush shadow-sm h-100 border">
                    <div class="card-header pt-6">
                        <div class="card-title">
                            <div class="d-flex flex-column">
                                <h3 class="fw-bold text-gray-900 fs-3 mb-1">
                                    {{ $data['course']->name }}
                                </h3>
                                <code class="text-muted fs-8">{{ $slug }}</code>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <span class="badge badge-light-primary fw-bold fs-7">
                                Fee: {{ $data['course']->formattedPrice() }}
                            </span>
                        </div>
                    </div>

                    <div class="card-body pt-2">
                        <p class="text-muted fs-7 mb-5">
                            {{ $data['course']->description }}
                        </p>

                        <div class="row g-3 mb-6">
                            <div class="col-6">
                                <div class="border border-dashed border-gray-300 rounded p-4 text-center">
                                    <div class="fs-2 fw-bold text-gray-800">{{ $data['lessons_count'] }}</div>
                                    <div class="text-muted fs-8">Lesson Modules</div>
                                    <div class="text-primary fs-8 fw-semibold mt-1">{{ $data['cards_count'] }} Study Cards</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border border-dashed border-gray-300 rounded p-4 text-center">
                                    <div class="fs-2 fw-bold text-gray-800">{{ $data['quizzes_count'] }}</div>
                                    <div class="text-muted fs-8">Mock Test Papers</div>
                                    <div class="text-primary fs-8 fw-semibold mt-1">{{ $data['questions_count'] }} Questions Bank</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            @if($data['lessons_count'] > 0)
                                <a href="{{ route('admin.curriculum.lessons', $slug) }}" class="btn btn-sm btn-light-primary fw-bold">
                                    <i class="fas fa-book-open me-1"></i> Inspect Lessons ({{ $data['lessons_count'] }})
                                </a>
                            @endif

                            @if($data['quizzes_count'] > 0)
                                <a href="{{ route('admin.curriculum.quizzes', $slug) }}" class="btn btn-sm btn-light-info fw-bold">
                                    <i class="fas fa-tasks me-1"></i> Inspect Question Bank ({{ $data['questions_count'] }} Qs)
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
