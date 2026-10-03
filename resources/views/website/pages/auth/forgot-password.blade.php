<!-- resources/views/website/pages/auth/forgot-password.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <span class="subtitle bg-primary-opacity">STUDENT PORTAL</span>
                    <h2 class="title">Forgot Password</h2>
                    <p class="mt--10 mb-0">Enter your email address and we'll send you a link to reset your password.</p>
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
                    <form method="POST" action="{{ route('password.email') }}" novalidate>
                        @csrf

                        @if (session('status'))
                            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                <i class="feather-check-circle me-2"></i>
                                {{ session('status') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                class="form-control form-control-lg @error('email') is-invalid @enderror" required
                                autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Send Reset Link</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt--20">
                    <p class="mb-0">
                        Remember your password?
                        <a href="{{ route('login') }}" class="fw-bold">Sign In</a>
                    </p>
                    <p class="mb-0 mt-2">
                        New student?
                        <a href="{{ route('register') }}" class="fw-bold">Create an account</a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection