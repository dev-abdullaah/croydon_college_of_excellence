<!-- resources/views/website/pages/dashboard.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">My Account</h2>
                    <p class="mt--10 mb-0">
                        Signed in as {{ auth()->user()->email }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">


                <div class="row mb--40 align-items-center">
                    <div class="col-md-8">
                        <h3 class="title mb-0">Your purchased materials</h3>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            {{--
                                Red, and full size. Signing out is the one thing
                                on this page that undoes something, so it should
                                not look like the neutral "Course details" link
                                sitting four inches below it.
                            --}}
                            <button type="submit" class="btn btn-lg btn-danger">Sign Out</button>
                        </form>
                    </div>
                </div>

                @if ($learnableCourses->isNotEmpty())
                    <h3 class="title mb--20">Start learning</h3>

                    <div class="row g-3 mb--40">
                        @foreach ($learnableCourses as $course)
                            @php
                                $p = $lessonProgress[$course->slug] ?? ['done' => 0, 'total' => 0];
                                // Only the course the success page named can carry
                                // the badge, and the controller has already matched
                                // that slug against the paid purchases this account
                                // owns, so it cannot mark a course they do not have.
                                $isNew = $highlightCourse && $highlightCourse->id === $course->id;
                            @endphp
                            <div class="col-lg-6 col-12">
                                {{-- The whole card is the link, and it turns into
                                     a visible ring while the badge is showing so
                                     the eye is drawn to the thing that just
                                     arrived rather than having to read a label to
                                     work out which one is new. --}}
                                <a href="{{ route('learn.index', $course) }}"
                                    class="rbt-service rbt-service-2 radius-10 h-100 d-block text-decoration-none text-reset {{ $isNew ? 'dashboard-course--new' : '' }}">
                                    <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                                        <h4 class="title mb-0">{{ $course->name }}</h4>
                                        @if ($isNew)
                                            <span class="badge bg-primary">New</span>
                                        @endif
                                    </div>
                                    @if ($isNew)
                                        <p class="small mb-2">
                                            <i class="feather-check-circle me-1"></i>
                                            Payment received. This is yours.
                                        </p>
                                    @endif
                                    @if ($p['total'] > 0)
                                        @php $pct = (int) round($p['done'] / $p['total'] * 100); @endphp
                                        <p class="mb-2 small">
                                            {{ $p['done'] }} of {{ $p['total'] }} lessons read
                                            @if ($p['done'] === $p['total'])
                                                &mdash; all done
                                            @endif
                                        </p>
                                        <div class="progress" role="progressbar" aria-label="Lessons read"
                                            aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
                                            style="height: .45rem;">
                                            <div class="progress-bar" style="width: {{ $pct }}%"></div>
                                        </div>
                                    @else
                                        <p class="mb-2 small">Go to your papers and start practising.</p>
                                    @endif
                                    <span class="btn btn-lg btn-primary mt--20">
                                        <span>{{ $isNew ? 'Start learning' : 'Open the course' }}</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                @forelse ($paidPurchases as $purchase)
                    <div class="rbt-service rbt-service-2 radius-10 mb--30">
                        <div class="row align-items-center g-3">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-center mb-2">
                                    <h4 class="title mb-0 me-3">{{ $purchase->course->name }}</h4>
                                    <span class="{{ $purchase->statusBadgeClass() }}">{{ $purchase->statusLabel() }}</span>
                                </div>
                                <p class="mb-0">
                                    Paid {{ $purchase->paid_at?->format('j M Y, H:i') }}
                                    &middot; {{ number_format($purchase->amount / 100, 2) }}
                                    {{ strtoupper($purchase->currency) }}
                                </p>
                            </div>
                            <div class="col-lg-4">
                                <a href="{{ route('courses.show', $purchase->course) }}"
                                    class="btn btn-lg btn-outline-secondary w-100">Course details</a>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="title">Your material</h5>
                        @if ($purchase->course->hasLearningContent())
                            <p class="mb-3">
                                This course is read and tested on the website. Nothing is downloaded.
                            </p>
                            <a href="{{ route('learn.index', $purchase->course) }}"
                                class="btn btn-lg btn-primary">
                                <span>Open the course</span>
                            </a>
                        @else
                            <p class="mb-0">Everything in this course is on the website. Please
                                contact us on 07405 073764 if you cannot find it.</p>
                        @endif
                    </div>
                @empty
                    {{-- Only when there is nothing half-finished either. Telling
                         somebody who is midway through a purchase that they
                         "have not purchased anything yet", and then listing
                         their purchase underneath, reads as a contradiction. --}}
                    @if ($unfinishedCourses->isEmpty())
                    <div class="rbt-service rbt-service-2 radius-10">
                        <h4 class="title">You have not purchased anything yet</h4>
                        <p>
                            Choose the Life in the UK Course or the 24 Mock Tests package and your course will
                            appear here the moment Stripe confirms your payment.
                        </p>
                        {{-- Centred, and sized to its own label.

                             .rbt-btn is display:flex, which fills whatever block
                             box it is put in. The card is 875px wide, so left
                             alone this renders as a full-width bar with a
                             caption on it rather than as a button.
                             d-inline-flex is the one class that overrides it,
                             shrinking the button to the label; the wrapper
                             around it then does the centring. --}}
                        <div class="text-center mt--20">
                            <a href="{{ route('courses.index') }}"
                                class="rbt-btn hover-icon-reverse btn-border-gradient radius-round d-inline-flex">
                                <div class="icon-reverse-wrapper">
                                    <span class="btn-text">Browse the courses</span>
                                    {{-- Two icons: the first shows, the second slides
                                         in on hover and the first slides away. --}}
                                    <span class="btn-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="feather feather-arrow-right">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </span>
                                    <span class="btn-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="feather feather-arrow-right">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                    @endif
                @endforelse

                {{--
                    "Complete your purchase", not "Recent checkout attempts".

                    The old heading described the database rows behind it. That
                    is the wrong way round: a customer did not make an
                    "attempt", they started buying a course and did not finish,
                    and a list of internal statuses beside each name reads as
                    several separate things going wrong. So this is one entry
                    per course they have started and not finished, with a single
                    button, and no statuses at all.

                    The button goes to checkout.start rather than to the
                    payment form or the course page, because start is the only
                    place that knows what this person still needs. It is a GET
                    that changes nothing but the session, so it is safe to be
                    sitting here waiting to be clicked, and safe to arrive at
                    from a bookmark.

                    One row per course, not per purchase: three clicks on the
                    pay button is one thing left to do, and listing it three
                    times would make it look like three.
                --}}
                @if ($unfinishedCourses->isNotEmpty())
                    <div class="rbt-service rbt-service-2 radius-10 mt--30">
                        <h4 class="title">Complete your purchase</h4>
                        <p>
                            You started buying {{ $unfinishedCourses->count() === 1 ? 'this course' : 'these courses' }}
                            but have not finished. Nothing has been charged &mdash; pick up where you left off.
                        </p>
                        <ul class="list-unstyled mb-0">
                            @foreach ($unfinishedCourses as $course)
                                <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
                                    <span>
                                        <strong>{{ $course->name }}</strong>
                                        <small class="ms-2">{{ $course->formattedPrice() }}</small>
                                    </span>
                                    <a href="{{ route('checkout.start', $course) }}"
                                        class="btn btn-lg btn-primary">Continue</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

@endsection
