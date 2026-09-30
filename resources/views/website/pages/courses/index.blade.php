<!-- resources/views/website/pages/courses/index.blade.php -->
{{--
    The course catalogue, on a page of its own.

    Reached from the account page, the checkout cancel page and the main menu.
    Prices and features come from the Course model via CatalogService, never
    from the request. The buy buttons are POST forms protected by CSRF, the
    slug is resolved against the database and the Stripe Price is read on the
    server, so a tampered form cannot change what anybody is charged.
--}}
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <span class="subtitle bg-primary-opacity">OUR COURSES</span>
                    <h2 class="title">Choose The Course You Need</h2>
                    <p class="mt--20 mb-0">
                        Pay once and keep access for life. Lessons and papers are read
                        and taken on this website, so there is nothing to download.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Course List Start -->
<div class="bg-color-white rbt-section-gap">
    <div class="container">
        @forelse ($courses as $course)
            <div class="row justify-content-center mb--30">
                <div class="col-lg-10">
                    <div class="rbt-service rbt-service-2 rbt-hover-02 radius-10">
                        <div class="row g-4 align-items-start">
                            <div class="col-lg-7">
                                @if ($course->badge)
                                    <div class="mb--15">
                                        <span class="badge bg-primary">{{ $course->badge }}</span>
                                    </div>
                                @endif

                                <h3 class="title mb-0">{{ $course->name }}</h3>

                                <p class="mt--10 mb-0">{{ $course->short_description }}</p>

                                <div class="mt--20">
                                    <span class="price" style="font-size: 2.5rem; font-weight: 700; line-height: 1;">
                                        {{ $course->formattedPrice() }}
                                    </span>
                                    <span class="ms-2">one-off payment</span>
                                </div>
                            </div>

                            <div class="col-lg-5">
                                <h4 class="title">What is included</h4>
                                <ul class="rbt-list-style-1 list-unstyled mb-0">
                                    @foreach ($course->features as $feature)
                                        <li class="d-flex">
                                            <i class="feather-check"></i>
                                            <span class="ms-2">{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row align-items-center g-3">
                            <div class="col-lg-6">
                                @auth
                                    @if ($course->hasAccessFor(auth()->user()))
                                        <a href="{{ $course->requiresPurchase() ? route('dashboard') : route('learn.index', $course) }}"
                                            class="rbt-btn btn-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                            <span>{{ $course->requiresPurchase() ? 'View In My Account' : 'Start Learning' }}</span>
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('checkout.store', $course) }}">
                                            @csrf
                                            <button type="submit"
                                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                                <span>Buy {{ $course->name }} &mdash; {{ $course->formattedPrice() }}</span>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    @if ($course->requiresPurchase())
                                        <form method="POST" action="{{ route('checkout.store', $course) }}">
                                            @csrf
                                            <button type="submit"
                                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                                <span>Buy {{ $course->name }} &mdash; {{ $course->formattedPrice() }}</span>
                                            </button>
                                        </form>
                                    @else
                                        {{-- The paywall is off, but a sitting is tied to
                                             an account, so sign in rather than sell. --}}
                                        <a href="{{ route('login') }}"
                                            class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                            <span>Sign In To Start Learning</span>
                                        </a>
                                    @endif
                                @endauth
                            </div>
                            <div class="col-lg-6">
                                <div class="text-lg-end text-center">
                                    <a href="{{ route('courses.show', $course) }}">See full details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            {{-- The catalogue has not been seeded yet. Say so plainly rather
                 than rendering an empty list with no explanation. --}}
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="rbt-service rbt-service-2 radius-10 text-center">
                        <h4 class="title">Courses are not available right now</h4>
                        <p class="mb-0">
                            Please call us on
                            <a href="tel:+447405073764">+44 7405 073764</a>
                            and we will talk you through the options.
                        </p>
                    </div>
                </div>
            </div>
        @endforelse

        <div class="row mt--20">
            <div class="col-lg-12">
                <p class="text-center mb-0">
                    <i class="feather-shield mr--10"></i>
                    Secure payment by Stripe &middot; Instant access after payment is confirmed
                    &middot; Card details never touch this website
                </p>
            </div>
        </div>
    </div>
</div>
<!-- Course List End -->

<div class="rbt-separator-mid">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection
