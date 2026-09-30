<!-- resources/views/website/pages/dashboard.blade.php -->
@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">My Account</h2>
                    <p class="mt--10 mb-0">
                        Signed in as {{ auth()->user()->email }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-color-white rbt-section-gap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">


                <div class="row mb--40 align-items-center">
                    <div class="col-md-8">
                        <h3 class="title mb-0">Your purchased materials</h3>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Sign Out</button>
                        </form>
                    </div>
                </div>

                @if ($learnableCourses->isNotEmpty())
                    <h3 class="title mb--20">Start learning</h3>

                    <div class="row g-3 mb--40">
                        @foreach ($learnableCourses as $course)
                            @php $p = $lessonProgress[$course->slug] ?? ['done' => 0, 'total' => 0]; @endphp
                            <div class="col-lg-6 col-12">
                                <a href="{{ route('learn.index', $course) }}"
                                    class="rbt-service rbt-service-2 radius-10 h-100 d-block text-decoration-none text-reset">
                                    <h4 class="title mb-1">{{ $course->name }}</h4>
                                    @if ($p['total'] > 0)
                                        @php $pct = (int) round($p['done'] / $p['total'] * 100); @endphp
                                        <p class="mb-2 small">
                                            {{ $p['done'] }} of {{ $p['total'] }} lessons read
                                            @if ($p['done'] === $p['total'])
                                                &mdash; all done
                                            @endif
                                        </p>
                                        <div class="progress" role="progressbar" aria-label="Lessons read"
                                            aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
                                            style="height: .45rem;">
                                            <div class="progress-bar" style="width: {{ $pct }}%"></div>
                                        </div>
                                    @else
                                        <p class="mb-2 small">Go to your papers and start practising.</p>
                                    @endif
                                    <span class="rbt-btn btn-gradient btn-sm mt--20">
                                        <span>Open the course</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                @forelse ($paidPurchases as $purchase)
                    <div class="rbt-service rbt-service-2 radius-10 mb--30">
                        <div class="row align-items-center g-3">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-center mb-2">
                                    <h4 class="title mb-0 me-3">{{ $purchase->course->name }}</h4>
                                    <span class="{{ $purchase->statusBadgeClass() }}">{{ $purchase->statusLabel() }}</span>
                                </div>
                                <p class="mb-0">
                                    Paid {{ $purchase->paid_at?->format('j M Y, H:i') }}
                                    &middot; {{ number_format($purchase->amount / 100, 2) }}
                                    {{ strtoupper($purchase->currency) }}
                                </p>
                            </div>
                            <div class="col-lg-4">
                                <a href="{{ route('courses.show', $purchase->course) }}"
                                    class="btn btn-sm btn-outline-secondary w-100">Course details</a>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="title">Your material</h5>
                        @if ($purchase->course->hasLearningContent())
                            <p class="mb-3">
                                This course is read and tested on the website. Nothing is downloaded.
                            </p>
                            <a href="{{ route('learn.index', $purchase->course) }}"
                                class="rbt-btn btn-gradient btn-sm">
                                <span>Open the course</span>
                            </a>
                        @else
                            <p class="mb-0">Everything in this course is on the website. Please
                                contact us on 07405 073764 if you cannot find it.</p>
                        @endif
                    </div>
                @empty
                    <div class="rbt-service rbt-service-2 radius-10">
                        <h4 class="title">You have not purchased anything yet</h4>
                        <p>
                            Choose the Life in the UK Course or the 24 Mock Tests package and your course will
                            appear here the moment Stripe confirms your payment.
                        </p>
                        <a href="{{ route('home') }}#paid-courses"
                            class="rbt-btn btn-border-gradient radius-round btn-sm">
                            <span>Browse the courses</span>
                        </a>
                    </div>
                @endforelse

                @if ($pendingPurchases->isNotEmpty())
                    <div class="rbt-service rbt-service-2 radius-10 mt--30">
                        <h4 class="title">Recent checkout attempts</h4>
                        <p>These have not completed. No payment has been taken for them.</p>
                        <ul class="list-unstyled mb-0">
                            @foreach ($pendingPurchases as $purchase)
                                <li class="d-flex justify-content-between align-items-center py-2">
                                    <span>
                                        {{ $purchase->course->name ?? 'Course' }}
                                        <small class="ms-2">{{ $purchase->statusLabel() }}</small>
                                    </span>
                                    @if ($purchase->course)
                                        <a href="{{ route('courses.show', $purchase->course) }}"
                                            class="btn btn-sm btn-outline-primary">Try again</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

@endsection
