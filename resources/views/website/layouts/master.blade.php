<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>@yield('title', 'Croydon College of Excellence')</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="A leading educational institution in Croydon, London, committed to academic excellence, personal growth, and future success.">

    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph -->
    <meta property="og:title" content="Croydon College of Excellence" />
    <meta property="og:description" content="A leading educational institution in Croydon, London, committed to academic excellence, personal growth, and future success." />
    <meta property="og:type" content="website" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ asset('assets/images/logo/og-image.jpg') }}" />
    <meta property="og:image:type" content="image/jpeg" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="Croydon College of Excellence campus and students" />
    <meta property="og:site_name" content="Croydon College of Excellence" />

    <!-- Twitter / X -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Croydon College of Excellence" />
    <meta name="twitter:description" content="Empowering students in Croydon, London through quality education and lifelong learning." />
    <meta name="twitter:image" content="{{ asset('assets/images/logo/og-image.jpg') }}" />

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.png') }}">

    <!-- CSS================================= -->
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/slick.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/slick-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/sal.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/swiper.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/odometer.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/animation.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/bootstrap-select.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/jquery-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/magnigy-popup.min.css') }}">
</head>

<body class="rbt-header-sticky">

    <a href="#main-content" class="sr-only sr-only-focusable">Skip to content</a>

    <div id="my_switcher" class="my_switcher">
        <ul>
            <li>
                <button type="button" data-theme="light" class="setColor light" aria-label="Switch to light mode">
                    <img src="{{ asset('assets/images/about/sun-01.svg') }}" alt="" aria-hidden="true"><span>Light</span>
                </button>
            </li>
            <li>
                <button type="button" data-theme="dark" class="setColor dark" aria-label="Switch to dark mode">
                    <img src="{{ asset('assets/images/about/vector.svg') }}" alt="" aria-hidden="true"><span>Dark</span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Header Area Start-->
    @include('website.layouts.header')
    <!-- Header Area End-->

    <!-- Mobile Menu Section Start-->
    @include('website.layouts.mobile_menu')
    <!-- Mobile Menu Section End-->

    {{--
        Session feedback. Rendered here, after the header and menu, so that
        every page shows it: a page that redirects back to itself and stays
        silent looks like a button that does nothing.

        It is emitted at the end of the body rather than in a slot in the page
        content, because the message is a fixed overlay and has no business
        being inside a container that also holds the page. Putting it in flow
        meant it sat flush under the header and pushed the page down for as
        long as it was on screen. The partial handles the "only if there is
        something to say" part, so no empty element is left behind.
    --}}
    @include('website.partials.flash')

    <main id="main-content">
        @yield('content')
    </main>
    <!-- End Page Container Area -->

    <!-- Start Footer aera -->
    @include('website.layouts.footer')
    <!-- End Footer aera -->


    <div class="rbt-progress-parent">
        <svg class="rbt-back-circle svg-inner" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>

    <!-- JS ==================== -->
    <!-- Modernizer JS -->
    <script src="{{ asset('assets/js/vendor/modernizr.min.js') }}" defer></script>
    <!-- jQuery JS -->
    <script src="{{ asset('assets/js/vendor/jquery.js') }}" defer></script>
    <!-- Bootstrap JS -->
    <script src="{{ asset('assets/js/vendor/bootstrap.min.js') }}" defer></script>
    <!-- sal.js -->
    <script src="{{ asset('assets/js/vendor/sal.js') }}" defer></script>
    <!-- Dark Mode Switcher -->
    <script src="{{ asset('assets/js/vendor/js.cookie.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/jquery.style.switcher.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/swiper.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/jquery-appear.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/odometer.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/backtotop.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/isotop.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/imageloaded.js') }}" defer></script>

    <script src="{{ asset('assets/js/vendor/wow.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/waypoint.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/easypie.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/text-type.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/jquery-one-page-nav.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/bootstrap-select.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/jquery-ui.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/magnify-popup.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/paralax-scroll.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/paralax.min.js') }}" defer></script>
    <script src="{{ asset('assets/js/vendor/countdown.js') }}" defer></script>

    @vite('resources/js/app.js')

    @stack('scripts')

</body>

</html>