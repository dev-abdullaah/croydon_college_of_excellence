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
                        <span class="ms-2">one-off payment &middot; lifetime access</span>
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

                    @if (! $course->requiresPurchase())
                        {{-- The paywall is off while the material is being built.
                             An account is still needed to hold a sitting. --}}
                        @auth
                            <div class="row align-items-center g-3">
                                <div class="col-lg-6">
                                    <a href="{{ route('learn.index', $course) }}"
                                        class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                        <span>Start Learning</span>
                                    </a>
                                </div>
                                <div class="col-lg-6">
                                    <p class="mb-0">
                                        <i class="feather-check-circle me-2"></i>
                                        Every lesson and paper is open to your account while this is in progress.
                                    </p>
                                </div>
                            </div>
                        @else
                            <div class="row align-items-center g-3">
                                <div class="col-lg-6">
                                    <a href="{{ route('login') }}"
                                        class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                        <span>Sign In To Start</span>
                                    </a>
                                </div>
                                <div class="col-lg-6">
                                    <p class="mb-0">
                                        New here?
                                        <a href="{{ route('register') }}">Create a free account</a>
                                        to begin.
                                    </p>
                                </div>
                            </div>
                        @endauth
                    @elseif ($course->hasAccessFor(auth()->user()))
                        <div class="alert alert-success mb-0" role="alert">
                            You already own this course.
                            <a href="{{ route('learn.index', $course) }}" class="ms-2">Start learning</a>.
                        </div>
                    @else
                        @auth
                            <form method="POST" action="{{ route('checkout.store', $course) }}">
                                @csrf
                                <div class="row align-items-center g-3">
                                    <div class="col-lg-6">
                                        <div class="rbt-btn-wrapper">
                                            <button type="submit"
                                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                                <span>Buy Now &mdash; {{ $course->formattedPrice() }}</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <p class="mb-0">
                                            <i class="feather-lock me-2"></i>
                                            You will be taken to Stripe's secure checkout to pay by card.
                                        </p>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="row align-items-center g-3">
                                <div class="col-lg-6">
                                    <a href="{{ route('login') }}"
                                        class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                        <span>Sign In To Buy &mdash; {{ $course->formattedPrice() }}</span>
                                    </a>
                                </div>
                                <div class="col-lg-6">
                                    <p class="mb-0">
                                        New here?
                                        <a href="{{ route('register') }}">Create a free account</a>
                                        to buy this course.
                                    </p>
                                </div>
                            </div>
                        @endauth
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
