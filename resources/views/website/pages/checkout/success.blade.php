<!-- resources/views/website/pages/checkout/success.blade.php -->
@extends('website.layouts.master')

@section('content')

{{--
    This page is now only ever the *waiting* page.

    When Stripe has confirmed the payment, CheckoutController::success
    redirects to My Account instead of rendering anything here, so a confirmed
    customer is taken straight to the course they just paid for. What is left
    is the case where the webhook has not landed yet, which is seconds of
    awkwardness rather than an error, and the job of this page is to get
    through it without making the customer do anything.

    The wait is ended by a meta refresh rather than by anything scripted.
    That is a deliberate choice, not laziness: this page is the one screen in
    the flow that is loaded over and over, and a plain meta refresh does it
    without a second script, without a dependency, and - the reason that
    matters - identically whether or not JavaScript is running. Someone
    arriving here from an email client with scripting blocked gets the same
    resolution as everyone else.
--}}

@php
    /*
     | Keep refreshing until the last attempt. The controller clamps `attempt`
     | to the configured maximum, so this can never be pushed past the end by
     | editing the query string - the only thing the number decides is whether
     | another request is made, and the last view does not make one.
     */
    $nextUrl = request()->fullUrlWithQuery(['attempt' => $attempt + 1]);
    $keepRefreshing = $attempt < $maxAttempts;
@endphp

@if ($keepRefreshing)
    {{--
        The refresh has to name the next attempt, not just this URL. A bare
        interval reloads whatever URL it is on, which would re-request
        attempt=1 for ever and the count would never reach the end - the page
        would spin indefinitely on a payment the webhook has already settled.
    --}}
    <meta http-equiv="refresh" content="{{ $refreshInterval }}; url={{ $nextUrl }}">
@endif

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

                {{--
                    Reaching this URL proves nothing. Only Stripe can say the
                    money arrived, so nothing here is shown as bought and no
                    access is granted on the strength of the URL. If the
                    webhook is slow, this page keeps asking Stripe and the
                    moment it has the answer the customer is moved to their
                    account.
                --}}
                <div class="rbt-service rbt-service-2 radius-10 text-center">
                    <i class="feather-clock" style="font-size: 64px;"></i>

                    @if ($keepRefreshing)
                        <h3 class="title mt--20">We Are Confirming Your Payment</h3>
                        <p>
                            This is normal and takes only a few seconds. Hold on and
                            we will take you to {{ $course?->name ?? 'your course' }} in My Account
                            as soon as Stripe confirms it.
                        </p>

                        {{--
                            The counter is here to explain the waiting, not to
                            test anybody. Without it a page that refreshes
                            itself every few seconds looks stuck; with it, it
                            looks like something is happening.
                        --}}
                        <p class="mb-0" role="status" aria-live="polite">
                            Still checking
                            <span class="fw-bold">({{ $attempt }} of {{ $maxAttempts }})</span>...
                        </p>
                    @else
                        {{--
                            Stopped refreshing. The payment may well have gone
                            through - the webhook is simply not here yet, and
                            access follows the webhook, not this page.

                            So the message is deliberately about time rather than
                            failure, and it does not tell them the payment did not
                            work: we do not know that, and saying so would send
                            somebody who has just been charged off to try paying
                            a second time. They are sent to their account, where
                            the course appears on its own if it has been
                            confirmed, and the phone number is there for the case
                            where it has not.
                        --}}
                        <h3 class="title mt--20">This Is Taking Longer Than Usual</h3>
                        <p>
                            We have not heard back from our payment provider yet. Your
                            {{ $course?->name ?? 'course' }} will appear in My Account by itself
                            the moment that confirmation arrives &mdash; there is nothing you
                            need to do, and you do not need to pay again.
                        </p>
                        <p class="mb-0">
                            If it has not appeared in the next few minutes, please call
                            us on <a href="tel:+447405073764">+44 7405 073764</a> or email
                            <a href="mailto:info@croydoncollegeofexcellence.co.uk">
                                info@croydoncollegeofexcellence.co.uk</a>.
                        </p>
                    @endif

                    <hr class="my-4">

                    <div class="rbt-btn-wrapper">
                        {{--
                            Always offered, at both stages. Somebody who would
                            rather not sit on a refreshing page should be able to
                            leave: their account is where the course ends up
                            either way, and the dashboard reads the same paid
                            purchase this page would have.
                        --}}
                        <a href="{{ route('dashboard') }}"
                            class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                            <span>Go To My Account</span>
                        </a>

                        @if (! $keepRefreshing)
                            {{--
                                A way to look without leaving, for the customer who
                                wants to see it themselves rather than take our
                                word for it.
                            --}}
                            <a href="{{ $nextUrl }}"
                                class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center mt-3">
                                <span>Check Again</span>
                            </a>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
