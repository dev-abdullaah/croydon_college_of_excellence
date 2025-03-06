<!-- resources/views/website/pages/our_team.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="row rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="col-lg-12">
        <div class="section-title text-center mb--20">
            <h2 class="title">Our Team</h2>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Our Team Start  -->
<div class="rbt-team-area bg-color-white rbt-section-gapBottom mt--40"
    style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div class="container">

        <div class="row g-5 mb--30">
            <div class="section-title text-center">
                <p class="description mt--20">
                    <span class="txt-highlight">Our team is passionate, experienced, and dedicated to guiding students toward success.</span>
                </p>
            </div>
        </div>

        <div class="row g-5">

            <!-- Start Single Team  -->
            <div class="col-lg-12 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail">
                            <img src="{{ asset('assets/images/team/sajib_team.jpg') }}" style="width: 600px; height: 475px; display: block; margin: 0 auto;">
                        </div>

                        <div class="content">
                            <h4>Md Sajib Miah</h2>
                                <h6 class="subtitle theme-gradient">Director & SEN Lead</h6>
                                <span class="team-form">
                                    <span class="location">(EdD & QTLS On-going), MA, BA, DET & CPD Courses</span>
                                </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Single Team  -->

            <!-- Start Single Team  -->
            <div class="col-lg-4 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail"><img src="{{ asset('assets/images/team/imran.jpeg') }}">
                        </div>
                        <div class="content">
                            <h4>Shah E Imran</h4>
                            <h6 class="subtitle theme-gradient">Manager & HR Administrator</h6>
                            <span class="team-form">
                                <span class="location">Phd (Prospective), MA , BA & CPD Courses</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Single Team  -->

            <!-- Start Single Team  -->
            <div class="col-lg-4 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail"><img src="{{ asset('assets/images/team/musharraf.jpeg') }}">
                        </div>
                        <div class="content">
                            <h4>Musharraf Ashraf</h4>
                            <h6 class="subtitle theme-gradient">English Lead</h6>
                            <span class="team-form">
                                <span class="location">Mphil, MEd, MA TESOL, BA & CPD Courses</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Single Team  -->

            <!-- Start Single Team  -->
            <div class="col-lg-4 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail"><img src="{{ asset('assets/images/team/shahidullah.jpg') }}">
                        </div>
                        <div class="content">
                            <h4>Mohammad Shahidullah</h4>
                            <h6 class="subtitle theme-gradient">Humanities Lead</h6>
                            <span class="team-form">
                                <span class="location">MEd (On-going), MA, BA & CPD Courses</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Single Team  -->

            <!-- Start Single Team  -->
            <div class="col-lg-4 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail"><img src="{{ asset('assets/images/team/sahra.jpg') }}">
                        </div>
                        <div class="content">
                            <h4>Sahra Ibrahim</h4>
                            <h6 class="subtitle theme-gradient">Maths Tutor</h6>
                            <span class="team-form">
                                <span class="location">BA in Mathmatics, A Level, GCSE & CPD Courses</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Single Team  -->

            <!-- Start Single Team  -->
            <!-- <div class="col-lg-4 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail"><img src="{{ asset('assets/images/team/morsalin.jpeg') }}">
                        </div>
                        <div class="content">
                            <h4>Morshalin Rifat</h4>
                            <h6 class="subtitle theme-gradient">Life Skills Tutor</h6>
                            <span class="team-form">
                                <span class="location">MA, BA & CPD Courses</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div> -->
            <!-- End Single Team  -->

            <!-- Start Single Team  -->
            <div class="col-lg-4 col-md-6 col-12">
                <div class="rbt-team team-style-default style-three rbt-hover">
                    <div class="inner">
                        <div class="thumbnail"><img src="{{ asset('assets/images/team/rashedul.jpg') }}">
                        </div>
                        <div class="content">
                            <h4>Rashedul Islam</h4>
                            <h6 class="subtitle theme-gradient">Science Tutor</h6>
                            <span class="team-form">
                                <span class="location">MA Science, MBA, BBA, Dip. in ICT & CPD Courses</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Single Team  -->

        </div>
    </div>
</div>
<!-- Our Team End  -->

<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection