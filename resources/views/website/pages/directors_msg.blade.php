<!-- resources/views/website/pages/directors_msg.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="row rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="col-lg-12">
        <div class="section-title text-center mb--20">
            <h2 class="title">Director's Message</h2>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Director's Message Start -->
<div class="rbt-split-area bg-color-white overflow-hidden mt--20 container-fluid ps-4"
    style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;">

    <div class="wrapper">
        <div class="rbt-splite-style">
            <div class="split-wrapper">
                <div class="row g-4 align-items-center">
                    <!-- Image Section -->
                    <div class="col-md-5 col-lg-4 text-center text-md-start">
                        <div class="thumbnail image-left-content">
                            <img src="{{ asset('assets/images/team/sajib_professional.jpg') }}" alt="Director" class="img-fluid">
                        </div>
                    </div>

                    <!-- Text Content Section -->
                    <div class="col-md-7 col-lg-8">
                        <div class="split-inner">
                            <h3 class="theme-gradient text-center text-md-start">
                                Welcome Message from the Director
                            </h3>
                            <div class="text-black" style="text-align: justify;">
                                <p class="text-black"><strong>Dear Students,</strong></p>
                                <p class="description text-black">
                                    It is with great excitement and pride that I welcome you to the Croydon College of
                                    Excellence in Croydon, London. As the Director, I am thrilled to have
                                    you as part of our dynamic community, where we are committed to helping you achieve
                                    excellence in everything you do.
                                </p>
                                <p class="description text-black">
                                    At Croydon College of Excellence, we believe that education is not just about
                                    academics; it's about personal growth, developing key skills, and building the
                                    confidence needed to succeed in the world beyond the classroom. Whether you are here
                                    to pursue your GCSE, A-levels, vocational qualifications, lifelong learning, or
                                    developing skills across KS1-KS3, we are here to support you every step of the way.
                                </p>
                                <p class="description text-black">
                                    I am excited about the journey ahead and am confident that you will find your time
                                    at Croydon College of Excellence both challenging and rewarding. Our focus is on
                                    your success, and we are here to provide you with the tools, resources, and support
                                    you need to thrive.
                                </p>
                                <p class="description text-black">
                                    Thank you for choosing us as your educational home. Together, we will create a
                                    foundation for your future and help you achieve excellence.
                                </p>
                                <p class="description text-black">
                                    Welcome to Croydon College of Excellence, where your success begins!
                                </p>
                                <div class="text-black">
                                    <p class="my-1">Warm regards,</p>
                                    <p class="my-1">Md Sajib Miah</p>
                                    <p class="my-1"><strong>Director</strong></p>
                                    <p class="my-1"><strong>Croydon College of Excellence</strong>, Croydon, London</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<!-- Director's Message End -->

<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection