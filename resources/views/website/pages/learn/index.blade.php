<!-- resources/views/website/pages/learn/index.blade.php -->
@extends('website.layouts.learn')

@section('heading', 'Your course')
@section('subheading', $lessons->isNotEmpty()
    ? 'Work through the lessons, then sit the papers.'
    : 'Sit the papers under real test conditions.')

@section('learn')


    {{-- Where the learner is, in one glance. --}}
    <div class="lz-card p-4 mb--30">
        <div class="row g-4 align-items-center">
            <div class="col-md-6">
                <h4 class="title mb-1">Your progress</h4>
                <p class="mb-0">
                    @if ($lessons->isNotEmpty())
                        {{ $progress['lessons_done'] === $progress['lessons_total']
                            ? "All {$progress['lessons_total']} lessons read."
                            : "{$progress['lessons_done']} of {$progress['lessons_total']} lessons read." }}
                        {{ $progress['quizzes_sat'] }} of {{ $progress['quizzes_total'] }} papers sat.
                    @elseif ($progress['quizzes_total'] > 0)
                        {{ $progress['quizzes_total'] }}
                        {{ Str::plural('full-length paper', $progress['quizzes_total']) }}.
                        {{ $progress['quizzes_sat'] === 0
                            ? 'None sat yet.'
                            : "You have sat {$progress['quizzes_sat']}." }}
                    @else
                        Nothing to show yet.
                    @endif
                </p>
            </div>
            <div class="col-md-6">
                @if ($progress['lessons_total'] > 0)
                    @php $pct = (int) round($progress['lessons_done'] / $progress['lessons_total'] * 100); @endphp
                    <div class="progress" role="progressbar" aria-label="Lessons read" aria-valuenow="{{ $pct }}"
                        aria-valuemin="0" aria-valuemax="100" style="height: .55rem;">
                        <div class="progress-bar" style="width: {{ $pct }}%"></div>
                    </div>
                @endif
            </div>
        </div>

        <hr class="my-4">

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('dashboard') }}" class="rbt-btn btn-white">My account</a>
            <a href="{{ route('courses.show', $course) }}" class="rbt-btn btn-white">Course details</a>
        </div>
    </div>

    {{-- Lessons --}}
    @if ($lessons->isNotEmpty())
        <h3 class="title mb--20">Lessons</h3>

        <div class="row g-3 mb--40">
            @foreach ($lessons as $lesson)
                <div class="col-lg-6 col-12">
                    <a href="{{ route('learn.lessons.show', [$course, $lesson->slug]) }}"
                        class="lz-card d-block p-3 h-100 text-decoration-none text-reset">
                        <div class="d-flex align-items-start">
                            <span class="lz-num">{{ $lesson->number }}</span>
                            <div class="ms-3 flex-grow-1">
                                <h5 class="title mb-1">Lesson {{ $lesson->number }}</h5>
                                <p class="mb-2 small">{{ $lesson->title }}</p>
                                <span class="badge {{ $read->contains($lesson->slug) ? 'bg-success' : 'bg-light text-dark' }}">
                                    {{ $read->contains($lesson->slug) ? 'Read' : $lesson->itemCount() . ' study cards' }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Papers --}}
    @forelse ($groups as $kind => $group)
        <h3 class="title mb--10">{{ $group['label'] }}</h3>
        <p class="mb--20">
            @if ($kind === \App\Content\Quiz::KIND_KNOWLEDGE_CHECK)
                Ten questions each, straight after the lesson they belong to.
            @else
                {{ $group['quizzes']->first()->questionCount() }} questions each,
                {{ $group['quizzes']->first()->time_limit_minutes }} minutes allowed,
                {{ $group['quizzes']->first()->passMarkCount() }} out of
                {{ $group['quizzes']->first()->questionCount() }} to pass.
            @endif
        </p>

        <div class="row g-3 mb--40">
            @foreach ($group['quizzes'] as $quiz)
                @php $row = $best->get($quiz->slug); @endphp
                <div class="col-lg-6 col-12">
                    <a href="{{ route('learn.quizzes.play', [$course, $quiz->slug]) }}"
                        class="lz-card d-block p-3 h-100 text-decoration-none text-reset">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h5 class="title mb-1">{{ $quiz->title }}</h5>
                                <p class="mb-0 small">
                                    {{ $quiz->questionCount() }} questions
                                    &middot; {{ $quiz->time_limit_minutes }} min
                                    &middot; pass at {{ $quiz->passMarkCount() }}/{{ $quiz->questionCount() }}
                                </p>
                            </div>
                            <div class="text-end ms-3">
                                @if ($row)
                                    <span class="badge {{ (float) $row->best_percentage >= $quiz->pass_mark_percent ? 'bg-success' : 'bg-warning text-dark' }}">
                                        Best {{ (int) round((float) $row->best_percentage) }}%
                                    </span>
                                    <div class="small text-muted mt-1">Sat {{ $row->times_sat }}&times;</div>
                                @else
                                    <span class="badge bg-light text-dark">Not sat</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @empty
        @if ($lessons->isEmpty())
            <div class="lz-card p-4 text-center">
                <h4 class="title">Course material is being prepared</h4>
                <p class="mb-3">
                    The lessons and papers for this course are not loaded yet.
                </p>
                <a href="{{ route('dashboard') }}" class="rbt-btn btn-white">Back to my account</a>
            </div>
        @endif
    @endforelse

@endsection
