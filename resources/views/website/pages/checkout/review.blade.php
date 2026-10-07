@extends('website.layouts.master')

@section('content')

<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title text-center">
                    <h2 class="title">Course Admission Application</h2>
                    <p class="mt--10 mb-0">Apply for enrollment. Course fees are arranged directly with our admissions office.</p>
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

                @if($existingPending)
                <div class="alert alert-info d-flex align-items-center p-4 mb-4 radius-10">
                    <i class="feather-info fs-4 me-3 text-primary"></i>
                    <div>
                        <strong>Pending Application on File:</strong> You submitted an admission request on {{ $existingPending->requested_at?->format('d M Y, H:i') }}. Our office will contact you shortly on <strong>{{ $existingPending->contact_phone }}</strong>. You can update your contact phone or notes below.
                    </div>
                </div>
                @endif

                <div class="text-center mb-4">
                    <h2 class="title mb-1">Check Your Order</h2>
                    <p class="text-muted">Review your course selection and confirm your details</p>
                </div>

                <div class="rbt-service rbt-service-2 radius-10 shadow-sm border">

                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            @if ($course->badge)
                                <span class="badge bg-primary">{{ $course->badge }}</span>
                            @endif
                            <h3 class="title mt--10 mb-0">{{ $course->name }}</h3>
                        </div>
                        <div class="text-end">
                            <span style="font-size: 2.25rem; font-weight: 700; line-height: 1;" class="text-primary">
                                {{ $course->formattedPrice() }}
                            </span>
                            <span class="d-block mt--5 text-muted small">course tuition fee</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h4 class="title fs-5 mb-3">🎓 What is included</h4>
                            <ul class="rbt-list-style-1 list-unstyled mb-0">
                                @foreach (($course->features ?? []) as $feature)
                                    <li class="d-flex mb-2">
                                        <i class="feather-check text-success"></i>
                                        <span class="ms-2">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="col-lg-6">
                            <h4 class="title fs-5 mb-3">📋 What happens next</h4>
                            <ul class="rbt-list-style-1 list-unstyled mb-0">
                                <li class="d-flex mb-2">
                                    <i class="feather-user-check text-primary"></i>
                                    <span class="ms-2">Submit your admission application below with your direct phone number.</span>
                                </li>
                                <li class="d-flex mb-2">
                                    <i class="feather-phone-call text-primary"></i>
                                    <span class="ms-2">Our admissions officer will contact you to confirm enrollment and guide payment.</span>
                                </li>
                                <li class="d-flex mb-2">
                                    <i class="feather-unlock text-primary"></i>
                                    <span class="ms-2">Once fee payment is confirmed, your course and mock tests are instantly unlocked!</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Application Form -->
                    <form method="POST" action="{{ route('checkout.store', $course) }}">
                        @csrf

                        <h4 class="title fs-5 mb-3">Learner Contact & Payment Preference</h4>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Learner Full Name</label>
                                <input type="text" class="form-control bg-light" value="{{ auth()->user()->name }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Account Email</label>
                                <input type="email" class="form-control bg-light" value="{{ auth()->user()->email }}" readonly>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold required">Contact Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone"
                                    class="form-control @error('phone') is-invalid @enderror"
                                    placeholder="e.g. 07405 123456"
                                    value="{{ old('phone', auth()->user()->phone ?? $existingPending?->contact_phone) }}" required>
                                <div class="form-text small">Our admissions team will call this number to arrange fee payment.</div>
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold required">Preferred Payment Method <span class="text-danger">*</span></label>
                                @php
                                    $selectedMethod = old('payment_method', $existingPending?->payment_method ?? 'bank_transfer');
                                @endphp
                                <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                    <option value="bank_transfer" {{ $selectedMethod === 'bank_transfer' ? 'selected' : '' }}>🏦 Bank Transfer / BACS (Recommended)</option>
                                    <option value="cash" {{ $selectedMethod === 'cash' ? 'selected' : '' }}>💵 In-Person Cash (Croydon Campus)</option>
                                    <option value="phone_card" {{ $selectedMethod === 'phone_card' ? 'selected' : '' }}>💳 Card Payment Over the Phone</option>
                                    <option value="other" {{ $selectedMethod === 'other' ? 'selected' : '' }}>🏢 Employer / Sponsor Billing</option>
                                </select>
                                <div class="form-text small">Select how you intend to settle your course fee.</div>
                                @error('payment_method')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Special Notes or Best Time to Call (Optional)</label>
                            <textarea name="learner_notes" rows="2" class="form-control" placeholder="e.g. Best time to call is after 2 PM, or any notes for the administrator...">{{ old('learner_notes', $existingPending?->learner_notes) }}</textarea>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox"
                                name="consent" value="1" id="consent" required
                                {{ old('consent') ? 'checked' : '' }}>
                            <label class="form-check-label small" for="consent">
                                {!! config('courses.consent_text') !!}
                            </label>
                            @error('consent')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="rbt-btn-wrapper d-flex flex-wrap gap-3 align-items-center">
                            <button type="submit"
                                class="rbt-btn btn-gradient radius-round btn-md justify-content-center text-center">
                                <span><i class="feather-send me-2"></i> Submit Admission Application</span>
                            </button>
                            <a href="{{ route('courses.show', $course) }}"
                                class="rbt-btn btn-border-gradient radius-round btn-md justify-content-center text-center">
                                <span>Back to Course Overview</span>
                            </a>
                        </div>
                    </form>
                </div>

                <p class="text-center mt--20 text-muted">
                    Questions about enrollment? Call our admissions desk on <a href="tel:+447405073764" class="fw-bold">+44 7405 073764</a>.
                </p>

            </div>
        </div>
    </div>
</div>

@endsection
