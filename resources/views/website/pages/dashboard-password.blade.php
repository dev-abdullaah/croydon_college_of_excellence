<!-- resources/views/website/pages/dashboard-password.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Change Password</h2>
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
                    <h3 class="title">Change Your Password</h3>
                    <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-secondary">Back to Account</a>
                </div>

                @if (session('status') === 'password-changed')
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="feather-check-circle me-2"></i>
                        Your password has been changed successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="rbt-service rbt-service-2 radius-10">
                    <form method="POST" action="{{ route('dashboard.password.update') }}" class="needs-validation" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-12">
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

                            <div class="col-12">
                                <label for="password" class="form-label fw-semibold">New Password</label>
                                <input type="password"
                                       class="form-control form-control-lg @error('password') is-invalid @enderror"
                                       id="password"
                                       name="password"
                                       required
                                       autocomplete="new-password">
                                @error('password')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Must be at least 8 characters.
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="password_confirmation" class="form-label fw-semibold">Confirm New Password</label>
                                <input type="password"
                                       class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       required
                                       autocomplete="new-password">
                                @error('password_confirmation')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-secondary me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-lg btn-primary">Save New Password</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection