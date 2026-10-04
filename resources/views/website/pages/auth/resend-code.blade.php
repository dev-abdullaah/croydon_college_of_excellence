<!-- resources/views/website/pages/auth/resend-code.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Send a New Code</h2>
                    <p class="mt--10 mb-0">We will email you a fresh six digit code.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">

                @include('website.partials.checkout-steps', ['step' => 'verify'])

<div class="rbt-service rbt-service-2 radius-10">
                    {{--
                        This page exists so the code page can ask for one thing.

                        It is the only place in this flow that asks for an email
                        address, which is what keeps /email/verify show a single
                        field. Same shape as Laravel's own password reset: a
                        "send it again" form is a page of its own, reached by a
                        link, rather than a second form under the first.
                    --}}
                    <form method="POST" action="{{ route('verification.resend') }}" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            @if ($email)
                                <input type="email" id="email" name="email" value="{{ $email }}"
                                    class="form-control" readonly autocomplete="email">
                                <div class="form-text">A verification code will be sent to this address.</div>
                            @else
                                <input type="email" id="email" name="email" value="{{ old('email') }}"
                                    class="form-control @error('email') is-invalid @enderror" required autofocus
                                    autocomplete="email">
                                @error('email')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>

                        <div class="rbt-btn-wrapper">
                            <button type="submit"
                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                <span>Send the code</span>
                            </button>
                        </div>
                    </form>

                    <p class="mt-4 mb-0">
                        Already have a code?
                        <a href="{{ route('verification.notice') }}">Enter it here</a>.
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