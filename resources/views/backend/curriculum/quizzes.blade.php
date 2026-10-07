@extends('backend.layouts.app')
@section('title', 'Question Bank & Mock Tests — ' . $course->name)

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-2">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📝 Mock Tests &amp; Question Bank: {{ $course->name }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Review test papers, examine question prompts &amp; answer keys, and evaluate student success rates</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.curriculum.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left fa-xs me-1"></i> Back to Overview
            </a>
            @if($course->slug === 'life-in-the-uk-course')
                <a href="{{ route('admin.curriculum.lessons', $course->slug) }}" class="btn btn-sm btn-light-primary">
                    <i class="fas fa-book-open fa-xs me-1"></i> View Lessons
                </a>
            @endif
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        <div class="row g-5 g-xl-8">
            <!-- Left Sidebar: Paper Selector -->
            <div class="col-xl-4">
                <div class="card card-flush shadow-sm">
                    <div class="card-header pt-5">
                        <h3 class="card-title fw-bold text-gray-800 fs-5">
                            📄 Test Papers ({{ $quizzes->count() }})
                        </h3>
                    </div>
                    <div class="card-body p-3">
                        <div class="overflow-auto max-h-600px">
                            @foreach($quizzesByKind as $kind => $papers)
                                <div class="fs-8 fw-bold text-uppercase text-muted px-3 py-2 bg-light rounded mb-2">
                                    {{ ucwords(str_replace('_', ' ', $kind)) }} ({{ $papers->count() }})
                                </div>
                                <div class="list-group list-group-flush mb-3">
                                    @foreach($papers as $paper)
                                        @php
                                            $isSelected = $selectedQuiz && $selectedQuiz->slug === $paper->slug;
                                            $stats = $attemptStats->get($paper->slug);
                                            $attempts = $stats?->attempts_count ?? 0;
                                            $passRate = $attempts > 0 ? (int) round(($stats->passes_count / $attempts) * 100) : null;
                                        @endphp
                                        <a href="{{ route('admin.curriculum.quizzes', [$course->slug, 'quiz' => $paper->slug]) }}"
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 px-3 rounded {{ $isSelected ? 'active bg-light-primary border-primary' : '' }}">
                                            <div>
                                                <div class="fw-bold fs-7 {{ $isSelected ? 'text-primary' : 'text-gray-800' }}">
                                                    {{ $paper->title }}
                                                </div>
                                                <span class="fs-8 text-muted">
                                                    {{ count($paper->questions) }} Questions &middot; Pass {{ $paper->pass_mark_percent }}%
                                                </span>
                                            </div>
                                            <div class="text-end">
                                                @if($attempts > 0)
                                                    <span class="badge {{ $passRate >= 75 ? 'badge-light-success text-success' : 'badge-light-warning text-warning' }} fs-9">
                                                        {{ $passRate }}% pass
                                                    </span>
                                                    <span class="d-block fs-9 text-muted">{{ $attempts }} sittings</span>
                                                @else
                                                    <span class="badge badge-light-secondary fs-9">No data</span>
                                                @endif
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Question Bank Inspector -->
            <div class="col-xl-8">
                @if($selectedQuiz)
                    @php
                        $selectedStats = $attemptStats->get($selectedQuiz->slug);
                        $totalSittings = $selectedStats?->attempts_count ?? 0;
                        $passRate = $totalSittings > 0 ? (int) round(($selectedStats->passes_count / $totalSittings) * 100) : null;
                        $avgScore = $totalSittings > 0 ? (int) round($selectedStats->avg_percentage) : null;
                    @endphp

                    <!-- Paper Header Card -->
                    <div class="card card-flush shadow-sm mb-5">
                        <div class="card-body p-5">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <span class="badge badge-light-primary text-uppercase fw-bold fs-8 mb-2">
                                        {{ ucwords(str_replace('_', ' ', $selectedQuiz->kind)) }}
                                    </span>
                                    <h2 class="fs-3 fw-bold text-gray-900 mb-1">{{ $selectedQuiz->title }}</h2>
                                    <p class="text-muted fs-7 mb-0">{{ $selectedQuiz->description }}</p>
                                </div>
                                <div class="d-flex gap-3">
                                    <div class="border rounded p-3 text-center bg-light min-w-100px">
                                        <div class="fs-4 fw-bold text-gray-900">{{ count($selectedQuiz->questions) }}</div>
                                        <div class="fs-8 text-muted">Questions</div>
                                    </div>
                                    <div class="border rounded p-3 text-center bg-light min-w-100px">
                                        <div class="fs-4 fw-bold text-primary">{{ $selectedQuiz->pass_mark_percent }}%</div>
                                        <div class="fs-8 text-muted">Pass Mark</div>
                                    </div>
                                    <div class="border rounded p-3 text-center bg-light min-w-100px">
                                        <div class="fs-4 fw-bold {{ $passRate !== null && $passRate >= 75 ? 'text-success' : 'text-dark' }}">
                                            {{ $passRate !== null ? $passRate . '%' : 'N/A' }}
                                        </div>
                                        <div class="fs-8 text-muted">Learner Pass Rate</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Questions List -->
                    <div class="d-flex flex-column gap-4">
                        @foreach($selectedQuiz->questions as $qIndex => $question)
                            <div class="card card-flush shadow-sm border">
                                <div class="card-header pt-4 pb-2">
                                    <h4 class="card-title fs-6 fw-bold text-gray-900">
                                        <span class="badge badge-light-dark me-2">Q{{ $qIndex + 1 }}</span>
                                        {{ $question->prompt }}
                                    </h4>
                                </div>
                                <div class="card-body pt-0 pb-4">
                                    <div class="row g-2 mb-3">
                                        @foreach($question->options as $letter => $optionText)
                                            @php
                                                $isCorrect = strtolower($question->correct) === strtolower($letter);
                                            @endphp
                                            <div class="col-md-6">
                                                <div class="p-3 rounded border {{ $isCorrect ? 'bg-light-success border-success text-dark' : 'bg-light border-gray-200 text-gray-700' }} fs-7 d-flex align-items-start gap-2">
                                                    <span class="badge {{ $isCorrect ? 'badge-success' : 'badge-light' }} fw-bold text-uppercase px-2">
                                                        {{ $letter }}
                                                    </span>
                                                    <span class="{{ $isCorrect ? 'fw-bold' : '' }}">
                                                        {{ $optionText }}
                                                    </span>
                                                    @if($isCorrect)
                                                        <i class="fas fa-check-circle text-success ms-auto mt-1" title="Correct Key"></i>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if(!empty($question->explanation))
                                        <div class="fs-8 text-muted bg-light p-2 rounded border">
                                            <strong class="text-dark">Syllabus Guidance / Explanation:</strong> {{ $question->explanation }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                @else
                    <div class="card card-flush shadow-sm">
                        <div class="card-body text-center py-10">
                            <p class="text-muted">Select a test paper on the left to examine its questions and answer key.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
