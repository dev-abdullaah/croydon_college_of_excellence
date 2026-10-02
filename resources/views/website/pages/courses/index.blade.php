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
                        Learn online in your own account: read the lessons and take the
                        practice tests. Pay once, with no subscription.
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
        <!-- Returning Student Quick Access Alert -->
        <div class="row justify-content-center mb--40">
            <div class="col-lg-10">
                @include('website.partials.enrollment_banner')
            </div>
        </div>

        {{-- The anchor the "See The Courses" button in the banner above
             scrolls to for a signed in learner with no course yet. --}}
        <div id="course-list">
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
                                    @if ($course->hasAccessFor(auth()->user()))
                                        <a href="{{ route('dashboard') }}"
                                            class="rbt-btn btn-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                            <span>View In My Account</span>
                                        </a>
                                    @else
                                        {{-- A link, not a form: checkout.start carries
                                             the visitor through whichever step they
                                             still need. See partials/paid_courses. --}}
                                        <a href="{{ route('checkout.start', $course) }}"
                                            class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                            <span>Buy {{ $course->name }} &mdash; {{ $course->formattedPrice() }}</span>
                                        </a>
                                    @endif
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
        </div>

        <!-- Course Syllabus and Test Guide Section -->
        <div class="row justify-content-center mt--50">
            <div class="col-lg-10">
                <div class="rbt-card variation-01 bg-color-extra2 p-4 radius-10">
                    <h3 class="title text-center mb--30">Everything You Need To Pass The Life in the UK Test</h3>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="bg-white p-4 radius-10 h-100 border">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="quick-access-icon-box icon-blue me-3 mb-0" style="width: 44px; height: 44px; font-size: 20px;">
                                        <i class="feather-book"></i>
                                    </div>
                                    <h4 class="title mb-0" style="font-size: 1.25rem;">10 Structured Lessons</h4>
                                </div>
                                <p class="small text-muted mb-3">Full official handbook content broken down into easy-to-read cards:</p>
                                <ul class="rbt-list-style-1 list-unstyled mb-0 small">
                                    <li><i class="feather-check text-primary"></i> <strong>Lessons 1-2:</strong> Values, Principles &amp; What is the UK</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Lessons 3-6:</strong> Complete British History from Early Times to Post-War</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Lesson 7:</strong> Modern, Thriving Society, Culture &amp; Sport</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Lesson 8:</strong> UK Government, The Law &amp; Your Role</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Lessons 9-10:</strong> Community Involvement, Everyday Life &amp; Traditions</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="bg-white p-4 radius-10 h-100 border">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="quick-access-icon-box icon-purple me-3 mb-0" style="width: 44px; height: 44px; font-size: 20px;">
                                        <i class="feather-clock"></i>
                                    </div>
                                    <h4 class="title mb-0" style="font-size: 1.25rem;">24 Timed Mock Tests</h4>
                                </div>
                                <p class="small text-muted mb-3">Simulate the real Home Office examination format:</p>
                                <ul class="rbt-list-style-1 list-unstyled mb-0 small">
                                    <li><i class="feather-check text-primary"></i> <strong>576 Questions:</strong> 24 separate papers covering all 10 topics</li>
                                    <li><i class="feather-check text-primary"></i> <strong>45-Minute Timer:</strong> Practice against the clock to build speed</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Real Pass Mark:</strong> 18 out of 24 (75%) required to pass</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Instant Scoring:</strong> Know your result the moment you submit</li>
                                    <li><i class="feather-check text-primary"></i> <strong>Teacher Answer Keys:</strong> Review full explanations and retry anytime</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt--40">
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
