<!-- resources/views/website/pages/courses_send/send_science.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Start Subject Heading -->
<div class="rbt-page-banner-wrapper">
    <!-- Start Banner BG Image  -->
    <div class="rbt-banner-image" style="height: 60% !important;"></div>
    <!-- End Banner BG Image  -->
    <div class="rbt-banner-content">

        <!-- Start Subject Area -->
        <div class="rbt-banner-content-top">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="section-title text-center">
                            <h2 class="title">Science</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Subject Area -->

        <!-- Start Course Top  -->
        <div class="rbt-course-top-wrapper mt--40"
            style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;">
            <div class="container">
                <div class="row g-5 align-items-center">

                    <div class="col-lg-12 mt--60">
                        <ul class="rbt-portfolio-filter filter-tab-button justify-content-center nav nav-tabs"
                            id="rbt-myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="active" id="physics-tab" data-bs-toggle="tab"
                                    data-bs-target="#physics" type="button" role="tab" aria-controls="physics"
                                    aria-selected="true">
                                    <span class="filter-text"><strong>Physics</strong></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button id="chemistry-tab" data-bs-toggle="tab" data-bs-target="#chemistry"
                                    type="button" role="tab" aria-controls="chemistry" aria-selected="false">
                                    <span class="filter-text"><strong>Chemistry</strong></span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button id="biology-tab" data-bs-toggle="tab" data-bs-target="#biology"
                                    type="button" role="tab" aria-controls="biology" aria-selected="false">
                                    <span class="filter-text"><strong>Biology</strong></span>
                                </button>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
        <!-- End Course Top  -->
    </div>
</div>
<!-- End Subject Heading -->

<!-- Course Details Starts-->
<div class="rbt-section-overlayping-top mb--20"
        style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="tab-content" id="rbt-myTabContent">

                        <div class="tab-pane fade show active" id="physics" role="tabpanel"
                            aria-labelledby="physics-tab">
                            <div class="rbt-course-grid-column">

                                <!-- Start Single Course  -->
                                <div class="col-xl-12 col-md-12 col-sm-12 col-12 mt--30">
                                    <div
                                        class="rbt-service rbt-service-2 variation-2 color-box bg-color-white-off radius-10">
                                        <div class="rbt-course-feature-inner radius-10">
                                            <div class="section-title">
                                                <h3 class="title mb--20">Welcome to Our Science Courses at CCE (KS1 -
                                                    KS5)! </h3>
                                            </div>
                                            <div>
                                                At <span class="txt-highlight">Croydon College of Excellence
                                                    (CCE)</span>, we believe that every student
                                                deserves the
                                                opportunity to excel in <span class="txt-highlight">Physics</span>,
                                                regardless of their learning
                                                challenges. Our <span class="txt-highlight">Special Educational
                                                    Needs (SEN) Physics Courses</span> are designed to provide <span
                                                    class="txt-highlight">structured,
                                                    supportive, and engaging</span>
                                                lessons tailored to individual needs.
                                            </div>
                                        </div>

                                        <div class="rbt-course-feature-inner mt-4 text-start">
                                            <strong>
                                                ***Empowering Every Learner to Explore, Understand & Succeed in
                                                Physics!***
                                            </strong>
                                        </div>

                                        <div class="inner mt--20">
                                            <div class="content">
                                                <h4 class="title">We Focus On UK National Curriculum:</h4>
                                                <ul class="rbt-list-style-1">
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS1&nbsp;:&nbsp;</strong>
                                                        Everyday Materials, Forces & Motion, Light & Dark, Sound &
                                                        Hearing, Electricity (Basic Introduction), and Seasonal Changes.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS2&nbsp;:&nbsp;</strong>
                                                        Forces & Motion, Electricity, Light & Shadows, Sound, Earth &
                                                        Space.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS3&nbsp;:&nbsp;</strong>
                                                        Forces & Motion, Electricity & Magnetism, Energy, Waves, Matter
                                                        & Particles, Space & the Universe.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS4&nbsp;:&nbsp;</strong>
                                                        GCSE (Years 10 & 11) - Paper 1 & Paper 2 (AQA, Pearson, Eduqas,
                                                        OCR).
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS5&nbsp;:&nbsp;</strong>
                                                        AS, A2 & A Level - Paper 1, 2 & 3 (AQA, Pearson, Eduqas, OCR).
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="thumbnail">
                                                <img src="{{ asset('assets/images/course/science_course.png') }}" style="height: 200px!important; border-radius: 10px;">
                                            </div>
                                        </div>
                                        <a class="rbt-btn btn-gradient btn-sm mt--30 w-100"></a>

                                        <div class="rbt-course-feature-inner mt-4 text-start">
                                            <h4 style="color: rgb(255, 37, 37) !important;"><strong>*** Grab Your Free
                                                    Assessment Test Today***</strong></h4>
                                        </div>

                                        <div class="inner mt-4">
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

                                        <div class="inner">
                                            <div class="content">
                                                <h4 class="title">Our Specialties:</h4>
                                                <ul class="rbt-list-style-1">
                                                    <li><i class="feather-check"></i>Fully Focused On UK National
                                                        Curriculum
                                                    </li>
                                                    <li><i class="feather-check"></i>Fully UK Qualified Teachers</li>
                                                    <li><i class="feather-check"></i>Flexible Timing</li>
                                                    <li><i class="feather-check"></i>Baseline, Progressive, Year-End
                                                        Assessments</li>
                                                    <li><i class="feather-check"></i>6 Weeks Review Meeting with Parents
                                                    </li>
                                                    <li><i class="feather-check"></i>1:1 Problem Solving Sessions</li>
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
                                <!-- End Single Course  -->

                            </div>
                        </div>

                        <div class="tab-pane fade" id="chemistry" role="tabpanel" aria-labelledby="chemistry-tab">
                            <div class="rbt-course-grid-column">

                                <!-- Start Single Course  -->
                                <div class="col-xl-12 col-md-12 col-sm-12 col-12 mt--30">
                                    <div
                                        class="rbt-service rbt-service-2 variation-2 color-box bg-color-white-off radius-10">
                                        <div class="rbt-course-feature-inner radius-10">
                                            <div class="section-title">
                                                <h3 class="title mb--20">Welcome to Our Science Courses at CCE (KS1 -
                                                    KS5)! </h3>
                                            </div>
                                            <div>
                                                At <span class="txt-highlight">Croydon College of Excellence
                                                    (CCE)</span>, we are committed to making
                                                <span class="txt-highlight">Chemistry accessible,
                                                    engaging, and enjoyable</span> for students with <span
                                                    class="txt-highlight">Special Educational Needs
                                                    (SEN)</span>. Our tailored
                                                approach ensures that every learner receives <span
                                                    class="txt-highlight">personalized</span> support to
                                                build confidence and
                                                succeed in science.
                                            </div>
                                        </div>
                                        <div class="rbt-course-feature-inner mt-4 text-start">
                                            <strong>
                                                ***Supporting Every Learner to Explore, Experiment & Excel in
                                                Chemistry!***
                                            </strong>
                                        </div>

                                        <div class="inner mt--20">
                                            <div class="content">
                                                <h4 class="title">We Focus On UK National Curriculum:</h4>
                                                <ul class="rbt-list-style-1">
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS1&nbsp;:&nbsp;</strong>
                                                        Everyday Materials & Their Properties, Changing Materials,
                                                        Grouping & Classifying Materials, Scientific Investigation &
                                                        Experiments.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS2&nbsp;:&nbsp;</strong>
                                                        Properties and Changes of Materials, States of Matter, Mixtures
                                                        and Solutions, Chemical Reactions, and Introduction to the
                                                        Periodic Table (Year 6).
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS3&nbsp;:&nbsp;</strong>
                                                        Particles and Their Properties, Atomic Structure and the
                                                        Periodic Table, Chemical Reactions, Acids & Alkalis, The Earth's
                                                        Resources, Energy & Chemical Reactions, and Separation
                                                        Techniques.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS4&nbsp;:&nbsp;</strong>
                                                        GCSE (Years 10 & 11) - Paper 1 & Paper 2 (AQA, Pearson, Eduqas,
                                                        OCR).
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS5&nbsp;:&nbsp;</strong>
                                                        AS, A2 & A Level - Paper 1, 2 & 3 (AQA, Pearson, Eduqas, OCR).
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="thumbnail">
                                                <img src="{{ asset('assets/images/course/science_course.png') }}" style="height: 200px!important; border-radius: 10px;">
                                            </div>
                                        </div>
                                        <a class="rbt-btn btn-gradient btn-sm mt--30 w-100"></a>

                                        <div class="rbt-course-feature-inner mt-4 text-start">
                                            <h4 style="color: rgb(255, 37, 37) !important;"><strong>*** Grab Your Free
                                                    Assessment Test Today***</strong></h4>
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
                                                    <li><i class="feather-check"></i>Fully Focused On UK National
                                                        Curriculum
                                                    </li>
                                                    <li><i class="feather-check"></i>Fully UK Qualified Teachers</li>
                                                    <li><i class="feather-check"></i>Flexible Timing</li>
                                                    <li><i class="feather-check"></i>Baseline, Progressive, Year-End
                                                        Assessments</li>
                                                    <li><i class="feather-check"></i>6 Weeks Review Meeting with Parents
                                                    </li>
                                                    <li><i class="feather-check"></i>1:1 Problem Solving Sessions</li>
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
                                <!-- End Single Course  -->

                            </div>
                        </div>

                        <div class="tab-pane fade" id="biology" role="tabpanel" aria-labelledby="biology-tab">
                            <div class="rbt-course-grid-column">

                                <!-- Start Single Course  -->
                                <div class="col-xl-12 col-md-12 col-sm-12 col-12 mt--30">
                                    <div
                                        class="rbt-service rbt-service-2 variation-2 color-box bg-color-white-off radius-10">
                                        <div class="rbt-course-feature-inner radius-10">
                                            <div class="section-title">
                                                <h3 class="title mb--20">Welcome to Our Science Courses at CCE (KS1 -
                                                    KS5)! </h3>
                                            </div>
                                            <div>
                                                At <span class="txt-highlight">Croydon College of Excellence
                                                    (CCE)</span>, we believe that <span class="txt-highlight">every
                                                    student
                                                    deserves the opportunity to thrive in Biology</span>, regardless of
                                                their learning
                                                challenges. <span class="txt-highlight">Our Special
                                                    Educational Needs (SEN) Biology Courses</span> provide <span
                                                    class="txt-highlight">structured, engaging,
                                                    and supportive</span>
                                                lessons tailored to individual needs.
                                            </div>
                                        </div>
                                        <div class="rbt-course-feature-inner mt-4 text-start txt-red">
                                            <strong>
                                                ***Helping Every Learner Discover, Understand & Succeed in Biology!***
                                            </strong>
                                        </div>

                                        <div class="inner mt--20">
                                            <div class="content">
                                                <h4 class="title">We Focus On UK National Curriculum:</h4>
                                                <ul class="rbt-list-style-1">
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS1&nbsp;:&nbsp;</strong>
                                                        Living Things and Their Habitats, Animals (Including Humans),
                                                        Plants & Seasonal Changes.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS2&nbsp;:&nbsp;</strong>
                                                        Living Things and Their Habitats, Animals (Including Humans),
                                                        Plants & Seasonal Changes, Life Cycles & Evolution, and
                                                        Inheritance (Year 6).
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS3&nbsp;:&nbsp;</strong>
                                                        Cells and Organization, The Human Body, Health and Disease,
                                                        Reproduction and Inheritance, Ecology and the Environment, Plant
                                                        Biology and Photosynthesis, Evolution and Adaptation.
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS4&nbsp;:&nbsp;</strong>
                                                        GCSE (Years 10 & 11) - Paper 1 & Paper 2 (AQA, Pearson, Eduqas,
                                                        OCR).
                                                    </li>
                                                    <li>
                                                        <i class="feather-check"></i>
                                                        <strong>KS5&nbsp;:&nbsp;</strong>
                                                        AS, A2 & A Level - Paper 1, 2 & 3 (AQA, Pearson, Eduqas, OCR).
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="thumbnail">
                                                <img src="{{ asset('assets/images/course/science_course.png') }}" style="height: 200px!important; border-radius: 10px;">
                                            </div>
                                        </div>
                                        <a class="rbt-btn btn-gradient btn-sm mt--30 w-100"></a>

                                        <div class="rbt-course-feature-inner mt-4 text-start">
                                            <h4 style="color: rgb(255, 37, 37) !important;"><strong>*** Grab Your Free
                                                    Assessment Test Today***</strong></h4>
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
                                                    <li><i class="feather-check"></i>Fully Focused On UK National
                                                        Curriculum
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
                                <!-- End Single Course  -->

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