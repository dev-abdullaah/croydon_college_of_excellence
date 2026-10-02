<!-- resources/views/website/pages/auth/login.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <span class="subtitle bg-primary-opacity">STUDENT PORTAL</span>
                    <h2 class="title">Sign In</h2>
                    <p class="mt--10 mb-0">Access your Life in the UK course lessons, study cards, and mock tests.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">


                <div class="rbt-service rbt-service-2 radius-10">
                    <form method="POST" action="{{ route('login') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" required autofocus
                                autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" required
                                autocomplete="current-password">
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label" for="remember">Keep me signed in</label>
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Sign In</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt--20">
                    <p class="mb-2">
                        New student?
                        <a href="{{ route('register') }}" class="fw-bold">Create an account</a>
                    </p>
                    <p class="mb-0">
                        Haven't enrolled yet?
                        <a href="{{ route('courses.index') }}" class="text-primary fw-bold">Explore Life in the UK Courses &amp; Mock Tests &rarr;</a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
