{{--
    Shared chrome for the four learning pages (course hub, lesson reader,
    paper player, results). Keeps the heading shape consistent and the
    learning-area stylesheet in one place.
--}}
@extends('website.layouts.master')

@section('content')
    <div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="section-title text-center">
                        <span class="subtitle bg-primary-opacity">{{ $course->name }}</span>
                        <h2 class="title">@yield('heading')</h2>
                        @hasSection('subheading')
                            <p class="mt--10 mb-0">@yield('subheading')</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-color-white rbt-section-gap">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    @yield('learn')
                </div>
            </div>
        </div>
    </div>
@endsection
