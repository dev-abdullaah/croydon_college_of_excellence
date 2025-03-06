<!-- resources/views/website/pages/become_tutor.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="row rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="col-lg-12">
        <div class="section-title text-center mb--20">
            <h2 class="title">Become a Tutor</h2>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Start Tutor Area -->
<div class="rbt-about-area about-style-1 bg-color-white mt--40"
    style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div class="container">
        <div class="col-lg-12">
            <div class="rbt-contact-form contact-form-style-1 max-width-auto">
                <h3>Tutor Registration</h3>
                <hr class="mb--30">

                @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form id="tutor_registration_form" action="{{ route('tutor.send') }}" method="POST" class="row row--15">
                    @csrf

                    <div class="col-lg-12">
                        <div class="form-group">
                            <input id="tutor_full_name" type="text" name="full_name" required>
                            <label for="tutor_full_name">Full Name</label>
                            <span class="focus-border"></span>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group">
                            <input id="tutor_qualification" type="text" name="qualification" required>
                            <label for="tutor_qualification">Highest Educational Qualification</label>
                            <span class="focus-border"></span>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group">
                            <input id="tutor_subject" type="text" name="subject" required>
                            <label for="tutor_subject">Interested Subject</label>
                            <span class="focus-border"></span>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group">
                            <input id="tutor_email" type="email" name="email" required>
                            <label for="tutor_email">Email</label>
                            <span class="focus-border"></span>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group">
                            <input id="tutor_phone" type="text" name="phone" required>
                            <label for="tutor_phone">Phone Number</label>
                            <span class="focus-border"></span>
                        </div>
                    </div>

                    <div class="col-lg-12">
                        <div class="form-group">
                            <textarea id="tutor_comments" name="comments"></textarea>
                            <label for="tutor_comments">Further Comment</label>
                            <span class="focus-border"></span>
                        </div>
                    </div>

                    <div class="col-lg-12 d-flex justify-content-center">
                        <button type="submit" class="rbt-btn btn-border-gradient radius-round btn-sm w-30 justify-content-center text-center">
                            <span>Submit</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- End Tutor Area -->

<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection