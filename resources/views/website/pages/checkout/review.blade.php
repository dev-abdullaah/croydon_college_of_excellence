<!-- resources/views/website/pages/checkout/review.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Check Your Order</h2>
                    <p class="mt--10 mb-0">One last look before you pay.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">

                @include('website.partials.checkout-steps', ['step' => 'review'])

                <div class="rbt-service rbt-service-2 radius-10">

                    {{--
                        Everything on this page is read from the database: the
                        name, the price, the feature list. Nothing here comes
                        from a hidden form field, because a form field is
                        whatever the browser says it is. The only thing this
                        page posts is the consent tick box, and the amount is
                        resolved again server side when the session is created.
                    --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            @if ($course->badge)
                                <span class="badge bg-primary">{{ $course->badge }}</span>
                            @endif
                            <h3 class="title mt--10 mb-0">{{ $course->name }}</h3>
                        </div>
                        <div class="text-end">
                            <span style="font-size: 2.25rem; font-weight: 700; line-height: 1;">
                                {{ $course->formattedPrice() }}
                            </span>
                            <span class="d-block mt--5">one-off payment</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-4">
                        <div class="col-lg-7">
                            <h4 class="title">What is included</h4>
                            <ul class="rbt-list-style-1 list-unstyled mb-0">
                                @foreach ($course->features as $feature)
                                    <li class="d-flex">
                                        <i class="feather-check"></i>
                                        <span class="ms-2">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="col-lg-5">
                            <h4 class="title">What happens next</h4>
                            <ul class="rbt-list-style-1 list-unstyled mb-0">
                                <li class="d-flex">
                                    <i class="feather-lock"></i>
                                    <span class="ms-2">You pay on Stripe's secure checkout. Your card details never touch this website.</span>
                                </li>
                                <li class="d-flex">
                                    <i class="feather-check-circle"></i>
                                    <span class="ms-2">The moment Stripe confirms the payment, the course appears in My Account.</span>
                                </li>
                                <li class="d-flex">
                                    <i class="feather-monitor"></i>
                                    <span class="ms-2">Read the lessons and take the practice tests online, as often as you like.</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <hr class="my-4">

                    <form method="POST" action="{{ route('checkout.store', $course) }}" novalidate>
                        @csrf

                        {{--
                            The consent box, required.

                            The wording comes from config/courses.php rather than
                            being written here, because it is a legal statement
                            and the owner needs to be able to change it without
                            anyone having to find a Blade file. It is a plain
                            required checkbox posting `consent`, and the server
                            refuses to open a payment session without it, so it
                            cannot be submitted empty by editing the page.

                            A custom validity message is set because the browser
                            default ("Please tick this box if you want to
                            proceed") is dismissive on a page where ticking it is
                            the actual instruction.
                        --}}
                        <div class="form-check mb-3">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox"
                                name="consent" value="1" id="consent" required
                                {{ old('consent') ? 'checked' : '' }}
                                oninvalid="this.setCustomValidity('Please tick the box to agree to the terms before paying.')"
                                oninput="this.setCustomValidity('')">
                            <label class="form-check-label" for="consent">
                                {!! \Illuminate\Support\Str::markdown(config('courses.consent_text')) !!}
                            </label>
                            @error('consent')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rbt-btn-wrapper d-flex flex-wrap gap-3">
                            <button type="submit"
                                class="rbt-btn btn-gradient radius-round btn-sm justify-content-center text-center">
                                <span>Pay {{ $course->formattedPrice() }} securely with Stripe</span>
                            </button>
                            <a href="{{ route('courses.show', $course) }}"
                                class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                                <span>Back to the course</span>
                            </a>
                        </div>
                    </form>
                </div>

                <p class="text-center mt--20">
                    Something gone wrong? Call us on <a href="tel:+447405073764">+44 7405 073764</a>.
                </p>

            </div>
        </div>
    </div>
</div>

@endsection
