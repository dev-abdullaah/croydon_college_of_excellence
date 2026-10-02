<!-- resources/views/website/pages/learn/result.blade.php -->
@extends('website.layouts.learn')

@section('heading', 'Your result')
@section('subheading', $quiz->title)

@section('learn')

    {{-- ── Pass / fail hero ────────────────────────────────────────────────── --}}
    <div class="lz-result-hero {{ $attempt->passed ? 'is-pass' : 'is-fail' }} mb--30">
        {{-- Score circle --}}
        <div class="lz-result-circle">
            <div class="lz-result-pct">{{ (int) round((float) $attempt->percentage) }}<span style="font-size:1.4rem;">%</span></div>
            <div class="lz-result-label">{{ $attempt->passed ? 'Pass' : 'Fail' }}</div>
        </div>

        {{-- Narrative --}}
        <div class="flex-grow-1">
            <h3 class="title mb-2">
                @if ($attempt->passed)
                    🎉 Well done — that&rsquo;s a pass!
                @else
                    Keep going — you&rsquo;re getting there
                @endif
            </h3>
            <p class="mb-1" style="font-size:1.5rem;">
                You scored <strong>{{ $attempt->score }}/{{ $attempt->total }}</strong>
                ({{ (int) round((float) $attempt->percentage) }}%).
                The pass mark is {{ $quiz->pass_mark_percent }}%
                ({{ $quiz->passMarkCount() }} out of {{ $attempt->total }}).
            </p>
            @if (!$attempt->passed)
                <p class="mb-1" style="font-size:1.45rem; color: var(--lz-fail);">
                    You need
                    {{ max(0, $quiz->passMarkCount() - (int) $attempt->score) }}
                    more correct
                    {{ max(0, $quiz->passMarkCount() - (int) $attempt->score) === 1 ? 'answer' : 'answers' }}
                    to pass. Review the lesson material and sit it again.
                </p>
            @else
                <p class="mb-1" style="font-size:1.45rem; color: var(--lz-pass);">
                    Sit it again any time to try for a higher score.
                </p>
            @endif
            <p class="mb-0 small text-muted mt-2">
                Finished {{ $attempt->submitted_at?->format('j M Y, H:i') }}
                @if ($attempt->time_taken_seconds)
                    &middot; took {{ gmdate($attempt->time_taken_seconds >= 3600 ? 'H:i:s' : 'i:s', $attempt->time_taken_seconds) }}
                @endif
                @if ($blank > 0)
                    &middot; {{ $blank }} question{{ $blank === 1 ? '' : 's' }} left blank
                @endif
            </p>
        </div>
    </div>

    {{-- ── Action buttons ──────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--30">
        <a href="{{ route('learn.index', $course) }}" class="btn btn-outline-secondary">
            &larr; Back to the course
        </a>

        <div class="d-flex flex-wrap gap-2">
            @if ($lesson)
                <a href="{{ route('learn.lessons.show', [$course, $lesson->slug]) }}"
                    class="btn btn-outline-secondary">
                    <i class="feather-book-open me-1"></i> Back to the lesson
                </a>
            @endif
            <a href="{{ route('learn.quizzes.play', [$course, $quiz->slug]) }}" class="btn btn-primary">
                <i class="feather-refresh-cw me-1"></i> Sit this paper again
            </a>
        </div>
    </div>

    {{-- ── Question-by-question breakdown ─────────────────────────────────── --}}
    <div class="lz-card p-4 mb--30">
        <h4 class="title mb--30">
            <i class="feather-list me-2 text-primary"></i>Question by question
        </h4>

        @foreach ($marked as $row)
            <div class="pb-4 mb-4 border-bottom">
                <div class="d-flex gap-3">
                    {{-- align-self-start pins the badge to the question line --}}
                    <span class="lz-num align-self-start" style="flex-shrink:0;">
                        {{ $row['question']->position }}
                    </span>
                    <div class="flex-grow-1">
                        <p class="fw-semibold mb-3" style="font-size:1.55rem; line-height:1.5;">
                            {!! nl2br(e($row['question']->prompt)) !!}
                        </p>

                        @foreach ($row['question']->options as $letter => $text)
                            @php
                                $isPicked = $row['given'] === $letter;
                                $isRight  = $row['correct'] === $letter;
                                $class    = $isRight ? 'is-right' : ($isPicked ? 'is-wrong' : 'is-muted');
                            @endphp
                            <div class="lz-opt {{ $class }}">
                                <span class="lz-key">{{ strtoupper($letter) }}</span>
                                <span class="flex-grow-1">{!! nl2br(e($text)) !!}</span>
                                @if ($isRight)
                                    <span class="lz-mark lz-mark-yes small">
                                        <i class="feather-check"></i> Correct
                                    </span>
                                @elseif ($isPicked)
                                    <span class="lz-mark lz-mark-no small">
                                        <i class="feather-x"></i> Your answer
                                    </span>
                                @endif
                            </div>
                        @endforeach

                        @if ($row['is_blank'])
                            <p class="small mb-0 mt-1">
                                <span class="lz-mark lz-mark-no">
                                    <i class="feather-x"></i> You left this blank
                                </span>
                            </p>
                        @endif

                        @if ($row['explanation'])
                            <div class="lz-explain mt-2">
                                <strong>Why:</strong> {!! nl2br(e($row['explanation'])) !!}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Previous sittings ───────────────────────────────────────────────── --}}
    @if ($history->count() > 1)
        <div class="lz-card p-4 mb--30">
            <h5 class="title mb--20">
                <i class="feather-clock me-1 text-primary"></i> Your previous sittings
            </h5>
            @foreach ($history as $past)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <span class="fw-semibold">{{ $past->score }}/{{ $past->total }}</span>
                        <span class="small text-muted ms-2">
                            &middot; {{ $past->submitted_at?->format('j M Y, H:i') }}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge {{ $past->passed ? 'bg-success' : 'bg-warning text-dark' }}">
                            {{ $past->passed ? 'Pass' : 'Not passed' }}
                        </span>
                        @if ($past->id !== $attempt->id)
                            <a href="{{ route('learn.quizzes.result', [$course, $quiz->slug, $past->id]) }}"
                                class="btn btn-sm btn-outline-secondary">View</a>
                        @else
                            <span class="small text-muted">This one</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection

@push('scripts')
    {{-- Clear localStorage for this sitting now it is finished. --}}
    <script>
        try {
            window.localStorage.removeItem('cce.quiz.attempt.{{ $attempt->id }}');
        } catch (error) {
            /* Nothing to do. */
        }
    </script>
@endpush
