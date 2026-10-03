<!-- resources/views/website/pages/two-factor-setup.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Two-Factor Authentication</h2>
                    <p class="mt--10 mb-0">
                        Add an extra layer of security to your account
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
                    <h3 class="title">Set Up 2FA</h3>
                    <a href="{{ route('account.center') }}" class="btn btn-lg btn-outline-secondary">
                        <i class="feather-arrow-left me-1"></i> Back to Account Center
                    </a>
                </div>

                @if (session('status') === '2fa-enabled')
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="feather-check-circle me-2"></i>
                        Two-factor authentication has been enabled successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('status') === '2fa-disabled')
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="feather-check-circle me-2"></i>
                        Two-factor authentication has been disabled.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('status') === '2fa-already-enabled')
                    <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                        <i class="feather-info me-2"></i>
                        Two-factor authentication is already enabled.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('status') === 'recovery-codes-regenerated')
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                        <i class="feather-check-circle me-2"></i>
                        Recovery codes have been regenerated.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                {{-- Step 1: Install Authenticator App --}}
                <div class="rbt-service rbt-service-2 radius-10 mb--40">
                    <div class="d-flex align-items-start mb-4">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                            <span class="fw-bold">1</span>
                        </div>
                        <div>
                            <h5 class="title mb-1">Install an Authenticator App</h5>
                            <p class="text-muted mb-0">Download one of these apps on your phone:</p>
                        </div>
                    </div>
                    <div class="row g-3 mt-3">
                        <div class="col-md-4 text-center">
                            <a href="https://apps.apple.com/app/google-authenticator/id388497605" target="_blank" class="btn btn-outline-secondary w-100" style="text-decoration: none;">
                                <i class="fab fa-apple fa-2x mb-2 d-block"></i>
                                <small>App Store</small>
                            </a>
                        </div>
                        <div class="col-md-4 text-center">
                            <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2" target="_blank" class="btn btn-outline-secondary w-100" style="text-decoration: none;">
                                <i class="fab fa-google-play fa-2x mb-2 d-block"></i>
                                <small>Google Play</small>
                            </a>
                        </div>
                        <div class="col-md-4 text-center">
                            <a href="https://1password.com/downloads/" target="_blank" class="btn btn-outline-secondary w-100" style="text-decoration: none;">
                                <i class="feather-lock fa-2x mb-2 d-block"></i>
                                <small>1Password</small>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Scan QR Code --}}
                <div class="rbt-service rbt-service-2 radius-10 mb--40">
                    <div class="d-flex align-items-start mb-4">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                            <span class="fw-bold">2</span>
                        </div>
                        <div>
                            <h5 class="title mb-1">Scan the QR Code</h5>
                            <p class="text-muted mb-0">Open your authenticator app and scan this QR code, or enter the secret key manually.</p>
                        </div>
                    </div>

                    <div class="text-center mb-4">
                        <div class="bg-white p-4 rounded border d-inline-block">
                            <img src="{{ $qr_code }}" alt="QR Code for 2FA Setup" class="img-fluid" style="max-width: 200px;">
                        </div>
                    </div>

                    <div class="alert alert-light border">
                        <h6 class="fw-semibold mb-2">Or enter this secret key manually:</h6>
                        <div class="font-monospace fs-5 fw-bold text-break user-select-all">{{ $secret }}</div>
                    </div>
                </div>

                {{-- Step 3: Enter Code --}}
                <div class="rbt-service rbt-service-2 radius-10 mb--40">
                    <div class="d-flex align-items-start mb-4">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 40px; height: 40px;">
                            <span class="fw-bold">3</span>
                        </div>
                        <div>
                            <h5 class="title mb-1">Enter the 6-Digit Code</h5>
                            <p class="text-muted mb-0">Your authenticator app will generate a 6-digit code. Enter it below to confirm setup.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('account.2fa.confirm') }}" class="row g-3">
                        @csrf

                        <div class="col-12">
                            <label for="code" class="form-label fw-semibold">6-Digit Code</label>
                            <input type="text" class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                                   id="code" name="code" required maxlength="6" pattern="\d{6}"
                                   autocomplete="one-time-code" inputmode="numeric"
                                   placeholder="000000" style="letter-spacing: 0.5em;">
                            @error('code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="current_password" class="form-label fw-semibold">Current Password</label>
                            <input type="password" class="form-control form-control-lg @error('current_password') is-invalid @enderror"
                                   id="current_password" name="current_password" required autocomplete="current-password">
                            @error('current_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr class="my-4">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="{{ route('account.center') }}" class="btn btn-lg btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-lg btn-primary">Enable 2FA</button>
                            </div>
                        </div>
                    </form>
                </div>

                {{-- Recovery Codes Notice --}}
                <div class="rbt-service rbt-service-2 radius-10 bg-warning bg-opacity-10 border-warning">
                    <h5 class="title mb-3 text-warning"><i class="feather-alert-triangle me-2"></i> Save Your Recovery Codes</h5>
                    <p class="text-muted mb-3">After enabling 2FA, you'll receive <strong>8 recovery codes</strong>. Save them in a secure place (password manager, printed copy). Each code can be used <strong>once</strong> if you lose access to your authenticator app.</p>
                    <p class="text-muted small mb-0">Without recovery codes, you may permanently lose access to your account.</p>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection