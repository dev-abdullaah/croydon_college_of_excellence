<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login | Croydon College of Excellence</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{ asset('admin-assets/media/logos/favicon.png') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <link href="{{ asset('admin-assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('admin-assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
</head>

<body id="kt_body" class="app-blank app-blank bgi-size-cover bgi-position-center bgi-no-repeat">
    <script>
        var defaultThemeMode = "light";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-theme-mode");
            } else {
                if (localStorage.getItem("data-theme") !== null) {
                    themeMode = localStorage.getItem("data-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-theme", themeMode);
        }
    </script>
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <style>
            body {
                background-image: url('{{ asset("admin-assets/media/auth/bg7-dark.jpg") }}');
            }
            [data-theme="dark"] body {
                background-image: url('{{ asset("admin-assets/media/auth/bg4-dark.jpg") }}');
            }
        </style>
        <div class="d-flex flex-column flex-column-fluid flex-lg-row">
            <div class="d-flex flex-center w-lg-50 pt-15 pt-lg-0 px-10">
                <div class="d-flex flex-center flex-lg-start flex-column">
                    <a style="font-size: 30px; color:white;" href="{{ route('home') }}" class="mb-7">
                        <img style="width: 300px; border-radius: 10px;" alt="Croydon College of Excellence" src="{{ asset('admin-assets/media/logos/logo-full.png') }}"/>
                    </a>
                </div>
            </div>
            <div class="d-flex flex-center w-lg-50 p-10">
                <div class="card rounded-3 w-md-550px">
                    <div class="card-body p-5 p-lg-10">
                        <!-- Session Status -->
                        @if (session('status'))
                        <div class="alert alert-success mb-4">
                            {{ session('status') }}
                        </div>
                        @endif

                        <form method="POST" action="{{ route('admin.login.store') }}" class="form w-100" id="kt_sign_in_form">
                            @csrf
                            <div class="text-center mb-11">
                                <h1 class="text-dark fw-bolder mb-3">Admin Login</h1>
                            </div>

                            <!-- Username/Email Field -->
                            <div class="fv-row mb-4">
                                <label for="login" class="form-label text-dark fs-6 fw-bolder mb-2">Username or Email</label>
                                <input id="login" 
                                    type="text" 
                                    name="login" 
                                    class="form-control bg-transparent @error('login') is-invalid @enderror" 
                                    placeholder="Enter your email or username"
                                    value="{{ old('login') }}" 
                                    required 
                                    autofocus 
                                    autocomplete="username" />
                                @if ($errors->has('login'))
                                <div class="text-danger mt-2">
                                    {{ $errors->first('login') }}
                                </div>
                                @endif
                            </div>

                            <!-- Password Field -->
                            <div class="fv-row mb-5">
                                <label for="password" class="form-label text-dark fs-6 fw-bolder mb-2">Password</label>
                                <input id="password" 
                                    type="password" 
                                    name="password" 
                                    class="form-control bg-transparent @error('password') is-invalid @enderror" 
                                    placeholder="Enter your password"
                                    required 
                                    autocomplete="current-password" />
                                @if ($errors->has('password'))
                                <div class="text-danger mt-2">
                                    {{ $errors->first('password') }}
                                </div>
                                @endif
                            </div>

                            <!-- Remember Me -->
                            <div class="fv-row mb-5">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input id="remember_me" 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        name="remember"
                                        value="1">
                                    <span class="form-check-label text-gray-600 fs-6">Remember me</span>
                                </label>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">
                                    <span class="indicator-label">Sign In</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('admin-assets/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('admin-assets/js/scripts.bundle.js') }}"></script>
</body>
</html>
