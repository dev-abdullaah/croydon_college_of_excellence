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
                        One question on this page: the code.

                        This form used to ask for an email address as well, and
                        carried a second form underneath for resending. Two
                        email boxes on one screen is not a normal thing to meet -
                        it reads as two competing forms, and people cannot tell
                        which one to fill in. The address is already known,
                        because whoever sent the code remembered it, so asking
                        again bought nothing.

                        Requesting a new code is now a link to its own page.
                    --}}
                    <p class="mb-4">
                        @if ($hasEmail)
                            We sent a six digit code to <strong>{{ $email }}</strong>.
                        @else
                            Enter the six digit code we sent you.
                        @endif
                    </p>

                    <form method="POST" action="{{ route('verification.verify') }}" novalidate>
                        @csrf

                        {{--
                            Fallback for the case where the address is not
                            known, which happens once the session that carried
                            it has gone. Not visible, and not a way in: the code
                            is what proves ownership, and this only says which
                            account to compare it against.
                        --}}
                        @unless ($hasEmail)
                            <input type="hidden" name="email" value="{{ old('email') }}">
                        @endunless

                        <div class="mb-3">
                            <label for="code" class="form-label">Verification Code</label>
                            {{--
                                inputmode=numeric and autocomplete=one-time-code
                                are what make this read as a code from a phone:
                                the keyboard becomes digits, and iOS and Android
                                will offer the code they just received.

                                No placeholder, deliberately. A sample value like
                                123456 is indistinguishable from a real code,
                                and it reads as one - which is exactly how it was
                                mistaken for the code that had been sent, and
                                then typed in as though it were. The other
                                placeholders on this site name the field in
                                words for the same reason; a code is the one
                                field where an example is actively misleading.
                            --}}
                            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*"
                                autocomplete="one-time-code" maxlength="6"
                                class="form-control @error('code') is-invalid @enderror"
                                value="{{ old('code') }}" required autofocus>
                            <div class="form-text">Enter the six digit code that we sent to your mail.</div>
                            @error('code')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Confirm my email</span>
                            </button>
                        </div>
                    </form>

                    <p class="mt-4 mb-0">
                        Nothing arrived, or the code has expired? The code stops working after
                        {{ config('auth.verification_code.expire', 15) }} minutes, and
                        {{ config('auth.verification_code.max_attempts', 5) }} wrong attempts lock it
                        out for {{ config('auth.verification_code.lockout_minutes', 15) }} minutes, so
                        request a new one rather than guessing again.
                        <a href="{{ route('verification.resend.form') }}">Send a new code</a>.
                    </p>
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