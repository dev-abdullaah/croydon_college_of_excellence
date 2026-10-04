<!-- Mobile Menu Section Start-->
<div class="popup-mobile-menu">
    <div class="inner-wrapper">
        <div class="inner-top">
            <div class="content">
                <div class="logo">
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
                <div class="rbt-btn-close">
                    <button class="close-button rbt-round-btn" aria-label="Close Mobile Menu">
                        <svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Fast Track Mobile Actions -->
        <div class="mobile-quick-actions">
            <a href="{{ route('courses.index') }}" class="mobile-quick-btn mobile-quick-btn-primary">
                <i class="feather-book-open"></i>
                <span>Life in the UK Lessons &amp; Tests</span>
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="mobile-quick-btn mobile-quick-btn-secondary">
                    <i class="feather-user"></i>
                    <span>Go to My Learning Account</span>
                </a>
            @else
                <a href="{{ route('login') }}" class="mobile-quick-btn mobile-quick-btn-secondary">
                    <i class="feather-log-in"></i>
                    <span>Sign In</span>
                </a>
            @endauth
        </div>

        <nav class="mainmenu-nav">
            <ul class="mainmenu">
                <li><a href="{{ url('/') }}">HOME</a></li>
                <li class="has-dropdown has-menu-child-item">
                    <a>ABOUT US <i class="feather-chevron-down"></i></a>
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
                    <a href="{{ route('courses.index') }}">LIFE IN THE UK <i class="feather-chevron-down"></i></a>
                    <ul class="submenu">
                        <li><a href="{{ route('courses.show', 'life-in-the-uk-course') }}">📚 LIFE IN THE UK COURSE &mdash; &pound;99</a></li>
                        <li><a href="{{ route('courses.show', '24-mock-tests') }}">⌚ 24 MOCK TESTS &mdash; &pound;49</a></li>
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="text-primary fw-bold">👤 MY ACCOUNT</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="text-primary fw-bold">🔑 SIGN IN</a></li>
                        @endauth
                    </ul>
                </li>
                <li><a href="{{ url('/contact-us') }}">CONTACT US</a></li>
                <li><a href="{{ url('/gallery') }}">GALLERY</a></li>
            </ul>
        </nav>

        <div class="mobile-menu-bottom">
            <div class="rbt-btn-wrapper mb--15">
                <a class="rbt-btn btn-border-gradient radius-round btn-sm hover-transform-none w-100 justify-content-center text-center"
                    href="{{ route('courses.index') }}">
                    <span>Life in the UK Courses</span>
                </a>
            </div>

            @auth
                <div class="rbt-btn-wrapper mb--20">
                    <a class="rbt-btn btn-white radius-round btn-sm hover-transform-none w-100 justify-content-center text-center"
                        href="{{ route('dashboard') }}">
                        <span>My Account</span>
                    </a>
                </div>
            @else
                <div class="rbt-btn-wrapper mb--20">
                    <a class="rbt-btn btn-white radius-round btn-sm hover-transform-none w-100 justify-content-center text-center"
                        href="{{ route('login') }}">
                        <span>Sign In</span>
                    </a>
                </div>
            @endauth

            <div class="social-share-wrapper">
                <span class="rbt-short-title d-block">Stay Connected With Us</span>
                <ul class="social-icon social-default with-gradient justify-content-start mt--20">
                    <li class="list-inline-item">
                        <a href="https://www.facebook.com/people/Croydon-College-of-Excellence/61572423543580/" rel="noopener noreferrer" target="_blank">
                            <img src="{{ asset('assets/images/icons/facebook.png') }}" width="20" height="20" alt="Facebook">
                        </a>
                    </li>
                    <li class="list-inline-item">
                        <a href="https://www.instagram.com/ccollegeofexcellence/profilecard/" rel="noopener noreferrer" target="_blank">
                            <img src="{{ asset('assets/images/icons/instagram.png') }}" width="18" height="18" alt="Instagram">
                        </a>
                    </li>
                    <li class="list-inline-item">
                        <a href="https://www.linkedin.com/company/croydon-college-of-excellence/" rel="noopener noreferrer" target="_blank">
                            <img src="{{ asset('assets/images/icons/linkedIn.png') }}" width="18" height="18" alt="LinkedIn">
                        </a>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</div>
<!-- Mobile Menu Section End-->