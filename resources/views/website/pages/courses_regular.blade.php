<!-- resources/views/website/pages/courses_regular.blade.php -->
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
                            <h1 class="title mb--0">Online / Offline Courses</h1>
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
                                        <a href="{{ url('/regular-english') }}">
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
                                                <a href="{{ url('/regular-english') }}">
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
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center flex-button"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center flex-button"
                                                    href="{{ url('/regular-english') }}">
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
                                        <a href="{{ url('/regular-math') }}">
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
                                                <a href="{{ url('/regular-math') }}">
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
                                                    href="{{ url('/regular-math') }}">
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
                                        <a href="{{ url('/regular-science') }}">
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
                                                <a href="{{ url('/regular-science') }}">
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
                                                    href="{{ url('/regular-science') }}">
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
                                        <a href="{{ url('/regular-exam') }}">
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
                                                <a href="{{ url('/regular-exam') }}">
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
                                                    href="{{ url('/regular-exam') }}">
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
                                        <a href="{{ url('/regular-sat') }}">
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
                                                <a href="{{ url('/regular-sat') }}">
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
                                                    href="{{ url('/regular-sat') }}">
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
                                        <a href="{{ url('/regular-skills') }}">
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
                                                <a href="{{ url('/regular-skills') }}">
                                                    Functional Skills
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the Functional Skills Preparation Courses at CCE!
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
                                                    href="{{ url('/regular-skills') }}">
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
                                        <a href="{{ url('/regular-esol') }}">
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
                                                <a href="{{ url('/regular-esol') }}">
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
                                                    href="{{ url('/regular-esol') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End ESOL Course  -->

                            <!-- Start IELTS Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/regular-ielts') }}">
                                            <img src="{{ asset('assets/images/course/ielts_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/regular-ielts') }}">
                                                    IELTS
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the IELTS Preparation Course at CCE!
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
                                                    href="{{ url('/regular-ielts') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End IELTS Course  -->

                            <!-- Start UKVI Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/regular-ukvi') }}">
                                            <img src="{{ asset('assets/images/course/ukvi_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/regular-ukvi') }}">
                                                    UKVI Life Skills
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to the UKVI Life Skills / A1, A2 Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On Practical Communication and Understanding of British Culture</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/regular-ukvi') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End UKVI Course  -->

                            <!-- Start UK Life Course  -->
                            <div class="course-grid-3">
                                <div class="rbt-card variation-01 rbt-hover">
                                    <div class="rbt-card-img">
                                        <a href="{{ url('/regular-uk-life') }}">
                                            <img src="{{ asset('assets/images/course/life_uk_course.png') }}">
                                            <div class="rbt-badge-3 bg-white">
                                                <span class="discountValue">40%</span>
                                                <span>Off</span>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="rbt-card-body">
                                        <div class="rbt-card-top">
                                            <h4 class="rbt-card-title">
                                                <a href="{{ url('/regular-uk-life') }}">
                                                    Life in the UK
                                                </a>
                                            </h4>
                                        </div>
                                        <ul class="rbt-meta">
                                            <li>
                                                <i class="feather-book"></i>
                                                Welcome to Life in the UK Course at CCE!
                                            </li>
                                        </ul>
                                        <p class="rbt-card-text">We Focus On British History, Govt. and Law, Culture and Traditions and Everyday Life</p>

                                        <div class="rbt-card-bottom">
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/enroll-now') }}">
                                                    <span>Enroll Now</span>
                                                </a>
                                            </div>
                                            <div class="rbt-btn-wrapper">
                                                <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                                                    href="{{ url('/regular-uk-life') }}">
                                                    <span>View Details</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- End UK Life Course  -->

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