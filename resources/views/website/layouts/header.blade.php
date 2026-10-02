<!-- Header Area Start-->
<header class="rbt-header rbt-header-10">
    <div class="rbt-sticky-placeholder"></div>

    <!-- Start Header Top -->
    <div class="rbt-header-top rbt-header-top-1 header-space-betwween bg-not-transparent">
        <div class="container-fluid">
            <div class="top-header-wrapper">
                <!-- Left Section: Email and Phone Start -->
                <div class="top-header-left">
                    <ul class="top-header-list">
                        <li>
                            <a href="mailto:info@croydoncollegeofexcellence.co.uk" class="top_header_font">
                                <i class="fa-solid fa-envelope"></i>
                                <span>info@croydoncollegeofexcellence.co.uk</span>
                            </a>
                        </li>
                        <li class="top-header-divider" aria-hidden="true"></li>
                        <li>
                            <a href="tel:+447405073764" class="top_header_font">
                                <i class="fa-solid fa-phone"></i>
                                <span>+44 7405 073764</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <!-- Left Section: Email and Phone End -->

                <!-- Center Section: Address Start -->
                <div class="top-header-center">
                    <span class="top-header-address top_header_font">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>47 Old Town, Croydon, London, CR0 1AU</span>
                    </span>
                </div>
                <!-- Center Section: Address End -->

                <!-- Right Section: Social Media Links Start -->
                <div class="top-header-right">
                    <ul class="social-share-transparent list-inline d-flex align-items-center justify-content-center gap-2 m-0">
                        <li class="list-inline-item">
                            <a href="https://www.facebook.com/people/Croydon-College-of-Excellence/61572423543580/" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                                <img src="{{ asset('assets/images/icons/facebook.png') }}" width="18" height="18" alt="Facebook">
                            </a>
                        </li>
                        <li class="list-inline-item">
                            <a href="https://www.instagram.com/ccollegeofexcellence/profilecard/" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                                <img src="{{ asset('assets/images/icons/instagram.png') }}" width="18" height="18" alt="Instagram">
                            </a>
                        </li>
                        <li class="list-inline-item">
                            <a href="https://www.linkedin.com/company/croydon-college-of-excellence/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                                <img src="{{ asset('assets/images/icons/linkedIn.png') }}" width="18" height="18" alt="LinkedIn">
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
                            <li class="has-dropdown has-menu-child-item">
                                <a href="{{ route('courses.index') }}">LIFE IN THE UK
                                    <i class="feather-chevron-down"></i>
                                </a>
                                <ul class="submenu submenu-rich">
                                    <li>
                                        <a href="{{ route('courses.show', 'life-in-the-uk-course') }}">
                                            <span class="submenu-rich-title">
                                                📚 Life in the UK Course &mdash; &pound;99
                                            </span>
                                            <span class="submenu-rich-desc">10 structured lessons, study cards, knowledge checks &amp; 6 mock tests</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('courses.show', '24-mock-tests') }}">
                                            <span class="submenu-rich-title">
                                                ⌚ 24 Mock Tests Package &mdash; &pound;49
                                            </span>
                                            <span class="submenu-rich-desc">576 exam-standard questions, 45-min timer &amp; instant answer keys</span>
                                        </a>
                                    </li>
                                    <li class="dropdown-divider my-2"></li>
                                    @auth
                                        <li>
                                            <a href="{{ route('dashboard') }}" class="text-primary fw-bold">
                                                <span class="submenu-rich-title text-primary">
                                                    👤 My Learning Dashboard
                                                </span>
                                                <span class="submenu-rich-desc">Access your lessons &amp; tests</span>
                                            </a>
                                        </li>
                                    @else
                                        <li>
                                            <a href="{{ route('login') }}" class="text-primary fw-bold">
                                                <span class="submenu-rich-title text-primary">
                                                    🔑 Sign In To Your Account
                                                </span>
                                                <span class="submenu-rich-desc">Sign in to continue your course &amp; tests</span>
                                            </a>
                                        </li>
                                    @endauth
                                </ul>
                            </li>
                            <li><a href="{{ url('/contact-us') }}">CONTACT US</a></li>
                            <li><a href="{{ url('/gallery') }}">GALLERY</a></li>
                        </ul>
                    </nav>
                </div>

                <div class="header-right">

                    <div class="header-actions-group d-none d-xl-flex">
                        @auth
                            <a class="rbt-btn btn-gradient btn-header-icon" href="{{ route('dashboard') }}">
                                <i class="feather-user"></i>
                                <span>My Account</span>
                            </a>
                        @else
                            <a class="rbt-btn btn-gradient btn-header-icon" href="{{ route('login') }}">
                                <i class="feather-log-in"></i>
                                <span>Sign In</span>
                            </a>
                        @endauth

                        <div class="rbt-btn-wrapper">
                            <a class="rbt-btn btn-border-gradient radius-round btn-header-course"
                                href="{{ route('courses.index') }}">
                                <span>Life in the UK Prep</span>
                            </a>
                        </div>
                    </div>

                    <!-- Mobile quick login button visible next to hamburger.
                         The label used to carry `d-none d-sm-inline`, so below
                         576px it collapsed to a bare icon with no name on it -
                         the one control in the bar nobody could identify. The
                         label now always shows; nowrap stops it wrapping when
                         the bar is tight. -->
                    <div class="d-flex align-items-center d-xl-none me-2">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rbt-btn btn-gradient btn-header-account btn-header-icon">
                                <i class="feather-user"></i>
                                <span>Account</span>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rbt-btn btn-gradient btn-header-account btn-header-icon">
                                <i class="feather-log-in"></i>
                                <span>Sign In</span>
                            </a>
                        @endauth
                    </div>

                    <!-- Start Mobile-Menu-Bar -->
                    <div class="mobile-menu-bar d-block d-xl-none">
                        <div class="hamberger">
                            <button class="hamberger-button rbt-round-btn" aria-label="Open Mobile Menu">
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