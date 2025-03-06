<!-- resources/views/website/pages/contact_us.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Contact Details Start -->
<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center mb--60">
                    <h2 class="title">Contact Us</h2>
                </div>
            </div>
        </div>
        <div class="row g-5">
            <div class="col-lg-4 col-md-6 col-sm-6 col-12 sal-animate" data-sal="slide-up" data-sal-delay="150"
                data-sal-duration="800">
                <div class="rbt-address">
                    <div class="icon">
                        <i class="feather-headphones"></i>
                    </div>
                    <div class="inner">
                        <h4 class="title">Phone Number</h4>
                        <p><a href="tel:+447404797808">+44 7404 797808</a></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 col-12 sal-animate" data-sal="slide-up" data-sal-delay="200"
                data-sal-duration="800">
                <div class="rbt-address">
                    <div class="icon">
                        <i class="feather-mail"></i>
                    </div>
                    <div class="inner">
                        <h4 class="title">Email Address</h4>
                        <p>
                            <a
                                href="mailto:info@croydoncollegeofexcellence.co.uk">info@croydoncollegeofexcellence.co.uk</a>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 col-12 sal-animate" data-sal="slide-up" data-sal-delay="250"
                data-sal-duration="800">
                <div class="rbt-address">
                    <div class="icon">
                        <i class="feather-map-pin"></i>
                    </div>
                    <div class="inner">
                        <h4 class="title">Location</h4>
                        <p>47 Old Town, Croydon, London, CR0 1AU</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Contact Details End -->

<!-- Contact Form Start -->
<div class="rbt-contact-address mt--40">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6 pt--60">
                <div class="thumbnail">
                    <img class="w-100 radius-6" src="{{ asset('assets/images/about/contact.jpg') }}">
                </div>
            </div>

            <div class="col-lg-6">
                <div class="rbt-contact-form contact-form-style-1 max-width-auto">
                    <div class="section-title text-start">
                        <span class="subtitle bg-primary-opacity">EDUCATION FOR EVERYONE</span>
                    </div>
                    <h3 class="title">Contact With Us</h3>

                    @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form id="contact_form" action="{{ route('contact.send') }}" method="POST" class="row row--15">
                        @csrf

                        <div class="col-lg-12">
                            <div class="form-group">
                                <input id="contact_name" type="text" name="name" required>
                                <label for="contact_name">Full Name</label>
                                <span class="focus-border"></span>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <input id="contact_email" type="email" name="email" required>
                                <label for="contact_email">Email</label>
                                <span class="focus-border"></span>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <input id="contact_phone" type="text" name="phone" required>
                                <label for="contact_email">Phone</label>
                                <span class="focus-border"></span>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <input id="contact_subject" type="text" name="subject" required>
                                <label for="contact_subject">Your Subject</label>
                                <span class="focus-border"></span>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="form-group">
                                <textarea id="contact_message" name="message" required></textarea>
                                <label for="contact_message">Message</label>
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
</div>
<!-- Contact Form End -->

<!-- Google Maps Start -->
<div class="rbt-google-map bg-color-white rbt-section-gapTop" style="display: flex; justify-content: center;">
    <iframe
        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d993.4627342015637!2d-0.1058134!3d51.3704279!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x487607382c905237%3A0xc1e1b71846fbe68e!2s47%20Old%20Town%2C%20Croydon%20CR0%201AU%2C%20UK!5e0!3m2!1sen!2sus!4v1706878952045"
        height="600" style="border:0; width: 70%; border-radius: 10px;" allowfullscreen="" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"></iframe>
</div>
<!-- Google Maps End -->

<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection