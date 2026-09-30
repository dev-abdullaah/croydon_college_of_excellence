<!-- resources/views/website/pages/auth/verify-email.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Confirm Your Email</h2>
                    <p class="mt--10 mb-0">One last step before you can start your course.</p>
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
                    {{--
                        This page is reachable whether or not somebody is
                        signed in. Registration deliberately does not sign
                        anyone in, and a correct password for an unverified
                        account does not either, so the person following the
                        email link is a guest until the link proves the address
                        is theirs.
                    --}}
                    <p class="mb-4">
                        We have sent a verification link to
                        @if ($email)
                            <strong>{{ $email }}</strong>.
                        @else
                            <strong>your email address</strong>.
                        @endif
                        Open it and you will be signed in automatically.
                    </p>

                    <p class="mb-4">
                        Nothing arrived? Check your junk folder, or send it again below. The link
                        stops working after {{ config('auth.verification.expire', 60) }} minutes, so
                        an old one is best replaced rather than searched for.
                    </p>

                    <form method="POST" action="{{ route('verification.resend') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                                class="form-control @error('email') is-invalid @enderror" required
                                autocomplete="email">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Send the link again</span>
                            </button>
                        </div>
                    </form>
                </div>

                <p class="text-center mt--20">
                    Wrong address, or not yours?
                    <a href="{{ route('home') }}">Back to the site</a>
                </p>

            </div>
        </div>
    </div>
</div>

@endsection
