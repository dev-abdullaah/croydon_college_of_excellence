<!-- resources/views/website/pages/courses_send.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-page-banner-wrapper">
    <!-- Start Banner BG Image  -->
    <div class="rbt-banner-image"></div>
    <!-- End Banner BG Image  -->
    <div class="rbt-banner-content">

        <!-- Start Banner Content Top -->
        <div class="rbt-banner-content-top">
            <div class="container">
                <div class="row justify-content-center text-center"> <!-- Centering the row and text -->
                    <div class="col-lg-12">
                        <div>
                            <h1 class="title mb--0">SEND Courses</h1>
                        </div>
                        <p class="description">Courses that help beginners become true unicorns.</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Banner Content Top -->

    </div>
</div>

<!-- Start Course Area  -->
<div class="rbt-section-overlayping-top rbt-section-gapBottom">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="tab-content" id="rbt-myTabContent">

                    <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                        <div class="rbt-course-grid-column">

                            <!-- Start English Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-english') }}">
                                            <img src="{{ asset('assets/images/course/english_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-english') }}">
                                                    English
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to Our UK English Course (KS1 - KS5)!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-english') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End English Course  -->

                            <!-- Start Math Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-math') }}">
                                            <img src="{{ asset('assets/images/course/math_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-math') }}">
                                                    Mathematics
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to Our Math Courses (KS1 - KS5)!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-math') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Math Course  -->

                            <!-- Start Science Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-science') }}">
                                            <img src="{{ asset('assets/images/course/science_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-science') }}">
                                                    Science
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to Our Science Courses at CCE (KS1 - KS5)!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-science') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Science Course  -->

                            <!-- Start 11+ Exam Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-exam') }}">
                                            <img src="{{ asset('assets/images/course/exam_11.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-exam') }}">
                                                    11+ Exam Preparation
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the 11+ Exam Preparation Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-exam') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End 11+ Exam Course  -->

                            <!-- Start SAT Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-sat') }}">
                                            <img src="{{ asset('assets/images/course/sat_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-sat') }}">
                                                    SATs/Year 6
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the SATs Preparation Course at CCE - Year 6!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-sat') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End SAT Course  -->

                            <!-- Start Skill Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-skills') }}">
                                            <img src="{{ asset('assets/images/course/functional_skill.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-skills') }}">
                                                    Functional Skills
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Functional Skills Preparation Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On English and Math</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-skills') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Skill Course  -->

                            <!-- Start ESOL Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-esol') }}">
                                            <img src="{{ asset('assets/images/course/esol_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-esol') }}">
                                                    ESOL
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the ESOL Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On Speaking, Listening, Reading and Writing</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-esol') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End ESOL Course  -->

                            <!-- Start Literacy Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-literacy') }}">
                                            <img src="{{ asset('assets/images/course/literacy.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-literacy') }}">
                                                    Literacy & Numeracy
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Literacy & Numeracy Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On Literacy and Numeracy</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-literacy') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Literacy Course  -->

                            <!-- Start Humanities Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-humanities') }}">
                                            <img src="{{ asset('assets/images/course/humanity_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-humanities') }}">
                                                    Humanities
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Humanities Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-humanities') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Humanities Course  -->

                            <!-- Start Business Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-business') }}">
                                            <img src="{{ asset('assets/images/course/business_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-business') }}">
                                                    Business
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Business Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On UK National Curriculum</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-business') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Business Course  -->

                            <!-- Start ICT Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-ict') }}">
                                            <img src="{{ asset('assets/images/course/ict_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-ict') }}">
                                                    ICT
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to SEN ICT Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On Fundamental ICT Skills</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-ict') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End ICT Course  -->

                            <!-- Start Life Skill Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-life-skills') }}">
                                            <img src="{{ asset('assets/images/course/life_skill_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-life-skills') }}">
                                                    Life Skills
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Life Skills for SEN Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On Developing Essential Skills</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-life-skills') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Life Skill Course  -->

                            <!-- Start Music Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/send-music') }}">
                                            <img src="{{ asset('assets/images/course/music_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/send-music') }}">
                                                    Music & Art
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Music & Art Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text"> Join Us In This Creative Environment</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/send-music') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End Music Course  -->

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Course Area  -->

<div class="rbt-separator-mid">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection