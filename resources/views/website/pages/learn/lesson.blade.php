<!-- resources/views/website/pages/learn/lesson.blade.php -->
@extends('website.layouts.learn')

@section('heading', 'Lesson ' . $lesson->number)
@section('subheading', $lesson->title)

@section('learn')

    {{-- ── Top nav ─────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--20">
        <a href="{{ route('learn.index', $course) }}" class="btn btn-outline-secondary">
            &larr; All lessons
        </a>
        <span class="small text-muted">
            Lesson {{ $lesson->number }} of {{ $lessons->count() }}
        </span>
    </div>

    {{-- ── Pager / position card ──────────────────────────────────────────── --}}
    <div class="lz-card lz-pager-card p-3 mb--20">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="lz-q-badge">
                    <i class="feather-layers" style="font-size:1.3rem;"></i>
                    Cards {{ $items->firstItem() }}&ndash;{{ $items->lastItem() }}
                    of {{ $lesson->itemCount() }}
                </span>
            </div>
            <span class="small text-muted">Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
        </div>
    </div>

    {{-- ── Study cards ────────────────────────────────────────────────────── --}}
    <div class="lz-card p-4 mb--30">
        @foreach ($items as $item)
            <div class="lz-item">
                <span class="lz-num">{{ $item->position }}</span>
                <div class="flex-grow-1">
                    <p class="lz-q mb-0">{!! nl2br(e($item->question)) !!}</p>
                    <p class="lz-a mb-0 mt-2">{!! nl2br(e($item->answer)) !!}</p>
                </div>
            </div>
        @endforeach

        <div class="mt-3">
            {{ $items->links() }}
        </div>
    </div>

    {{-- ── Knowledge check ────────────────────────────────────────────────── --}}
    @if ($check)
        <div class="lz-card p-4 mb--30">
            <div class="d-flex align-items-start gap-3">
                <div class="lz-num" style="border-radius:10px; background: rgba(47,87,239,.1); color: var(--lz-accent); flex-shrink:0;">
                    <i class="feather-check-square" style="font-size:1.6rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                        <div>
                            <h5 class="title mb-1">Finished this lesson?</h5>
                            <p class="mb-0 text-muted" style="font-size:1.45rem;">
                                {{ $check->title }} &mdash; {{ $check->questionCount() }} questions
                                on the same material.
                                @if ($checkScore !== null)
                                    Your best score so far is
                                    <strong class="text-heading">{{ (int) round($checkScore) }}%</strong>.
                                @endif
                            </p>
                        </div>
                        <a href="{{ route('learn.quizzes.play', [$course, $check->slug]) }}"
                            class="btn btn-primary btn-lg flex-shrink-0">
                            {{ $checkScore !== null ? 'Sit again' : 'Start ' . strtolower($check->title) }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Mark as read ────────────────────────────────────────────────────── --}}
    <div class="lz-card p-4 mb--30">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-start gap-3">
                <div class="lz-num" style="border-radius:10px; background: {{ $completedAt ? 'var(--lz-pass-soft)' : 'var(--lz-surface-2)' }}; color: {{ $completedAt ? 'var(--lz-pass)' : 'var(--lz-muted)' }}; flex-shrink:0;">
                    <i class="feather-book-open" style="font-size:1.6rem;"></i>
                </div>
                <div>
                    <h5 class="title mb-1">
                        {{ $completedAt ? 'Lesson complete' : 'Finished reading?' }}
                    </h5>
                    <p class="mb-0 text-muted" style="font-size:1.45rem;">
                        @if ($completedAt)
                            Marked complete on {{ $completedAt?->format('j M Y') }}.
                            You can read it again as often as you like.
                        @else
                            Mark this lesson as read to track your progress across the course.
                        @endif
                    </p>
                </div>
            </div>
            <div>
                @if ($completedAt)
                    <span class="badge bg-success px-3 py-2" style="font-size:1.3rem;">
                        <i class="feather-check me-1"></i> Done
                    </span>
                @else
                    <form method="POST" action="{{ route('learn.lessons.complete', [$course, $lesson->slug]) }}">
                        @csrf
                        <button type="submit" class="btn btn-lg btn-success">
                            Mark as read
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Previous / next ─────────────────────────────────────────────────── --}}
    <div class="row g-3">
        <div class="col-6">
            @if ($neighbours['previous'])
                <a href="{{ route('learn.lessons.show', [$course, $neighbours['previous']->slug]) }}"
                    class="lz-card d-flex align-items-center gap-2 p-3 text-decoration-none text-reset h-100">
                    <i class="feather-arrow-left text-muted" style="font-size:1.8rem; flex-shrink:0;"></i>
                    <div>
                        <div class="small text-muted">Previous</div>
                        <div class="fw-semibold" style="font-size:1.4rem;">
                            Lesson {{ $neighbours['previous']->number }}:
                            {{ $neighbours['previous']->title }}
                        </div>
                    </div>
                </a>
            @endif
        </div>
        <div class="col-6">
            @if ($neighbours['next'])
                <a href="{{ route('learn.lessons.show', [$course, $neighbours['next']->slug]) }}"
                    class="lz-card d-flex align-items-center justify-content-end gap-2 p-3 text-decoration-none text-reset h-100 text-end">
                    <div>
                        <div class="small text-muted">Next</div>
                        <div class="fw-semibold" style="font-size:1.4rem;">
                            Lesson {{ $neighbours['next']->number }}:
                            {{ $neighbours['next']->title }}
                        </div>
                    </div>
                    <i class="feather-arrow-right text-muted" style="font-size:1.8rem; flex-shrink:0;"></i>
                </a>
            @else
                <a href="{{ route('learn.index', $course) }}"
                    class="lz-card d-flex align-items-center justify-content-end gap-2 p-3 text-decoration-none text-reset h-100 text-end">
                    <div>
                        <div class="small text-muted">Next</div>
                        <div class="fw-semibold" style="font-size:1.4rem;">Back to all lessons &amp; papers</div>
                    </div>
                    <i class="feather-arrow-right text-muted" style="font-size:1.8rem; flex-shrink:0;"></i>
                </a>
            @endif
        </div>
    </div>

@endsection
