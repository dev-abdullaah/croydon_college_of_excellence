<!-- resources/views/website/pages/learn/play.blade.php -->
@extends('website.layouts.learn')

@section('heading', $quiz->title)
@section('subheading', $quiz->description)

@section('learn')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--20">
        <a href="{{ route('learn.index', $course) }}" class="rbt-btn btn-white">
            &larr; Back to the course
        </a>

        @if ($history->isNotEmpty())
            <a href="{{ route('learn.quizzes.result', [$course, $quiz->slug, $history->first()->id]) }}"
                class="rbt-btn btn-white">Your last result</a>
        @endif
    </div>

    @if ($questions->isEmpty())
        <div class="lz-card p-4">
            <p class="mb-0">This paper has no questions loaded yet. Please contact us on 07405 073764.</p>
        </div>
    @else
        <div class="row g-4">
            {{-- The paper --}}
            <div class="col-lg-8">
                {{--
                    One form for the whole paper, holding every question.

                    The learner picks their way through in the browser, which
                    keeps their answers, so no question costs a request. The
                    form posts once, when they finish.

                    With scripting off nothing hides: every question is shown
                    stacked up and the form still posts them all together, so
                    the paper is still sit-able.

                    The id is `paper-form` and must stay that way. The theme's
                    own main.js cancels the submit of anything called
                    `quiz-form`, which it uses for the demo quiz on another
                    page, so that id here would silently stop the paper being
                    marked.
                --}}
                <form id="paper-form" method="POST" class="lz-paper"
                    action="{{ route('learn.quizzes.submit', [$course, $quiz->slug]) }}"
                    data-attempt="{{ $attempt->id }}" data-start="{{ $position }}"
                    data-total="{{ $total }}">
                    @csrf

                    @foreach ($questions as $question)
                        <section class="lz-card lz-question p-4 {{ $question['position'] === $position ? 'is-current' : '' }}"
                            id="q-{{ $question['position'] }}" data-position="{{ $question['position'] }}"
                            aria-label="Question {{ $question['position'] }} of {{ $total }}">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <span class="badge bg-primary">Question {{ $question['position'] }} of {{ $total }}</span>
                            </div>

                            <h4 class="title mb--30">{!! nl2br(e($question['prompt'])) !!}</h4>

                            {{-- The four options are labels wrapping a radio, so
                                 the whole row is tappable. `correct` appears
                                 nowhere on this page: it is only read on the
                                 server, when the paper is marked. --}}
                            @foreach ($question['options'] as $letter => $text)
                                <label class="lz-opt">
                                    <input type="radio" name="answers[{{ $question['position'] }}]"
                                        value="{{ $letter }}">
                                    <span class="lz-key">{{ strtoupper($letter) }}</span>
                                    <span>{!! nl2br(e($text)) !!}</span>
                                </label>
                            @endforeach
                        </section>
                    @endforeach

                    {{-- Back, next and finish. Only useful when the browser is
                         steering the paper, so they stay out of the way until
                         then. --}}
                    <div class="lz-card lz-nav p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="rbt-btn btn-white" data-move="back">Back</button>

                            <button type="button" class="rbt-btn btn-gradient" data-move="next">
                                Next &rarr;
                            </button>
                        </div>
                    </div>
                </form>

                <noscript>
                    <div class="lz-card p-4 mt--20">
                        <p class="small mb-0">
                            Answer every question above, then use <strong>Finish and see my
                            results</strong> on the right to have the paper marked. Your answers
                            are sent once, at the end.
                        </p>
                    </div>
                </noscript>
            </div>

            {{-- The navigator --}}
            <div class="col-lg-4">
                <div class="lz-card p-4 mb--30 lz-aside">
                    <h5 class="title">How you are doing</h5>
                    <p class="small mb--10" data-progress-text>Nothing answered yet.</p>

                    <div class="progress mb--20" role="progressbar" aria-label="Questions answered"
                        aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="height: .55rem;">
                        <div class="progress-bar" style="width: 0%" data-progress-bar></div>
                    </div>

                    <h6 class="title mb--10">Jump to a question</h6>
                    <div class="lz-jump mb--20">
                        @foreach ($questions as $target)
                            <button type="button" data-goto="{{ $target['position'] }}"
                                class="{{ $target['position'] === $position ? 'here' : '' }}"
                                @if ($target['position'] === $position) aria-current="true" @endif
                                aria-label="Question {{ $target['position'] }}, not answered">{{ $target['position'] }}</button>
                        @endforeach
                    </div>

                    <p class="small text-muted mb-0">
                        Green means answered. Your answers are held as you go, so you can close
                        this page and come back to it on this device later.
                    </p>
                </div>

                <div class="lz-card p-4">
                    <h5 class="title">{{ $quiz->time_limit_minutes }} minutes &middot; pass at
                        {{ $quiz->passMarkCount() }}/{{ $total }}</h5>
                    <ul class="rbt-list-style-1 list-unstyled mb-0 small">
                        <li>Choose one answer for each question.</li>
                        <li>Anything you leave blank counts as wrong.</li>
                        <li>You are marked as soon as you finish, and cannot change your answers afterwards.</li>
                    </ul>

                    <button type="submit" form="paper-form" class="rbt-btn btn-white w-100 mt--20">
                        Finish and see my results
                    </button>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/quiz.js') }}" defer></script>
@endpush
