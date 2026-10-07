<!-- resources/views/website/pages/learn/play.blade.php -->
@extends('website.layouts.learn')

@section('heading', $quiz->title)
@section('subheading', $quiz->description)

@section('learn')

    {{-- ── Top nav ─────────────────────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb--20">
        <a href="{{ route('learn.index', $course) }}" class="btn btn-lg btn-outline-secondary">
            &larr; Back to the course
        </a>

        @if ($history->isNotEmpty())
            <a href="{{ route('learn.quizzes.result', [$course, $quiz->slug, $history->first()->id]) }}"
                class="btn btn-lg btn-outline-secondary">
                <i class="feather-clock me-1"></i> Your last result
            </a>
        @endif
    </div>

    @if ($questions->isEmpty())
        <div class="lz-card p-5 text-center">
            <div class="mb-3" style="font-size:3rem;">📋</div>
            <p class="mb-0">This paper has no questions loaded yet. Please contact us on 07405 073764.</p>
        </div>
    @else
        <div class="row g-4">

            {{-- ── Paper (questions) ─────────────────────────────────────────── --}}
            <div class="col-lg-8">
                {{--
                    One form holds every question. The learner works through
                    in-browser; answers are kept in localStorage and posted once
                    at the end. With scripting off all questions are shown and
                    the form still posts — no question costs a request.

                    id="paper-form" must stay: quiz.js references it by this id.
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

                            <div class="d-flex justify-content-between align-items-center mb--30">
                                <span class="lz-q-badge">
                                    Question {{ $question['position'] }} of {{ $total }}
                                </span>
                            </div>

                            <h4 class="title mb--30" style="line-height: 1.45;">
                                {!! nl2br(e($question['prompt'])) !!}
                            </h4>

                            {{--
                                Options are labels wrapping a radio so the whole
                                row is tappable. `correct` never appears here —
                                it is read only on the server when marking.
                            --}}
                            @foreach ($question['options'] as $letter => $text)
                                <label class="lz-opt">
                                    <input type="radio" name="answers[{{ $question['position'] }}]"
                                        value="{{ $letter }}">
                                    <span class="lz-key">{{ strtoupper($letter) }}</span>
                                    <span class="flex-grow-1">{!! nl2br(e($text)) !!}</span>
                                </label>
                            @endforeach
                        </section>
                    @endforeach

                    {{-- Back / Next — only useful with JS steering the paper --}}
                    <div class="lz-card lz-nav p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-lg btn-outline-secondary" data-move="back">
                                &larr; Back
                            </button>
                            <button type="button" class="btn btn-lg btn-primary" data-move="next">
                                Next &rarr;
                            </button>
                        </div>
                    </div>
                </form>

                <noscript>
                    <div class="lz-card p-4 mt--20">
                        <p class="small mb-0">
                            Answer every question above, then use
                            <strong>Finish and see my results</strong> on the right
                            to have the paper marked. Your answers are sent once, at the end.
                        </p>
                    </div>
                </noscript>
            </div>

            {{-- ── Sidebar ───────────────────────────────────────────────────── --}}
            {{--
                Both cards are in ordinary flow, and that is deliberate.

                The first card used to carry `position: sticky; top: 100px`.
                Sticky creates a stacking context even at z-index:auto, so the
                pinned card painted *above* its in-flow sibling. It kept its slot
                in flow, so the rules card below scrolled up into the pinned
                card's 100-440px band and disappeared underneath it - taking the
                Finish button with it, exactly when the reader wanted it.

                Sticky would then need a viewport cap to stop it pushing its own
                lower half, including Finish, below the fold on a laptop screen:
                this column is ~620px tall at 24 questions and only ~520px is left
                below the header. A capped sticky rail needs its own overflow-y,
                which is a second scroll context nested inside the page - the
                wheel stops moving the page and the rail captures it instead.
                That reads as broken, and it is worse than the rail simply
                scrolling away with the page.

                So: no sticky, no cap, no overflow. One scroll context - the
                page. Nothing to overlap, nothing to be covered, and the jump
                grid stays usable at its natural size.
            --}}
            <div class="col-lg-4">

                {{-- Progress + jump grid --}}
                <div class="lz-card p-4 mb--30 lz-aside">
                    <h5 class="title mb-2">
                        <i class="feather-bar-chart-2 me-1 text-primary"></i> How you&rsquo;re doing
                    </h5>
                    <p class="small mb-2 text-muted" data-progress-text>Nothing answered yet.</p>

                    <div class="progress mb--20" role="progressbar" aria-label="Questions answered"
                        aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                        style="height: 8px; border-radius: 99px; background: var(--lz-surface-2);">
                        <div class="progress-bar" style="width: 0%; border-radius: 99px; background: linear-gradient(90deg, var(--lz-accent) 0%, #7f9bfa 100%);" data-progress-bar></div>
                    </div>

                    <h6 class="title mb--10" style="font-size:1.3rem;">Jump to a question</h6>
                    <div class="lz-jump mb--20">
                        @foreach ($questions as $target)
                            <button type="button" data-goto="{{ $target['position'] }}"
                                class="{{ $target['position'] === $position ? 'here' : '' }}"
                                @if ($target['position'] === $position) aria-current="true" @endif
                                aria-label="Question {{ $target['position'] }}, not answered">{{ $target['position'] }}</button>
                        @endforeach
                    </div>

                    <p class="small text-muted mb-0">
                        <i class="feather-check-circle me-1 text-success" style="font-size:1.2rem;"></i>
                        Green = answered. Close and return later — your answers are saved on this device.
                    </p>
                </div>

                {{-- Rules + finish --}}
                <div class="lz-card p-4">
                    <h5 class="title mb-2">
                        <i class="feather-clock me-1 text-primary"></i>
                        {{ $quiz->time_limit_minutes }} minutes
                        &middot; pass at {{ $quiz->passMarkCount() }}/{{ $total }}
                    </h5>
                    <ul class="list-unstyled mb--20 small text-muted" style="line-height:2;">
                        <li>
                            <i class="feather-check me-1 text-success"></i>
                            Choose one answer per question.
                        </li>
                        <li>
                            <i class="feather-alert-triangle me-1 text-warning"></i>
                            Blank answers count as wrong.
                        </li>
                        <li>
                            <i class="feather-lock me-1 text-danger"></i>
                            Marked instantly; answers cannot change afterwards.
                        </li>
                    </ul>

                    <button type="submit" form="paper-form" class="btn btn-lg btn-success w-100">
                        <i class="feather-flag me-1"></i> Finish and see my results
                    </button>
                </div>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script src="{{ asset('assets/js/quiz.js') }}" defer></script>
@endpush
