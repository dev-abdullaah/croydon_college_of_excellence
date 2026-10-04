<!-- resources/views/website/pages/auth/register.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Create Your Account</h2>
                    <p class="mt--10 mb-0">
                        One account keeps every course and mock test pack you buy.
                        @if ($intendedCourse)
                            You are one step away from {{ $intendedCourse->name }}.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">

                @include('website.partials.checkout-steps', ['step' => 'register'])

                {{--
                    The course they came for, next to the form.

                    Shown because arriving here from a Buy button and being
                    asked to make an account with no mention of what it is for
                    is how a page like this loses people. The slug is resolved
                    from the database, so this is only ever a real active course
                    on this site, and it is absent when somebody came here
                    directly rather than from a purchase.
                --}}
                @if ($intendedCourse)
                    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-3 mb--30"
                        role="status">
                        <div>
                            <i class="feather-book-open me-2"></i>
                            <strong>{{ $intendedCourse->name }}</strong>
                            &mdash; {{ $intendedCourse->formattedPrice() }}, one-off payment
                        </div>
                        <a href="{{ route('courses.show', $intendedCourse) }}" class="btn btn-sm btn-link p-0">
                            See what is included
                        </a>
                    </div>
                @endif

                <div class="rbt-service rbt-service-2 radius-10">
                    <form id="register-form" method="POST" action="{{ route('register') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror" required autofocus
                                autocomplete="name">
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="text-danger small mt-1 d-none" id="name-feedback-js" role="alert"></div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" required
                                autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="text-danger small mt-1 d-none" id="email-feedback-js" role="alert"></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="password" id="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" required
                                autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="text-danger small mt-1 d-none" id="password-feedback-js" role="alert"></div>
                            <small class="d-block mt-2">At least 8 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm Password <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                class="form-control @error('password_confirmation') is-invalid @enderror" required autocomplete="new-password">
                            @error('password_confirmation')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <div class="text-danger small mt-1 d-none" id="confirm-password-feedback-js" role="alert"></div>
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Create Account</span>
                            </button>
                        </div>
                    </form>
                </div>

                <p class="text-center mt--20">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                </p>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('register-form');

    const nameRegex  = /^[\p{L}\s\-'\.]+$/u;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const nameInput     = document.getElementById('name');
    const emailInput    = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmInput  = document.getElementById('password_confirmation');

    // ===== Validators (return an error string, or null if valid) =====
    function validateName(value) {
        if (value.trim().length === 0) return 'Name is required.';
        if (!nameRegex.test(value)) return 'Name can only contain letters, spaces, hyphens, apostrophes, and periods.';
        const words = value.trim().split(/\s+/);
        if (words.length === 1 && words[0].length < 2) return 'Please enter at least 2 characters for your name.';
        return null;
    }

    function validateEmail(value) {
        if (value.length === 0) return 'Email is required.';
        if (!emailRegex.test(value)) return 'Please enter a valid email address.';
        return null;
    }

    function validatePassword(value) {
        if (value.length === 0) return 'Password is required.';
        if (value.length < 8) return 'Password must be at least 8 characters.';
        return null;
    }

    function validateConfirm(value) {
        if (value.length === 0) return 'Please confirm your password.';
        if (value !== passwordInput.value) return 'Passwords do not match.';
        return null;
    }

    // ===== Field wiring =====
    function setupField(input, feedbackId, validator) {
        const feedback = document.getElementById(feedbackId);

        function hideServerError() {
            const serverError = input.parentNode.querySelector('.invalid-feedback');
            if (serverError) {
                serverError.classList.remove('d-block');
                serverError.classList.add('d-none');
            }
        }

        function run() {
            const error = validator(input.value);
            if (error) {
                feedback.textContent = error;
                feedback.classList.remove('d-none');
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
            } else {
                feedback.textContent = '';
                feedback.classList.add('d-none');
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
            return error;
        }

        input.addEventListener('input', function () {
            hideServerError();   // JS message replaces the stale server one
            run();
        });
        input.addEventListener('blur', run);

        return run;
    }

    const runName     = setupField(nameInput,     'name-feedback-js',             validateName);
    const runEmail    = setupField(emailInput,    'email-feedback-js',            validateEmail);
    const runPassword = setupField(passwordInput, 'password-feedback-js',         validatePassword);
    const runConfirm  = setupField(confirmInput,  'confirm-password-feedback-js', validateConfirm);

    // Re-check confirm field when the password changes
    passwordInput.addEventListener('input', function () {
        if (confirmInput.value.length > 0) runConfirm();
    });

    // ===== Submit =====
    form.addEventListener('submit', function (e) {
        // run every validator (no short-circuit) so all errors show at once
        const results = [runName(), runEmail(), runPassword(), runConfirm()];
        if (results.some(Boolean)) {
            e.preventDefault();
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
        }
    });
});
</script>
@endpush

@endsection