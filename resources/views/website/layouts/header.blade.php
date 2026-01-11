<!-- Header Area Start-->
<header class="rbt-header rbt-header-10">
    <div class="rbt-sticky-placeholder"></div>

    <!-- Start Header Top -->
    <div
        class="rbt-header-top rbt-header-top-1 header-space-betwween bg-not-transparent bg-color-darker top-expended-activation">
        <div class="container-fluid">
            <div class="row align-items-center justify-content-center text-center">
                <!-- Left Section: Email and Phone Start -->
                <div class="col-12 col-md-auto d-flex flex-wrap gap-3 justify-content-center">
                    <div class="rbt-header-content">
                        <div class="header-info">
                            <ul class="rbt-information-list text-center text-md-start">
                                <li>
                                    <a href="mailto:info@croydoncollegeofexcellence.co.uk" class="top_header_font">
                                        <i class="fa-solid fa-envelope"></i>
                                        info@croydoncollegeofexcellence.co.uk
                                    </a>
                                </li>
                                <li>
                                    <a href="tel:+447404797808" class="top_header_font">
                                        <i class="fa-solid fa-phone"></i>
                                        +44 7404 797808
                                        <br>
                                        +44 7405 073764
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <!-- Left Section: Email and Phone End -->

                <!-- Center Section: Address Start -->
                <div class="col-12 col-md text-center d-flex justify-content-center center-address">
                    <div class="w-100 w-md-auto text-nowrap">
                        <span class="news-text d-flex align-items-center justify-content-center top_header_font">
                            <i class="fa-solid fa-location-dot"></i>
                            &nbsp;
                            47 Old Town, Croydon, London, CR0 1AU
                        </span>
                    </div>
                </div>
                <!-- Center Section: Address End -->

                <!-- Right Section: Social Media Links Start -->
                <div class="col-12 col-md-auto text-center social-icons">
                    <ul class="social-share-transparent list-inline d-flex justify-content-center gap-2 m-0">
                        <li class="list-inline-item">
                            <a href="https://www.facebook.com/people/Croydon-College-of-Excellence/61572423543580/">
                                <img src="{{ asset('assets/images/icons/facebook.png') }}" width="18" height="18">
                            </a>
                        </li>
                        <li class="list-inline-item">
                            <a href="https://www.instagram.com/ccollegeofexcellence/profilecard/">
                                <img src="{{ asset('assets/images/icons/instagram.png') }}" width="18" height="18">
                            </a>
                        </li>
                        <li class="list-inline-item">
                            <a href="https://www.linkedin.com/company/croydon-college-of-excellence/">
                                <img src="{{ asset('assets/images/icons/linkedIn.png') }}" width="18" height="18">
                            </a>
                        </li>
                    </ul>
                </div>
                <!-- Right Section: Social Media Links End -->
            </div>
        </div>
    </div>
    <!-- End Header Top -->

    <!-- Navbar Start-->
    <div class="rbt-header-wrapper header-space-betwween header-sticky">
        <div class="container-fluid">
            <div class="mainbar-row rbt-navigation-center align-items-center">
                <div class="header-left rbt-header-content">
                    <div class="header-info">
                        <div class="logo logo-dark">
                            <a href="{{ url('/') }}">
                                <img src="{{ asset('assets/images/logo/logo.png') }}" alt="Logo">
                            </a>
                        </div>

                        <div class="logo d-none logo-light">
                            <a href="{{ url('/') }}">
                                <img src="{{ asset('assets/images/logo/logo.png') }}" alt="Logo">
                            </a>
                        </div>
                    </div>
                </div>

                <div class="rbt-main-navigation d-none d-xl-block">
                    <nav class="mainmenu-nav">
                        <ul class="mainmenu">
                            <li><a href="{{ url('/') }}">HOME</a></li>
                            <li class="has-dropdown has-menu-child-item">
                                <a>ABOUT US
                                    <i class="feather-chevron-down"></i>
                                </a>
                                <ul class="submenu">
                                    <li><a href="{{ url('/director-message') }}">DIRECTOR'S MESSAGE</a></li>
                                    <li><a href="{{ url('/our-team') }}">OUR TEAM</a></li>
                                    <li><a href="{{ url('/our-mission') }}">OUR MISSION & VISION</a></li>
                                    <li><a href="{{ url('/our-policy') }}">OUR POLICIES & PROCEDURES</a></li>
                                    <li><a href="{{ url('/become-tutor') }}">BECOME A TUTOR</a></li>
                                </ul>
                            </li>
                            <li><a href="{{ url('/courses-regular') }}">COURSES</a></li>
                            <li><a href="{{ url('/courses-send') }}">SEND COURSES</a></li>
                            <li><a href="{{ url('/contact-us') }}">CONTACT US</a></li>
                            <li><a href="{{ url('/gallery') }}">GALLERY</a></li>
                        </ul>
                    </nav>
                </div>

                <div class="header-right">

                    <div class="rbt-btn-wrapper d-none d-xl-block">
                        <a class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center"
                            href="{{ url('/enroll-now') }}">
                            <span>Enroll Now</span>
                        </a>
                    </div>

                    <!-- Start Mobile-Menu-Bar -->
                    <div class="mobile-menu-bar d-block d-xl-none">
                        <div class="hamberger">
                            <button class="hamberger-button rbt-round-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <line x1="3" y1="12" x2="21" y2="12"></line>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <line x1="3" y1="18" x2="21" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Start Mobile-Menu-Bar -->

                </div>

            </div>
        </div>
    </div>
    <!-- Navbar End-->

</header>
<!-- Header Area End-->