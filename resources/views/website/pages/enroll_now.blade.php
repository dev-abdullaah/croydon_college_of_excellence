<!-- resources/views/website/pages/enroll_now.blade.php -->
@extends('website.layouts.master')

@section('content')

<!-- Page Heading Start -->
<div class="row rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="col-lg-12">
        <div class="section-title text-center mb--20">
            <h2 class="title">Enroll Now</h2>
        </div>
    </div>
</div>
<!-- Page Heading End -->

<!-- Enroll Form Start -->
<div class="checkout_area bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row g-5 checkout-form">
            <div class="col-lg-12">

                <div class="checkout-content-wrapper">


                    @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    <form id="enroll-form" method="POST" action="{{ route('enroll.send') }}">

                        @csrf
                        <!-- Admission Form Start -->
                        <div id="admission_form" class="container">
                            <h3>Enrollment Form</h3>
                            <hr class="mb--30">
                            <div class="row">
                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_full_name">Full Name<span class="text-danger">*</span></label>
                                    <input type="text" id="txt_full_name" name="full_name" class="form-control" placeholder="First Name" required>
                                </div>

                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_dob">Date of Birth<span class="text-danger">*</span></label>
                                    <input type="date" id="txt_dob" name="dob" class="form-control" required>
                                </div>

                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_gender">Gender<span class="text-danger">*</span></label>
                                    <div class="rbt-modern-select bg-transparent height-45">
                                        <select id="txt_gender" name="gender" class="form-control w-100" required>
                                            <option>Male</option>
                                            <option>Female</option>
                                            <option>Non-Binary</option>
                                            <option>Prefer Not to Say</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_email">Email Address<span class="text-danger">*</span></label>
                                    <input type="email" id="txt_email" name="email" class="form-control" placeholder="Email Address" required>
                                </div>

                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_phone">Phone no<span class="text-danger">*</span></label>
                                    <input type="tel" id="txt_phone" name="phone" class="form-control" placeholder="Enter Phone Number" required>
                                </div>

                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_guardian_name">Parent/Guardian Full Name (Not Mandatory for 18+)</label>
                                    <input type="text" id="txt_guardian_name" name="guardian_name" class="form-control" placeholder="Guardian Name">
                                </div>
                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_guardian_contact">Parents/Guardian Contact Number</label>
                                    <input type="text" id="txt_guardian_contact" name="guardian_contact" class="form-control" placeholder="Guardian's Contact">
                                </div>

                                <div class="col-md-6 col-12 mb--20">
                                    <label for="txt_address">Address<span class="text-danger">*</span></label>
                                    <input type="text" id="txt_address" name="address" class="form-control" placeholder="Address" required>
                                </div>

                                <!-- Subjects Selection Start -->
                                <div id="subject_selection" class="container mb-3">
                                    <label for="subjects" class="form-label">Choose subject you would like to study.<span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_english" name="subjects[]" value="English">
                                                <label class="form-check-label" for="subject_english">English</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_maths" name="subjects[]" value="Maths">
                                                <label class="form-check-label" for="subject_maths">Maths</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_science" name="subjects[]" value="Science">
                                                <label class="form-check-label" for="subject_science">Science</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_11plus" name="subjects[]" value="11+ Exam Preparation">
                                                <label class="form-check-label" for="subject_11plus">11+ Exam Preparation</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_sats" name="subjects[]" value="SATs/Year 6">
                                                <label class="form-check-label" for="subject_sats">SATs/Year 6</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_functional_skills" name="subjects[]" value="Functional Skills">
                                                <label class="form-check-label" for="subject_functional_skills">Functional Skills</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_esol" name="subjects[]" value="ESOL">
                                                <label class="form-check-label" for="subject_esol">ESOL</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_literacy_numeracy" name="subjects[]" value="Literacy & Numeracy">
                                                <label class="form-check-label" for="subject_literacy_numeracy">Literacy & Numeracy</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_humanities" name="subjects[]" value="Humanities">
                                                <label class="form-check-label" for="subject_humanities">Humanities</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_ielts_ukvi" name="subjects[]" value="IELTS/UKVI Life Skills">
                                                <label class="form-check-label" for="subject_ielts_ukvi">IELTS/UKVI Life Skills</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_business" name="subjects[]" value="Business">
                                                <label class="form-check-label" for="subject_business">Business</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_ict" name="subjects[]" value="ICT">
                                                <label class="form-check-label" for="subject_ict">ICT</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_life_skills" name="subjects[]" value="Life Skills">
                                                <label class="form-check-label" for="subject_life_skills">Life Skills</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_music_art" name="subjects[]" value="Music & Art">
                                                <label class="form-check-label" for="subject_music_art">Music & Art</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="subject_life_in_uk" name="subjects[]" value="Life In The UK">
                                                <label class="form-check-label" for="subject_life_in_uk">Life In The UK</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <!-- Subjects Selection End -->

                            </div>
                        </div>

                        <div class="rbt-separator-mid mt--20 mb--20">
                            <div class="container">
                                <hr class="rbt-separator m-0">
                            </div>
                        </div>

                        <div class="col-lg-12 d-flex justify-content-center">
                            <button type="submit" class="rbt-btn btn-border-gradient radius-round btn-sm w-30 justify-content-center text-center">
                                <span>Submit</span>
                            </button>
                        </div>
                        <!-- Admission Form End -->
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Enroll Form End -->



<div class="rbt-separator-mid mt--60">
    <div class="container">
        <hr class="rbt-separator m-0">
    </div>
</div>

@endsection