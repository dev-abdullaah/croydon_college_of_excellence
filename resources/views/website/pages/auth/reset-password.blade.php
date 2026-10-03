<!-- resources/views/website/pages/auth/reset-password.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <span class="subtitle bg-primary-opacity">STUDENT PORTAL</span>
                    <h2 class="title">Reset Password</h2>
                    <p class="mt--10 mb-0">Enter your new password below.</p>
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
                    <form method="POST" action="{{ route('password.update') }}" novalidate>
                        @csrf

                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="email" value="{{ $email }}">

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ $email }}"
                                class="form-control form-control-lg @error('email') is-invalid @enderror" required
                                autocomplete="email" readonly>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" name="password"
                                class="form-control form-control-lg @error('password') is-invalid @enderror" required
                                autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Must be at least 8 characters.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror" required
                                autocomplete="new-password">
                            @error('password_confirmation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Reset Password</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt--20">
                    <p class="mb-0">
                        <a href="{{ route('login') }}" class="fw-bold">&larr; Back to Sign In</a>
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection