<!-- resources/views/website/pages/learn/lesson.blade.php -->
@extends('website.layouts.learn')

@section('heading', 'Lesson ' . $lesson->number)
@section('subheading', $lesson->title)

@section('learn')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--20">
        <div>
            <a href="{{ route('learn.index', $course) }}" class="btn btn-sm btn-outline-secondary">
                &larr; All lessons
            </a>
        </div>

        {{-- Which lesson of the course this is, as a position. --}}
        <div class="small text-muted">
            Lesson {{ $lesson->number }} of {{ $lessons->count() }}
        </div>
    </div>

    {{-- Where the learner is in the 100 cards. --}}
    <div class="lz-card p-3 mb--20">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="small">
                Showing cards
                <strong>{{ $items->firstItem() }}&ndash;{{ $items->lastItem() }}</strong>
                of <strong>{{ $lesson->itemCount() }}</strong>
            </div>

            <div class="small text-muted">
                Page {{ $items->currentPage() }} of {{ $items->lastPage() }}
            </div>
        </div>
    </div>

    {{-- The study cards --}}
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

        {{ $items->links() }}
    </div>

    {{-- On to the knowledge check for this lesson. --}}
    @if ($check)
        <div class="lz-card p-4 mb--30">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <h5 class="title mb-1">Finished this lesson?</h5>
                    <p class="mb-0">
                        Put it to the test with {{ $check->title }} &mdash; {{ $check->questionCount() }}
                        questions on the same material.
                        @if ($checkScore !== null)
                            <br>
                            Your best score so far is
                            <strong>{{ (int) round($checkScore) }}%</strong>.
                        @endif
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="{{ route('learn.quizzes.play', [$course, $check->slug]) }}"
                        class="btn {{ $checkScore !== null ? 'btn-outline-secondary' : 'btn-primary' }} w-100">
                        {{ $checkScore !== null ? 'Sit it again' : 'Start ' . strtolower($check->title) }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- Mark read --}}
    <div class="lz-card p-4 mb--30">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h5 class="title mb-1">{{ $completedAt ? 'Lesson complete' : 'Finished reading?' }}</h5>
                <p class="mb-0">
                    @if ($completedAt)
                        Marked complete on
                        {{ $completedAt?->format('j M Y') }}.
                        You can read it again as often as you like.
                    @else
                        Mark this lesson as read so you can see your progress across the course.
                    @endif
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                @if ($completedAt)
                    <span class="badge bg-success px-3 py-2">Done</span>
                @else
                    <form method="POST" action="{{ route('learn.lessons.complete', [$course, $lesson->slug]) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary w-100">Mark as read</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Previous / next --}}
    <div class="row g-3">
        <div class="col-md-6">
            @if ($neighbours['previous'])
                <a href="{{ route('learn.lessons.show', [$course, $neighbours['previous']->slug]) }}"
                    class="lz-card d-block p-3 text-decoration-none text-reset h-100">
                    <small class="text-muted">Previous</small>
                    <div class="fw-semibold">Lesson {{ $neighbours['previous']->number }}:
                        {{ $neighbours['previous']->title }}</div>
                </a>
            @endif
        </div>
        <div class="col-md-6">
            @if ($neighbours['next'])
                <a href="{{ route('learn.lessons.show', [$course, $neighbours['next']->slug]) }}"
                    class="lz-card d-block p-3 text-decoration-none text-reset h-100 text-md-end">
                    <small class="text-muted">Next</small>
                    <div class="fw-semibold">Lesson {{ $neighbours['next']->number }}:
                        {{ $neighbours['next']->title }}</div>
                </a>
            @else
                <a href="{{ route('learn.index', $course) }}"
                    class="lz-card d-block p-3 text-decoration-none text-reset h-100 text-md-end">
                    <small class="text-muted">Next</small>
                    <div class="fw-semibold">Back to all lessons &amp; papers</div>
                </a>
            @endif
        </div>
    </div>

@endsection
