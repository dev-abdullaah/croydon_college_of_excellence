{{--
    The four steps of buying a course, as a stepper.

    Shown on the pages that sit between clicking Buy and reaching Stripe, so
    the same four labels are in the same order wherever somebody lands. The
    point is that this is a known sequence with a known end, and that they are
    further along than they were a minute ago.

    Which step is current is passed in, not worked out here. A partial that
    derived it from the route would have to know about every page in the flow,
    and would quietly start disagreeing with the pages themselves.

    $step  string  one of: register, verify, review, pay
    $steps array   label => key, in order. Left at the default so a caller only
                   has to pass the current step.

    Markup notes:

    An ordered list, because the steps have a real order and a screen reader
    should say "step 2 of 4" rather than reading four disconnected words.

    The current step is marked with aria-current="step", and the completed
    ones are given a real class rather than only a colour change, so the
    distinction survives both a screen reader and a stylesheet that does not
    load.

    The completed steps are links back to where they were, because the most
    common thing somebody wants on a four step form is to go back one.

    The connector lines are drawn with ::after on the circle rather than as
    separate elements, so there is no extra markup for assistive technology to
    read out. They are decorative and hidden from it.
--}}

@php
    $steps = [
        'register' => 'Create account',
        'verify' => 'Verify email',
        'review' => 'Check order',
        'pay' => 'Pay',
    ];

    $order = array_keys($steps);
    $currentIndex = array_search($step, $order, true);
    $currentIndex = $currentIndex === false ? 0 : (int) $currentIndex;
@endphp

<nav class="checkout-steps" aria-label="Progress through checkout">
    <ol class="checkout-steps__list">
        @foreach ($steps as $key => $label)
            @php
                $index = $loop->index;
                $isCurrent = $index === $currentIndex;
                $isDone = $index < $currentIndex;
            @endphp

            <li class="checkout-steps__item {{ $isCurrent ? 'is-current' : '' }} {{ $isDone ? 'is-done' : '' }}"
                @if ($isCurrent) aria-current="step" @endif>
                {{--
                    Only a completed step is a link. Linking the current one
                    would reload the page the person is already on and throw
                    away anything they have typed into it.
                --}}
                @if ($isDone && $key === 'register')
                    <a href="{{ route('register') }}">
                        <span class="checkout-steps__num" aria-hidden="true">
                            <i class="feather-check"></i>
                        </span>
                        <span class="checkout-steps__label">{{ $label }}</span>
                    </a>
                @elseif ($isDone && $key === 'verify')
                    <a href="{{ route('verification.notice') }}">
                        <span class="checkout-steps__num" aria-hidden="true">
                            <i class="feather-check"></i>
                        </span>
                        <span class="checkout-steps__label">{{ $label }}</span>
                    </a>
                @elseif ($isDone && $key === 'review')
                    <a href="{{ $intendedCourse ? route('checkout.review', $intendedCourse) : route('courses.index') }}">
                        <span class="checkout-steps__num" aria-hidden="true">
                            <i class="feather-check"></i>
                        </span>
                        <span class="checkout-steps__label">{{ $label }}</span>
                    </a>
                @else
                    <span class="checkout-steps__link">
                        <span class="checkout-steps__num" aria-hidden="true">{{ $index + 1 }}</span>
                        <span class="checkout-steps__label">{{ $label }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
