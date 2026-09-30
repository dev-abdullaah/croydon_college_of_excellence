<!-- resources/views/website/pages/checkout/cancel.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Payment Cancelled</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">

                <div class="rbt-service rbt-service-2 radius-10 text-center">
                    <i class="feather-x-circle" style="font-size: 64px;"></i>
                    <h3 class="title mt--20">No Payment Was Taken</h3>
                    <p>
                        You cancelled the payment, so nothing has been charged and no access has been granted.
                        Your place is saved whenever you are ready.
                    </p>

                    <hr class="my-4">

                    <div class="rbt-btn-wrapper">
                        @if ($course)
                            <form method="POST" action="{{ route('checkout.store', $course) }}" class="d-inline">
                                @csrf
                                <button type="submit"
                                    class="rbt-btn btn-gradient radius-round btn-sm justify-content-center text-center">
                                    <span>Try {{ $course->name }} Again</span>
                                </button>
                            </form>

                            <a href="{{ route('courses.index') }}"
                                class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                                <span>Back To The Courses</span>
                            </a>
                        @else
                            <a href="{{ route('courses.index') }}"
                                class="rbt-btn btn-border-gradient radius-round btn-sm justify-content-center text-center">
                                <span>Back To The Courses</span>
                            </a>
                        @endif
                    </div>

                    <p class="mt--20 mb-0">
                        Having trouble? Call us on
                        <a href="tel:+447405073764">+44 7405 073764</a>.
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
