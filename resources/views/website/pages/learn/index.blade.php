<!-- resources/views/website/pages/learn/index.blade.php -->
@extends('website.layouts.learn')

@section('heading', 'Your course')
@section('subheading', $lessons->isNotEmpty()
    ? 'Work through the lessons, then sit the papers.'
    : 'Sit the papers under real test conditions.')

@section('learn')

    {{-- ── Progress overview ──────────────────────────────────────────────── --}}
    <div class="lz-card p-4 mb--30">
        <div class="row g-4 align-items-center">
            <div class="col-md-7">
                <h4 class="title mb-1">Your progress</h4>
                @if ($lessons->isNotEmpty())
                    @php $lessonPct = $progress['lessons_total'] > 0
                        ? (int) round($progress['lessons_done'] / $progress['lessons_total'] * 100)
                        : 0; @endphp
                    <p class="mb-3 text-muted">
                        <strong class="text-heading">{{ $progress['lessons_done'] }}</strong>
                        of {{ $progress['lessons_total'] }} lessons read
                        &middot;
                        <strong class="text-heading">{{ $progress['quizzes_sat'] }}</strong>
                        of {{ $progress['quizzes_total'] }} papers sat
                    </p>
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between mb-1 small text-muted">
                                <span>Lessons</span>
                                <span>{{ $lessonPct }}%</span>
                            </div>
                            <div class="progress" style="height: 8px; border-radius: 99px; background: var(--lz-surface-2);">
                                <div class="progress-bar" role="progressbar"
                                    style="width: {{ $lessonPct }}%; border-radius: 99px; background: linear-gradient(90deg, var(--lz-accent) 0%, #7f9bfa 100%);"
                                    aria-valuenow="{{ $lessonPct }}" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($progress['quizzes_total'] > 0)
                    <p class="mb-0 text-muted">
                        {{ $progress['quizzes_total'] }}
                        {{ Str::plural('full-length paper', $progress['quizzes_total']) }}.
                        {{ $progress['quizzes_sat'] === 0
                            ? 'None sat yet.'
                            : "You have sat {$progress['quizzes_sat']}." }}
                    </p>
                @else
                    <p class="mb-0 text-muted">Nothing to show yet.</p>
                @endif
            </div>

            <div class="col-md-5">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                    <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-secondary">
                        <i class="feather-user me-1"></i> My account
                    </a>
                    <a href="{{ route('courses.show', $course) }}" class="btn btn-lg btn-outline-secondary">
                        <i class="feather-info me-1"></i> Course details
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Lessons ────────────────────────────────────────────────────────── --}}
    @if ($lessons->isNotEmpty())
        <div class="d-flex align-items-center gap-3 mb--20">
            <h3 class="title mb-0">Lessons</h3>
            <span class="badge bg-primary-opacity text-primary">
                {{ $progress['lessons_done'] }}/{{ $progress['lessons_total'] }} read
            </span>
        </div>

        <div class="row g-3 mb--40 lz-index-grid">
            @foreach ($lessons as $lesson)
                @php $isRead = $read->contains($lesson->slug); @endphp
                <div class="col-lg-6 col-12">
                    <a href="{{ route('learn.lessons.show', [$course, $lesson->slug]) }}"
                        class="lz-card d-block p-3 h-100 text-decoration-none text-reset {{ $isRead ? 'border-success-subtle' : '' }}">
                        <div class="d-flex align-items-start gap-3">
                            <span class="lz-num {{ $isRead ? 'lz-num--done' : '' }}">
                                @if ($isRead)
                                    <i class="feather-check" style="font-size:1.4rem;"></i>
                                @else
                                    {{ $lesson->number }}
                                @endif
                            </span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <p class="fw-700 mb-1 text-heading">
                                            Lesson {{ $lesson->number }}
                                        </p>
                                        <p class="mb-0 small text-muted">{{ $lesson->title }}</p>
                                    </div>
                                    <span class="badge flex-shrink-0 {{ $isRead ? 'bg-success' : 'bg-light text-dark' }}">
                                        {{ $isRead ? '✓ Read' : $lesson->itemCount() . ' study cards' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Papers ─────────────────────────────────────────────────────────── --}}
    @forelse ($groups as $kind => $group)
        <div class="d-flex align-items-center gap-3 mb-2">
            <h3 class="title mb-0">{{ $group['label'] }}</h3>
        </div>

        <p class="mb--20 text-muted">
            @if ($kind === \App\Content\Quiz::KIND_KNOWLEDGE_CHECK)
                Ten questions each, sat straight after the lesson they belong to.
            @else
                {{ $group['quizzes']->first()->questionCount() }} questions each
                &middot; {{ $group['quizzes']->first()->time_limit_minutes }} minutes allowed
                &middot; pass at {{ $group['quizzes']->first()->passMarkCount() }}/{{ $group['quizzes']->first()->questionCount() }}
            @endif
        </p>

        <div class="row g-3 mb--40 lz-index-grid">
            @foreach ($group['quizzes'] as $quiz)
                @php
                    $row = $best->get($quiz->slug);
                    $passed = $row && (float) $row->best_percentage >= $quiz->pass_mark_percent;
                @endphp
                <div class="col-lg-6 col-12">
                    <a href="{{ route('learn.quizzes.play', [$course, $quiz->slug]) }}"
                        class="lz-card lz-paper-card d-block p-3 h-100 text-decoration-none text-reset">
                        <div class="d-flex align-items-start gap-3">
                            <span class="lz-num" style="border-radius: 10px;">{{ $loop->iteration }}</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <p class="fw-700 mb-1 text-heading">
                                        {{ $quiz->title }}
                                    </p>
                                    @if ($row)
                                        <span class="badge flex-shrink-0 {{ $passed ? 'bg-success' : 'bg-warning text-dark' }}">
                                            Best {{ (int) round((float) $row->best_percentage) }}%
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark flex-shrink-0">Not sat</span>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                                    <span class="small text-muted">
                                        <i class="feather-help-circle" style="font-size:1.2rem;vertical-align:-1px;"></i>
                                        {{ $quiz->questionCount() }} questions
                                    </span>
                                    <span class="small text-muted">
                                        <i class="feather-clock" style="font-size:1.2rem;vertical-align:-1px;"></i>
                                        {{ $quiz->time_limit_minutes }} min
                                    </span>
                                    <span class="small text-muted">
                                        <i class="feather-target" style="font-size:1.2rem;vertical-align:-1px;"></i>
                                        Pass at {{ $quiz->passMarkCount() }}/{{ $quiz->questionCount() }}
                                    </span>
                                    @if ($row)
                                        <span class="small text-muted">Sat {{ $row->times_sat }}&times;</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @empty
        @if ($lessons->isEmpty())
            <div class="lz-card p-5 text-center">
                <div class="mb-3" style="font-size:3.2rem;">📚</div>
                <h4 class="title">Course material is being prepared</h4>
                <p class="mb-4 text-muted">
                    The lessons and papers for this course are not loaded yet.
                </p>
                <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-secondary">
                    &larr; Back to my account
                </a>
            </div>
        @endif
    @endforelse

@endsection

@push('styles')
<style>
.lz-num--done {
    background: var(--lz-pass-soft) !important;
    color: var(--lz-pass) !important;
}
.border-success-subtle {
    border-color: rgba(31, 146, 84, .25) !important;
}
</style>
@endpush
