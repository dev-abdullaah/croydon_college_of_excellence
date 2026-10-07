@extends('backend.layouts.app')
@section('title', 'Lesson Curriculum — ' . $course->name)

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack flex-wrap gap-2">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                📚 Lessons &amp; Study Cards Inspector: {{ $course->name }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Review lesson texts, card questions, and learning prompts from <code>database/data/lesson-content.json</code></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.curriculum.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left fa-xs me-1"></i> Back to Overview
            </a>
            <a href="{{ route('admin.curriculum.quizzes', $course->slug) }}" class="btn btn-sm btn-light-primary">
                <i class="fas fa-tasks fa-xs me-1"></i> View Question Bank
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        <div class="accordion" id="kt_accordion_lessons">
            @forelse($lessons as $index => $lesson)
                @php
                    $itemCount = count($lesson->items ?? []);
                @endphp
                <div class="card card-flush shadow-sm mb-4">
                    <div class="card-header cursor-pointer py-4" id="heading_{{ $lesson->slug }}" data-bs-toggle="collapse" data-bs-target="#collapse_{{ $lesson->slug }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                        <div class="card-title d-flex align-items-center gap-3">
                            <span class="badge badge-primary fs-7 px-3 py-2">Lesson {{ $lesson->number }}</span>
                            <div>
                                <h3 class="fs-5 fw-bold text-gray-900 mb-0">{{ $lesson->title }}</h3>
                                <span class="text-muted fs-8"><code>{{ $lesson->slug }}</code> &middot; {{ $itemCount }} study cards</span>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <i class="fas fa-chevron-down fs-7 text-muted"></i>
                        </div>
                    </div>

                    <div id="collapse_{{ $lesson->slug }}" class="collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#kt_accordion_lessons">
                        <div class="card-body pt-0">
                            @if($lesson->summary)
                                <div class="p-4 bg-light rounded text-gray-700 fs-7 mb-4 border">
                                    <strong>Summary:</strong> {{ $lesson->summary }}
                                </div>
                            @endif

                            <div class="table-responsive">
                                <table class="table table-row-dashed table-hover align-middle gy-3 fs-7">
                                    <thead>
                                        <tr class="fw-bold text-gray-500 text-uppercase bg-light">
                                            <th class="w-50px ps-3">#</th>
                                            <th class="w-50">Study Question Prompt</th>
                                            <th class="w-50">Official Syllabus Answer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($lesson->items ?? [] as $i => $item)
                                        <tr>
                                            <td class="ps-3 fw-bold text-muted">{{ $i + 1 }}</td>
                                            <td class="fw-semibold text-gray-900">
                                                {{ $item->question ?? 'N/A' }}
                                            </td>
                                            <td class="text-gray-700">
                                                <span class="text-dark">{{ $item->answer ?? 'N/A' }}</span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card card-flush shadow-sm">
                    <div class="card-body text-center py-10">
                        <i class="fas fa-info-circle fs-2 text-muted mb-3"></i>
                        <h4 class="text-gray-800">No lessons defined for this course</h4>
                        <p class="text-muted fs-7">This course delivers mock tests rather than lesson study cards.</p>
                    </div>
                </div>
            @endforelse
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
