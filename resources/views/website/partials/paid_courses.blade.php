{{--
    Homepage promotional section for the two paid courses.

    Prices and content come from the Course model (seeded from
    config/catalog.php), never from anything the browser sends. The buy
    buttons are POST forms protected by CSRF: the slug in the URL is resolved
    against the database and the Stripe Price is looked up server side.
--}}
@if ($courses->isNotEmpty())
    <!-- Start Paid Courses Area -->
    <div class="rbt-paid-courses-area bg-color-extra2 rbt-section-gap overflow-hidden" id="paid-courses">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="section-title text-center mb--20">
                        <span class="subtitle bg-primary-opacity">LIFE IN THE UK</span>
                        <h2 class="title">Prepare For The Official Life in the UK Test</h2>
                        <p class="mt--20 mb-0">
                            Self-study courseware written by our tutors, delivered instantly as a secure download.
                            Pay once and keep access for life.
                        </p>
                    </div>
                </div>
            </div>

            <div class="row g-5 mt--10">
                @foreach ($courses as $course)
                    <div class="col-lg-6 col-md-6 col-12 sal-animate" data-sal="slide-up"
                        data-sal-delay="{{ $loop->index * 120 }}" data-sal-duration="800">
                        <div class="rbt-service rbt-service-2 rbt-hover-02 radius-10 h-100 d-flex flex-column">

                            @if ($course->badge)
                                <div class="mb--15">
                                    <span class="badge bg-primary">{{ $course->badge }}</span>
                                </div>
                            @endif

                            <h3 class="title">{{ $course->name }}</h3>

                            <p class="mt--10 mb-0">
                                {{ $course->short_description }}
                            </p>

                            <div class="mt--20">
                                <span class="price" style="font-size: 2.5rem; font-weight: 700; line-height: 1;">
                                    {{ $course->formattedPrice() }}
                                </span>
                                <span class="ms-2">one-off payment</span>
                            </div>

                            <hr class="my-4">

                            <h4 class="title">What is included</h4>
                            <ul class="rbt-list-style-1 list-unstyled">
                                @foreach ($course->features as $feature)
                                    <li class="d-flex">
                                        <i class="feather-check"></i>
                                        <span class="ms-2">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="mt-auto pt-4">
                                @auth
                                    @if ($course->hasAccessFor(auth()->user()))
                                        <a href="{{ route('dashboard') }}"
                                            class="rbt-btn btn-gradient btn-sm w-100 justify-content-center text-center">
                                            <span>View In My Account</span>
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('checkout.store', $course) }}">
                                            @csrf
                                            <button type="submit"
                                                class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                                <span>Buy {{ $course->name }} &mdash; {{ $course->formattedPrice() }}</span>
                                            </button>
                                        </form>
                                    @endif
                                @else
                                    <form method="POST" action="{{ route('checkout.store', $course) }}">
                                        @csrf
                                        <button type="submit"
                                            class="rbt-btn btn-border-gradient radius-round btn-sm w-100 justify-content-center text-center">
                                            <span>Buy {{ $course->name }} &mdash; {{ $course->formattedPrice() }}</span>
                                        </button>
                                    </form>
                                @endauth

                                <div class="mt-3 text-center">
                                    <a href="{{ route('courses.show', $course) }}">See full details</a>
                                </div>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row mt--40">
                <div class="col-lg-12">
                    <p class="text-center mb-0">
                        <i class="feather-shield mr--10"></i>
                        Secure payment by Stripe &middot; Instant access after payment is confirmed
                        &middot; Card details never touch this website
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- End Paid Courses Area -->
@endif
