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
                {{-- `.course-actions` is a hook, not decoration: the two links
                     below use Bootstrap's `.text-primary` and `.text-muted`,
                     which carry `!important` and have no dark-mode rule, so on
                     this section - `bg-color-white`, dark #192335 - they read
                     3.50:1 and 3.36:1. They need a class to be lifted from. --}}
                <div class="course-actions mb--30 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <a href="{{ route('courses.index') }}" class="text-primary fw-bold">
                        <i class="feather-arrow-left me-1"></i> Back to All Life in the UK Courses
                    </a>
                    @guest
                        <a href="{{ route('login') }}" class="text-muted small">
                            <i class="feather-user me-1"></i> Existing student? Sign in
                        </a>
                    @endguest
                </div>

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
                                        class="rbt-btn btn-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                        <span>Apply for Admission &mdash; {{ $course->formattedPrice() }}</span>
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <p class="mb-0 small">
                                    <i class="feather-phone-call me-2 text-primary"></i>
                                    Submit your application. Our admissions team will contact you to confirm payment and activate your course.
                                    @guest
                                        <br>
                                        Already registered?
                                        <a href="{{ route('login') }}">Log in to your account</a>.
                                    @endguest
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($course->slug === 'life-in-the-uk-course')
                    <div class="rbt-card variation-01 bg-color-extra2 p-4 radius-10 mt--40">
                        <h4 class="title mb-3">10 Structured Lessons Overview</h4>
                        <div class="row g-3 small">
                            <div class="col-md-6">
                                <div class="bg-white p-3 radius-10 border h-100">
                                    <strong class="d-block mb-1 text-primary">Core Knowledge &amp; History:</strong>
                                    <ul class="list-unstyled mb-0">
                                        <li>&bull; Lesson 1: Values and Principles of the UK</li>
                                        <li>&bull; Lesson 2: What is the UK?</li>
                                        <li>&bull; Lesson 3: Early Britain &amp; Medieval Period</li>
                                        <li>&bull; Lesson 4: A Global Power &amp; Empire</li>
                                        <li>&bull; Lesson 5: Modern Britain in the 20th Century</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-white p-3 radius-10 border h-100">
                                    <strong class="d-block mb-1 text-primary">Society, Government &amp; Mock Tests:</strong>
                                    <ul class="list-unstyled mb-0">
                                        <li>&bull; Lesson 6: Britain Since 1945</li>
                                        <li>&bull; Lesson 7: Modern, Thriving Society &amp; Culture</li>
                                        <li>&bull; Lesson 8: UK Government, The Law &amp; Your Role</li>
                                        <li>&bull; Lessons 9-10: Getting Involved &amp; Everyday Life</li>
                                        <li>&bull; <strong>Plus: 6 Classroom Mock Tests with teacher answer keys</strong></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <span class="text-muted small">Need only mock tests? Check out our <a href="{{ route('courses.show', '24-mock-tests') }}">24 Mock Tests Package (£49)</a>.</span>
                        </div>
                    </div>
                @elseif ($course->slug === '24-mock-tests')
                    <div class="rbt-card variation-01 bg-color-extra2 p-4 radius-10 mt--40">
                        <h4 class="title mb-3">24 Mock Tests Package Details</h4>
                        <p class="small text-muted mb-3">Designed specifically to give you the exam technique and stamina to pass the real 45-minute Home Office test:</p>
                        <div class="row g-3 small">
                            <div class="col-md-4">
                                <div class="bg-white p-3 radius-10 border text-center h-100">
                                    <h5 class="title mb-1 text-primary">576 Questions</h5>
                                    <p class="mb-0 text-muted">24 distinct practice papers sampling all 10 handbook syllabus topics.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-white p-3 radius-10 border text-center h-100">
                                    <h5 class="title mb-1 text-primary">45-Minute Timer</h5>
                                    <p class="mb-0 text-muted">Real exam countdown timer with instant scoring upon submission.</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-white p-3 radius-10 border text-center h-100">
                                    <h5 class="title mb-1 text-primary">Teacher Keys</h5>
                                    <p class="mb-0 text-muted">Clear explanations for every question so you learn from mistakes.</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <span class="text-muted small">Need the full lesson study cards? Check out our <a href="{{ route('courses.show', 'life-in-the-uk-course') }}">Life in the UK Course with 10 Lessons (£99)</a>.</span>
                        </div>
                    </div>
                @endif

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
