<!-- resources/views/website/pages/courses_regular/regular_esol.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Start Subject Heading -->
<div class="rbt-conatct-area bg-gradient-5 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">ESOL Preparation Courses</h2>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Subject Heading -->

<!-- Course Details Starts-->
<div class="rbt-course-details-area ptb-5"
    style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="course-details-content">

                    <br>
                    
                    <div class="col-xl-12 col-md-12 col-sm-12 col-12 mt--30">
                        <div class="rbt-service rbt-service-2 variation-2 color-box bg-color-white-off radius-10">
                            <div class="rbt-course-feature-inner radius-10">
                                <div class="section-title">
                                    <h3 class="title mb--20">Welcome to the ESOL Course at CCE!</h3>
                                </div>
                                <div>
                                    We are delighted to welcome you to our <span class="txt-highlight">ESOL (English
                                        for Speakers of Other Languages)</span> course!
                                    Whether you are just beginning your English language journey or looking to
                                    improve your skills, this
                                    course is designed to support you every step of the way.
                                </div>
                                <div class="mt--20">
                                    In this course, you will develop essential <span class="txt-highlight">reading,
                                        writing, listening</span>, and
                                    <span class="txt-highlight">speaking</span> skills in English,
                                    helping you communicate confidently in everyday situations, work, and your
                                    community. Our lessons are tailored to suit your level, whether you are a
                                    beginner or at an intermediate level, ensuring you make steady progress.
                                </div>
                            </div>

                            <div class="inner mt--20">
                                <div class="content">
                                    <h4 class="title">We Focus On:</h4>
                                    <ul class="rbt-list-style-1">
                                        <li>
                                            <i class="feather-check"></i>
                                            <strong>Speaking&nbsp;:&nbsp;</strong>
                                            Entry Level 1, 2, 3 & Level 1, 2
                                        </li>
                                        <li>
                                            <i class="feather-check"></i>
                                            <strong>Listening&nbsp;:&nbsp;</strong>
                                            Entry Level 1, 2, 3 & Level 1, 2
                                        </li>
                                        <li>
                                            <i class="feather-check"></i>
                                            <strong>Reading&nbsp;:&nbsp;</strong>
                                            Entry Level 1, 2, 3 & Level 1, 2
                                        </li>
                                        <li>
                                            <i class="feather-check"></i>
                                            <strong>Writing&nbsp;:&nbsp;</strong>
                                            Entry Level 1, 2, 3 & Level 1, 2
                                        </li>
                                    </ul>
                                </div>
                                <div class="thumbnail">
                                    <img src="{{ asset('assets/images/course/esol_course.png') }}"
                                        style="height: 200px!important; border-radius: 10px;">
                                </div>
                            </div>
                            <a class="rbt-btn btn-gradient btn-sm mt--30 w-100"></a>

                            <div class="rbt-course-feature-inner mt-4 text-start">
                                <h4 style="color: rgb(255, 37, 37) !important;">
                                    <strong>
                                        *** Grab 3 Free Mock Tests***
                                    </strong>
                                </h4>
                            </div>

                            <div class="inner">
                                <div class="content">
                                    <h4 class="title">We Offer:</h4>
                                    <ul class="rbt-list-style-1">
                                        <li><i class="feather-check"></i>1:1/2:1/3:1/4:1 & 5:1 Max Learning
                                            Groups</li>
                                        <li><i class="feather-check"></i>GCSE & A Level Crash Course</li>
                                        <li><i class="feather-check"></i>Exam Preparation Only Courses</li>
                                        <li><i class="feather-check"></i>Boost Up Courses</li>
                                        <li><i class="feather-check"></i>Scale Up 1 Score in GCSE/A Level
                                            Courses</li>
                                    </ul>
                                </div>
                                <div class="thumbnail">
                                    <!-- <img src="assets/images/service/service-02.png" style="height: 200px!important;"> -->
                                </div>
                            </div>
                            <a class="rbt-btn btn-gradient btn-sm mt--30 w-100"></a>

                            <div class="inner mt-4">
                                <div class="content">
                                    <h4 class="title">Our Specialties:</h4>
                                    <ul class="rbt-list-style-1">
                                        <li><i class="feather-check"></i>Fully Focused On UK National Curriculum
                                        </li>
                                        <li><i class="feather-check"></i>Fully UK Qualified Teachers</li>
                                        <li><i class="feather-check"></i>Flexible Timing</li>
                                        <li><i class="feather-check"></i>Baseline, Progressive, Year-End
                                            Assessments</li>
                                        <li><i class="feather-check"></i>6 Weeks Review Meeting with Parents
                                        </li>
                                        <li><i class="feather-check"></i>1:1 Problem Solving Sessions</li>
                                        <li><i class="feather-check"></i>Wide Range of Courses</li>
                                        <li><i class="feather-check"></i>Strong Track Record of Success</li>
                                    </ul>
                                </div>
                                <div class="thumbnail">
                                    <!-- <img src="assets/images/service/service-02.png" style="height: 200px!important;"> -->
                                </div>
                            </div>
                            <a class="rbt-btn btn-gradient btn-sm mt--30 w-100"></a>

                            <div class="rbt-course-feature-inner color-box card-bg-4 radius-10">
                                <div class="mt-4 text-center">
                                    <h4><strong>
                                            Affordable Fees and Qualified Teachers Await You!
                                            <br>
                                            <span style="color: rgb(255, 37, 37) !important;">Get 40%
                                                Discount!</span>
                                        </strong>
                                    </h4>
                                    <div class="rbt-btn-wrapper pb-3">
                                        <a class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center"
                                            href="{{ url('/free-assesment') }}">
                                            <span>Book Free Assessment</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<!-- Course Details Ends-->

<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection