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
                    <form method="POST" action="{{ route('register') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror" required autofocus
                                autocomplete="name">
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" required
                                autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" required
                                autocomplete="new-password">
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="d-block mt-2">At least 8 characters.</small>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                class="form-control" required autocomplete="new-password">
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

@endsection
