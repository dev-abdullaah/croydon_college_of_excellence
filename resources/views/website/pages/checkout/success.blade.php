<!-- resources/views/website/pages/checkout/success.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Thank You</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">

                @if ($purchase)
                    <div class="rbt-service rbt-service-2 radius-10 text-center">
                        <i class="feather-check-circle" style="font-size: 64px;"></i>
                        <h3 class="title mt--20">Payment Confirmed</h3>
                        <p>
                            Your payment of
                            <strong>{{ number_format($purchase->amount / 100, 2) }}
                                {{ strtoupper($purchase->currency) }}</strong>
                            for <strong>{{ $purchase->course->name }}</strong> has been received.
                        </p>
                        <p class="mb-0">
                            A receipt has been sent to {{ $purchase->customer_email ?? 'your email address' }}.
                        </p>

                        <hr class="my-4">

                        {{--
                            Nothing is downloaded - the material is read and sat
                            on the website - so the button goes straight to the
                            course that was just bought. The label follows the
                            course: the £99 pack has lessons to read, the £49
                            mock test pack has none and is papers only.
                        --}}
                        <div class="rbt-btn-wrapper">
                            @if ($purchase->course->hasLearningContent())
                                <a href="{{ route('learn.index', $purchase->course) }}"
                                    class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                                    <span>{{ $purchase->course->hasLessons() ? 'Start Learning' : 'Start Your Mock Tests' }}</span>
                                </a>
                                <a href="{{ route('dashboard') }}"
                                    class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center mt-3">
                                    <span>Go To My Account</span>
                                </a>
                            @else
                                <a href="{{ route('dashboard') }}"
                                    class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                                    <span>Go To My Account</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    {{--
                        Reaching this URL proves nothing - only Stripe can tell
                        us whether the money arrived. If the webhook has not
                        landed yet the purchase simply is not shown as paid.
                    --}}
                    <div class="rbt-service rbt-service-2 radius-10 text-center">
                        <i class="feather-clock" style="font-size: 64px;"></i>
                        <h3 class="title mt--20">We Are Confirming Your Payment</h3>
                        <p>
                            Stripe is still confirming this payment with us. This page updates automatically once
                            it arrives, and your course will be waiting in My Account.
                        </p>
                        <p class="mb-0">
                            If this message is still here in a few minutes, please email
                            <a href="mailto:info@croydoncollegeofexcellence.co.uk">
                                info@croydoncollegeofexcellence.co.uk</a>
                            quoting your receipt.
                        </p>

                        <hr class="my-4">

                        <div class="rbt-btn-wrapper">
                            <a href="{{ route('dashboard') }}"
                                class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                                <span>Go To My Account</span>
                            </a>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

@endsection
