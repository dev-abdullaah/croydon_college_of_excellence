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


                {{--
                    Heading and Sign Out share one row at every width. This was
                    a col-md-8 / col-md-4 pair, and below 768px the grid stacks
                    those columns, so the button dropped onto a line of its own
                    under the heading. Flex holds them side by side; the heading
                    is allowed to wrap and steps down in size instead, which is
                    the only way a 34px h3 and a large button both fit across a
                    narrow phone. See `.account-bar`.
                --}}
                <div class="account-bar mb--40">
                    <h3 class="title">Your Enrolled Courses &amp; Admissions</h3>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <a href="{{ route('account.center') }}" class="btn btn-lg btn-outline-primary">
                            <i class="feather-shield me-2"></i> Account Center
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-lg btn-danger">Sign Out</button>
                        </form>
                    </div>
                </div>

                @if ($learnableCourses->isNotEmpty())
                    <h3 class="title mb--20">Start learning</h3>

                    <div class="row g-3 mb--40">
                        @foreach ($learnableCourses as $course)
                            @php
                                $p = $lessonProgress[$course->slug] ?? ['done' => 0, 'total' => 0];
                                $isNew = $highlightCourse && $highlightCourse->id === $course->id;
                            @endphp
                            <div class="col-lg-6 col-12">
                                <a href="{{ route('learn.index', $course) }}"
                                    class="rbt-service rbt-service-2 radius-10 h-100 d-block text-decoration-none text-reset {{ $isNew ? 'dashboard-course--new' : '' }}">
                                    <div class="d-flex align-items-center mb-1 flex-wrap gap-2">
                                        <h4 class="title mb-0">{{ $course->name }}</h4>
                                        @if ($isNew)
                                            <span class="badge bg-warning ms-auto">New</span>
                                        @else
                                            <span class="badge bg-success ms-auto">Admitted (Active)</span>
                                        @endif
                                    </div>
                                    @if ($isNew)
                                        <p class="mb-2 small text-success fw-bold">Payment received. This is yours.</p>
                                    @endif
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
                                        <p class="mb-2 small">Go to your lessons and mock papers to start learning.</p>
                                    @endif
                                    <span class="btn btn-lg btn-primary mt--20">
                                        <span>Start learning</span>
                                    </span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Unfinished Applications / Incomplete purchases --}}
                @if ($unfinishedCourses->isNotEmpty())
                    <div class="rbt-service rbt-service-2 radius-10 border border-primary bg-light mb--30 p-4">
                        <h4 class="title mb-3 fs-5">Complete your purchase</h4>
                        <div class="list-group">
                            @foreach ($unfinishedCourses as $course)
                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                                    <div>
                                        <h5 class="mb-1 fs-6">{{ $course->name }}</h5>
                                        <span class="text-muted small">Tuition Fee: {{ $course->formattedPrice() }}</span>
                                    </div>
                                    <a href="{{ route('checkout.start', $course) }}" class="btn btn-sm btn-primary">
                                        Continue
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Pending Admissions Notice --}}
                @if ($pendingPurchases->isNotEmpty())
                    <div class="rbt-service rbt-service-2 radius-10 border border-warning bg-light-warning mb--30 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-warning text-dark fs-6 me-2">⏳ Pending Admissions</span>
                            <h4 class="title mb-0 fs-5">Awaiting Fee Payment &amp; Office Approval</h4>
                        </div>
                        <p class="text-muted small mb-3">
                            Our admissions team has received your application for the following course(s). An administrator will contact you shortly to confirm your place and guide manual fee payment.
                        </p>
                        <div class="list-group">
                            @foreach ($pendingPurchases as $pending)
                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 bg-white rounded mb-2 border">
                                    <div>
                                        <strong class="d-block">{{ $pending->course->name }}</strong>
                                        <span class="text-muted small">
                                            Fee: {{ number_format($pending->amount / 100, 2) }} {{ strtoupper($pending->currency) }}
                                            &middot; Applied: {{ $pending->requested_at?->format('j M Y, H:i') ?? $pending->created_at->format('j M Y, H:i') }}
                                            @if($pending->contact_phone)
                                                &middot; Contact Phone: {{ $pending->contact_phone }}
                                            @endif
                                        </span>
                                    </div>
                                    <span class="badge bg-warning text-dark">Under Review</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 small text-muted">
                            Need faster activation? Call admissions directly at <a href="tel:+447405073764" class="fw-bold">+44 7405 073764</a>.
                        </div>
                    </div>
                @endif

                @if ($paidPurchases->isEmpty() && $pendingPurchases->isEmpty())
                    <div class="rbt-service rbt-service-2 radius-10 text-center py-5">
                        <h4 class="title">You are not currently enrolled in any courses</h4>
                        <p class="text-muted">
                            Browse our course catalogue and apply for admission. Our admissions office will arrange payment with you and unlock your access.
                        </p>
                        <div class="text-center mt--20">
                            <a href="{{ route('courses.index') }}"
                                class="rbt-btn btn-gradient radius-round d-inline-flex">
                                <span class="btn-text">Browse Courses</span>
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Earned Certificates & Verifiable Credentials --}}
                @if (isset($certificates) && $certificates->isNotEmpty())
                    <div class="rbt-service rbt-service-2 radius-10 border border-success bg-light mb--30 p-4">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-success fs-6 me-2">📜 Official Credentials</span>
                            <h4 class="title mb-0 fs-5">Your Course Completion Certificates</h4>
                        </div>
                        <div class="list-group">
                            @foreach ($certificates as $cert)
                                <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                                    <div>
                                        <h5 class="mb-1 fs-6">{{ $cert->course->name }}</h5>
                                        <span class="text-muted small">
                                            Serial: <code>{{ $cert->certificate_number }}</code> &middot; Awarded {{ $cert->issued_at->format('d M Y') }}
                                            @if($cert->grade)
                                                &middot; Grade: <strong>{{ $cert->grade }}</strong>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ $cert->verificationUrl() }}" target="_blank" class="btn btn-sm btn-outline-success">
                                            <i class="feather-external-link me-1"></i> View / Print Certificate
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Login History --}}
                <div class="rbt-service rbt-service-2 radius-10 mt--30">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="title mb-0">Recent Login Activity</h4>
                    </div>
                    @if ($loginHistory->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Device</th>
                                        <th>Browser</th>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($loginHistory as $login)
                                        <tr>
                                            <td>{{ $login->login_at->format('M j, Y g:i A') }}</td>
                                            <td>{{ $login->device_type }} ({{ $login->operating_system }})</td>
                                            <td>{{ $login->browser }}</td>
                                            <td>{{ $login->ip_address }}</td>
                                            <td>
                                                <span class="badge {{ $login->status === 'success' ? 'bg-success' : 'bg-danger' }}">
                                                    {{ ucfirst($login->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No login history recorded yet.</p>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
