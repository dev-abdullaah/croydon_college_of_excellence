<!-- resources/views/website/pages/courses/show.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    @if ($course->badge)
                        <span class="subtitle bg-primary-opacity">{{ strtoupper($course->badge) }}</span>
                    @endif
                    <h2 class="title">{{ $course->name }}</h2>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Course Details Start -->
<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">


                <div class="rbt-service rbt-service-2 radius-10">
                    <h3 class="title">{{ $course->name }}</h3>

                    <div class="mt--15">
                        <span style="font-size: 2.75rem; font-weight: 700; line-height: 1;">
                            {{ $course->formattedPrice() }}
                        </span>
                        <span class="ms-2">one-off payment &middot; access in your account</span>
                    </div>

                    <p class="mt--20 mb-0">{!! nl2br(e($course->description)) !!}</p>

                    <hr class="my-4">

                    <div class="row g-4">
                        <div class="col-lg-7">
                            <h4 class="title">What is included</h4>
                            <ul class="rbt-list-style-1 list-unstyled">
                                @foreach ($course->features as $feature)
                                    <li class="d-flex">
                                        <i class="feather-check"></i>
                                        <span class="ms-2">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="col-lg-5">
                            <h4 class="title">How it works</h4>
                            <ul class="rbt-list-style-1 list-unstyled mb-0">
                                <li class="d-flex">
                                    <i class="feather-monitor"></i>
                                    <span class="ms-2">Read the lessons online, one card at a time</span>
                                </li>
                                <li class="d-flex">
                                    <i class="feather-edit-3"></i>
                                    <span class="ms-2">Take every knowledge check and mock test as multiple choice</span>
                                </li>
                                <li class="d-flex">
                                    <i class="feather-bar-chart-2"></i>
                                    <span class="ms-2">Get your score and answers immediately, and try again as often as you like</span>
                                </li>
                                <li class="d-flex">
                                    <i class="feather-lock"></i>
                                    <span class="ms-2">Everything stays private to your account</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <hr class="my-4">

                    @if ($course->hasAccessFor(auth()->user()))
                        <div class="alert alert-success mb-0" role="alert">
                            You already own this course.
                            <a href="{{ route('learn.index', $course) }}" class="ms-2">Start learning</a>.
                        </div>
                    @else
                        {{--
                            One Buy button, for everybody who does not own it.

                            This used to fork on @auth: a signed-in visitor got a
                            POST form, a guest got a link to the login page. The
                            fork existed because a POST form behind `auth` threw
                            a guest straight to a sign-in form with the course
                            already forgotten, and the workaround was to make
                            guests log in first. Now the button is the same for
                            both and points at checkout.start, which decides
                            what still has to happen - account, code, review,
                            payment - and remembers the course across all of it.

                            A GET rather than a POST is also why this is safe to
                            reach from an email or a bookmark: it changes the
                            session and nothing else, and it never creates a
                            payment.
                        --}}
                        <div class="row align-items-center g-3">
                            <div class="col-lg-6">
                                <div class="rbt-btn-wrapper">
                                    <a href="{{ route('checkout.start', $course) }}"
                                        class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                        <span>Buy Now &mdash; {{ $course->formattedPrice() }}</span>
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <p class="mb-0">
                                    <i class="feather-lock me-2"></i>
                                    You will be taken to Stripe's secure checkout to pay by card.
                                    @guest
                                        <br>
                                        Already have an account?
                                        <a href="{{ route('login') }}">Log in</a>.
                                    @endguest
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
<!-- Course Details End -->

<div class="rbt-separator-mid">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection
