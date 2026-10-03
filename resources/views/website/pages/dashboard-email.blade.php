<!-- resources/views/website/pages/dashboard-email.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Change Email Address</h2>
                    <p class="mt--10 mb-0">
                        Signed in as {{ auth()->user()->email }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="account-bar mb--40">
                    <h3 class="title">Change Your Email Address</h3>
                    <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-secondary">Back to Account</a>
                </div>

                @if (session('status') === 'email-change-sent')
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="feather-check-circle me-2"></i>
                        A verification email has been sent to <strong>{{ session('pending_email') ?? $pendingEmail }}</strong>.
                        Please check your inbox and click the link to confirm the change.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('status') === 'email-changed')
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="feather-check-circle me-2"></i>
                        Your email address has been updated successfully. You will need to verify the new address.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($pendingEmail)
                    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                        <i class="feather-info me-2"></i>
                        A change to <strong>{{ $pendingEmail }}</strong> is pending. 
                        <a href="{{ route('dashboard.email') }}" class="alert-link">Resend verification email</a> or wait for the link to expire.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="rbt-service rbt-service-2 radius-10">
                    <form method="POST" action="{{ route('dashboard.email.update') }}" class="needs-validation" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="current_password" class="form-label fw-semibold">Current Password</label>
                            <input type="password"
                                   class="form-control form-control-lg @error('current_password') is-invalid @enderror"
                                   id="current_password"
                                   name="current_password"
                                   required
                                   autocomplete="current-password">
                            @error('current_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">New Email Address</label>
                            <input type="email"
                                   class="form-control form-control-lg @error('email') is-invalid @enderror"
                                   id="email"
                                   name="email"
                                   required
                                   autocomplete="email"
                                   placeholder="Enter your new email address">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                We'll send a verification link to this address. Your current email will remain active until you confirm the change.
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-lg btn-primary">Send Verification Email</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection