<!-- resources/views/website/pages/learn/result.blade.php -->
@extends('website.layouts.learn')

@section('heading', 'Your result')
@section('subheading', $quiz->title)

@section('learn')

    {{-- A knowledge check belongs to the lesson of the same number; a mock
         paper has no lesson to go back to. --}}
    
    {{-- The score --}}
    <div class="lz-card p-4 mb--30">
        <div class="row g-4 align-items-center">
            <div class="col-md-4 text-center">
                <div class="lz-score {{ $attempt->passed ? 'text-success' : 'text-danger' }}">
                    {{ $attempt->score }}<span class="fs-4 text-muted">/{{ $attempt->total }}</span>
                </div>
                <div class="mt-2">
                    <span class="badge {{ $attempt->passed ? 'bg-success' : 'bg-danger' }} px-3 py-2">
                        {{ $attempt->passed ? 'Pass' : 'Not passed yet' }}
                    </span>
                </div>
            </div>

            <div class="col-md-8">
                <p class="mb-2">
                    You scored <strong>{{ (int) round((float) $attempt->percentage) }}%</strong>.
                    The pass mark for this paper is {{ $quiz->pass_mark_percent }}%
                    ({{ $quiz->passMarkCount() }} out of {{ $attempt->total }} correct).
                </p>
                <p class="mb-2">
                    @if ($attempt->passed)
                        Well done &mdash; that is a pass. Sit it again whenever you like to try for a higher score.
                    @else
                        You need {{ max(0, $quiz->passMarkCount() - (int) $attempt->score) }} more correct
                        answer{{ max(0, $quiz->passMarkCount() - (int) $attempt->score) === 1 ? '' : 's' }}
                        to pass. Go back over the lesson material and sit it again.
                    @endif
                </p>
                <p class="small text-muted mb-0">
                    Finished
                    {{ $attempt->submitted_at?->format('j M Y, H:i') }}
                    @if ($attempt->time_taken_seconds)
                        &middot; took {{ gmdate($attempt->time_taken_seconds >= 3600 ? 'H:i:s' : 'i:s', $attempt->time_taken_seconds) }}
                    @endif
                    @if ($blank > 0)
                        &middot; {{ $blank }} question{{ $blank === 1 ? '' : 's' }} left blank
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--20">
        <a href="{{ route('learn.index', $course) }}" class="btn btn-sm btn-outline-secondary">
            &larr; Back to the course
        </a>

        <div class="d-flex gap-2">
            @if ($lesson)
                <a href="{{ route('learn.lessons.show', [$course, $lesson->slug]) }}"
                    class="btn btn-sm btn-outline-secondary">Back to the lesson</a>
            @endif
            <a href="{{ route('learn.quizzes.play', [$course, $quiz->slug]) }}" class="btn btn-sm btn-primary">
                Sit this paper again
            </a>
        </div>
    </div>

    {{-- Every question, what was chosen, and what was right --}}
    <div class="lz-card p-4 mb--30">
        <h4 class="title mb--30">Question by question</h4>

        @foreach ($marked as $row)
            <div class="pb-4 mb-4 border-bottom">
                <div class="d-flex gap-3">
                    <span class="lz-num">{{ $row['question']->position }}</span>
                    <div class="flex-grow-1">
                        <p class="fw-semibold mb-3">{!! nl2br(e($row['question']->prompt)) !!}</p>

                        @foreach ($row['question']->options as $letter => $text)
                            @php
                                $isPicked = $row['given'] === $letter;
                                $isRight = $row['correct'] === $letter;
                                $class = $isRight ? 'is-right' : ($isPicked ? 'is-wrong' : 'is-muted');
                            @endphp
                            <div class="lz-opt {{ $class }}">
                                <span class="lz-key">{{ strtoupper($letter) }}</span>
                                <span class="flex-grow-1">{!! nl2br(e($text)) !!}</span>
                                @if ($isRight)
                                    <span class="lz-mark lz-mark-yes small">
                                        <i class="feather-check"></i> Correct answer
                                    </span>
                                @elseif ($isPicked)
                                    <span class="lz-mark lz-mark-no small">
                                        <i class="feather-x"></i> You chose this
                                    </span>
                                @endif
                            </div>
                        @endforeach

                        @if ($row['is_blank'])
                            <p class="small mb-0">
                                <span class="lz-mark lz-mark-no">
                                    <i class="feather-x"></i> You left this blank
                                </span>
                            </p>
                        @endif

                        @if ($row['explanation'])
                            <div class="lz-explain">
                                <strong>Why:</strong> {!! nl2br(e($row['explanation'])) !!}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Previous sittings --}}
    @if ($history->count() > 1)
        <div class="lz-card p-4 mb--30">
            <h5 class="title">Your previous sittings</h5>
            <ul class="list-unstyled mb-0">
                @foreach ($history as $past)
                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>
                            {{ $past->score }}/{{ $past->total }}
                            <small class="text-muted">
                                &middot; {{ $past->submitted_at?->format('j M Y, H:i') }}
                            </small>
                        </span>
                        <span>
                            <span class="badge {{ $past->passed ? 'bg-success' : 'bg-warning text-dark' }} me-2">
                                {{ $past->passed ? 'Pass' : 'Not passed' }}
                            </span>
                            @if ($past->id !== $attempt->id)
                                <a href="{{ route('learn.quizzes.result', [$course, $quiz->slug, $past->id]) }}"
                                    class="btn btn-sm btn-outline-secondary">View</a>
                            @else
                                <span class="small text-muted">This one</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

@endsection
