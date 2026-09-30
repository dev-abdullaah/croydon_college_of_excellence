<!-- resources/views/website/pages/learn/play.blade.php -->
@extends('website.layouts.learn')

@section('heading', $quiz->title)
@section('subheading', $quiz->description)

@section('learn')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--20">
        <a href="{{ route('learn.index', $course) }}" class="btn btn-sm btn-outline-secondary">
            &larr; Back to the course
        </a>

        @if ($history->isNotEmpty())
            <a href="{{ route('learn.quizzes.result', [$course, $quiz->slug, $history->first()->id]) }}"
                class="btn btn-sm btn-outline-secondary">Your last result</a>
        @endif
    </div>

    <div class="row g-4">
        {{-- The question --}}
        <div class="col-lg-8">
            @if ($question)
                <div class="lz-card p-4 mb--30">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <span class="badge bg-primary">Question {{ $position }} of {{ $total }}</span>
                        <span class="small text-muted">
                            {{ $quiz->time_limit_minutes }} minutes &middot; pass at
                            {{ $quiz->passMarkCount() }}/{{ $total }}
                        </span>
                    </div>

                    {{--
                        One form per question. The four options are labels
                        wrapping a radio so the whole row is tappable.

                        The answer is posted and stored on the server. The
                        correct answer appears nowhere on this page - it is
                        only read when the attempt is marked.
                    --}}
                    <form id="answer-form" method="POST"
                        action="{{ route('learn.quizzes.answer', [$course, $quiz->slug]) }}">
                        @csrf
                        <input type="hidden" name="position" value="{{ $position }}">

                        <h4 class="title mb--30">{!! nl2br(e($question->prompt)) !!}</h4>

                        @php $picked = $attempt->answerFor($question->position); @endphp

                        @foreach ($question->options as $letter => $text)
                            <label class="lz-opt {{ $picked === $letter ? 'is-picked' : '' }}">
                                <input type="radio" name="answer" value="{{ $letter }}"
                                    {{ $picked === $letter ? 'checked' : '' }}>
                                <span class="lz-key">{{ strtoupper($letter) }}</span>
                                <span>{!! nl2br(e($text)) !!}</span>
                            </label>
                        @endforeach
                    </form>

                    <div class="d-flex justify-content-between align-items-center mt--20">
                        <div>
                            @if ($position > 1)
                                {{-- Back is navigation, not an answer, so it
                                     posts to the jump route on its own form. --}}
                                <form method="POST" action="{{ route('learn.quizzes.jump', [$course, $quiz->slug]) }}">
                                    @csrf
                                    <input type="hidden" name="position" value="{{ $position - 1 }}">
                                    <button type="submit" class="btn btn-outline-secondary">Back</button>
                                </form>
                            @endif
                        </div>

                        <div>
                            @if ($position < $total)
                                <button type="submit" form="answer-form" class="btn btn-primary">
                                    Save &amp; next &rarr;
                                </button>
                            @else
                                {{-- The last question finishes rather than moving
                                     on. Same form, so the answer chosen here is
                                     still saved before the paper is marked. --}}
                                <button type="submit" form="answer-form"
                                    formaction="{{ route('learn.quizzes.submit', [$course, $quiz->slug]) }}"
                                    class="btn btn-primary">
                                    Finish &amp; see my results
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="lz-card p-4">
                    <p class="mb-0">This paper has no questions loaded yet. Please contact us on 07405 073764.</p>
                </div>
            @endif
        </div>

        {{-- The navigator --}}
        <div class="col-lg-4">
            <div class="lz-card p-4 mb--30">
                <h5 class="title">How you are doing</h5>
                <p class="small mb--10">{{ $attempt->progressLabel($total) }}</p>

                @php $pct = $total > 0 ? (int) round($answered / $total * 100) : 0; @endphp
                <div class="progress mb--20" role="progressbar" aria-label="Questions answered"
                    aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" style="height: .55rem;">
                    <div class="progress-bar" style="width: {{ $pct }}%"></div>
                </div>

                <h6 class="title mb--10">Jump to a question</h6>
                <div class="lz-jump mb--20">
                    @foreach ($questions as $target)
                        @php
                            $here = $target->position === $position;
                            $done = $attempt->answerFor($target->position) !== null;
                        @endphp
                        <form method="POST" action="{{ route('learn.quizzes.jump', [$course, $quiz->slug]) }}"
                            class="d-inline">
                            @csrf
                            <input type="hidden" name="position" value="{{ $target->position }}">
                            <button type="submit"
                                class="{{ $here ? 'here' : ($done ? 'done' : '') }}"
                                @if ($here) aria-current="true" @endif
                                aria-label="Question {{ $target->position }}{{ $done ? ', answered' : ', not answered' }}">{{ $target->position }}</button>
                        </form>
                    @endforeach
                </div>

                <p class="small text-muted mb-0">
                    Green means answered. Your answers are saved as you go, so you can close this page and
                    come back to it later.
                </p>
            </div>

            <div class="lz-card p-4">
                <h5 class="title">Before you finish</h5>
                <ul class="rbt-list-style-1 list-unstyled mb-0 small">
                    <li>Choose one answer for each question.</li>
                    <li>Anything you leave blank counts as wrong.</li>
                    <li>You are marked as soon as you finish, and cannot change your answers afterwards.</li>
                </ul>

                <form method="POST" action="{{ route('learn.quizzes.submit', [$course, $quiz->slug]) }}" class="mt--20">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                        @if ($answered === 0) disabled @endif>
                        Finish early
                    </button>
                </form>
            </div>
        </div>
    </div>

@endsection
