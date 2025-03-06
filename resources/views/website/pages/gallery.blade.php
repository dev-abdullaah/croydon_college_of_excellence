<!-- resources/views/website/pages/gallery.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="rbt-breadcrumb-default bg-gradient-9">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="breadcrumb-inner text-center mt--30 mb--60">
                    <h2 class="title">Gallery</h2>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Start Gallery Area -->
<div class="rbt-gallery-area">
    <div class="row g-0 parent-gallery-container">
        <a href="{{ asset('assets/images/course/english_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/english_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/math_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/math_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/science_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/science_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/exam_11.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/exam_11.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/sat_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/sat_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/functional_skill.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/functional_skill.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/esol_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/esol_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/literacy.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/literacy.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/business_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/business_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/life_skill_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/life_skill_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/humanity_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/humanity_course.png') }}" alt="Gallery Images">
            </div>
        </a>
        <a href="{{ asset('assets/images/course/ict_course.png') }}"
            class="child-gallery-single col-lg-2 col-md-4 col-sm-6 col-6">
            <div class="rbt-gallery">
                <img class="w-100" src="{{ asset('assets/images/course/ict_course.png') }}" alt="Gallery Images">
            </div>
        </a>
    </div>
</div>
<!-- End Gallery Area -->

<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection