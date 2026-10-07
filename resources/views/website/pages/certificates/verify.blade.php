@extends('website.layouts.master')
@section('title', 'Certificate Verification — Croydon College of Excellence')

@section('content')
<div class="rbt-breadcrumb-default ptb--50 ptb_md--30 ptb_sm--30 bg-gradient-1">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="breadcrumb-inner text-center">
                    <h2 class="title">Official Credential Verification</h2>
                    <ul class="page-list justify-content-center">
                        <li class="rbt-breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li><div class="icon-right"><i class="feather-chevron-right"></i></div></li>
                        <li class="rbt-breadcrumb-item active">Certificate Registry</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="rbt-section-gap bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-md-11">

                @if(! $certificate)
                    {{-- NOT FOUND / INVALID --}}
                    <div class="rbt-card text-center p-5 shadow-sm border border-danger radius-10">
                        <div class="mb-4">
                            <i class="feather-alert-triangle text-danger" style="font-size: 3.5rem;"></i>
                        </div>
                        <h3 class="title text-danger mb-2">Record Not Found</h3>
                        <p class="text-muted fs-6 mb-4">
                            No active credential exists with certificate reference: <code class="fw-bold fs-6">{{ $queryNumber }}</code>.
                        </p>
                        <p class="small text-muted mb-4">
                            Please double-check the serial number printed on the certificate, or contact Croydon College of Excellence Academic Registry directly.
                        </p>

                        <div class="d-flex justify-content-center gap-3">
                            <a href="{{ route('certificates.lookup') }}" class="rbt-btn btn-sm btn-gradient radius-round">
                                Try Another Search
                            </a>
                            <a href="{{ route('static', 'contact') }}" class="rbt-btn btn-sm btn-border-gradient radius-round">
                                Contact Academic Office
                            </a>
                        </div>
                    </div>

                @elseif($certificate->isRevoked())
                    {{-- REVOKED CERTIFICATE --}}
                    <div class="rbt-card text-center p-5 shadow-sm border border-danger bg-light-danger radius-10">
                        <div class="mb-3">
                            <i class="feather-slash text-danger" style="font-size: 3.5rem;"></i>
                        </div>
                        <span class="badge bg-danger fs-6 px-3 py-2 mb-3">REVOKED CREDENTIAL</span>
                        <h3 class="title text-dark mb-2">Notice of Certificate Revocation</h3>
                        <p class="text-danger fw-bold fs-6 mb-2">
                            Certificate Reference: {{ $certificate->certificate_number }}
                        </p>
                        <div class="p-3 bg-white rounded border border-danger text-start max-width-500 mx-auto mb-4">
                            <p class="mb-1 text-muted small"><strong>Original Recipient:</strong> {{ $certificate->student->name }}</p>
                            <p class="mb-1 text-muted small"><strong>Course:</strong> {{ $certificate->course->name }}</p>
                            <p class="mb-1 text-muted small"><strong>Revocation Date:</strong> {{ $certificate->revoked_at?->format('d F Y, H:i') }}</p>
                            @if($certificate->revocation_reason)
                                <p class="mb-0 text-danger small"><strong>Reason:</strong> {{ $certificate->revocation_reason }}</p>
                            @endif
                        </div>
                        <p class="text-muted small">
                            This certificate was declared null and void by the Academic Board of Croydon College of Excellence and does not represent an active qualification.
                        </p>
                    </div>

                @else
                    {{-- VALID / VERIFIED CERTIFICATE --}}
                    <div class="certificate-container p-4 p-md-5 rounded shadow-lg border position-relative" style="background: #ffffff; border: 3px double #d4af37 !important;">

                        {{-- Top Verified Ribbon --}}
                        <div class="text-center mb-4">
                            <span class="badge bg-success fs-7 px-4 py-2 text-uppercase letter-spacing-1 shadow-sm">
                                <i class="feather-check-circle me-1"></i> Authenticated &amp; Verified Credential
                            </span>
                        </div>

                        {{-- College Header --}}
                        <div class="text-center mb-4">
                            <img src="{{ asset('assets/images/logo/croydon-college-logo.png') }}"
                                 alt="Croydon College of Excellence"
                                 style="max-height: 80px;" class="mb-3"
                                 onerror="this.src='{{ asset('admin-assets/media/logos/logo-full.png') }}'">
                            <h2 class="title fw-bold text-dark mb-1" style="font-family: Georgia, serif; letter-spacing: 1px;">
                                CROYDON COLLEGE OF EXCELLENCE
                            </h2>
                            <p class="text-muted small text-uppercase letter-spacing-2 mb-0">
                                London, United Kingdom &middot; Academic Credential Registry
                            </p>
                        </div>

                        <hr style="border-top: 1px solid #d4af37; opacity: 0.5;" class="my-4">

                        {{-- Certificate Statement --}}
                        <div class="text-center py-2">
                            <p class="text-muted text-uppercase letter-spacing-2 fs-7 mb-2">This is to officially certify that</p>
                            <h1 class="display-6 fw-bold text-dark mb-3" style="font-family: Georgia, serif; color: #1a202c !important;">
                                {{ $certificate->student->name }}
                            </h1>
                            <p class="text-muted fs-6 mb-2">has successfully completed the curriculum and fulfilled all academic requirements for</p>
                            <h3 class="title text-primary fw-bold mb-3" style="font-size: 1.5rem;">
                                {{ $certificate->course->name }}
                            </h3>
                            @if($certificate->grade)
                                <div class="mb-3">
                                    <span class="badge bg-light-primary text-primary fs-7 px-3 py-2 border">
                                        Achievement: <strong>{{ $certificate->grade }}</strong>
                                    </span>
                                </div>
                            @endif
                            <p class="text-muted small mb-0">
                                Awarded on <strong>{{ $certificate->issued_at->format('d F Y') }}</strong>
                            </p>
                        </div>

                        <hr style="border-top: 1px solid #d4af37; opacity: 0.5;" class="my-4">

                        {{-- Verification Footprint --}}
                        <div class="row align-items-center g-3 pt-2">
                            <div class="col-md-7 text-start">
                                <div class="fs-7 text-muted">
                                    <div><strong>Certificate Serial:</strong> <code>{{ $certificate->certificate_number }}</code></div>
                                    <div><strong>Digital Verification Hash:</strong> <code class="small text-break">{{ $certificate->verification_hash }}</code></div>
                                    <div><strong>Authorized By:</strong> {{ $certificate->issuedBy->name ?? 'Office of Academic Affairs' }}</div>
                                </div>
                            </div>
                            <div class="col-md-5 text-md-end text-center">
                                <div class="d-inline-block text-center p-2 border rounded bg-light">
                                    <div class="text-success fw-bold fs-7 mb-1">
                                        <i class="feather-shield text-success me-1"></i> SECURE QR AUDIT
                                    </div>
                                    <span class="small text-muted d-block fs-8">Scan or verify at:</span>
                                    <code class="small text-break">{{ url('/verify/' . $certificate->certificate_number) }}</code>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Actions: Print & Back --}}
                    <div class="text-center mt-4 d-flex justify-content-center gap-3 no-print">
                        <button onclick="window.print()" class="rbt-btn btn-sm btn-gradient radius-round">
                            <i class="feather-printer me-1"></i> Print / Save Certificate PDF
                        </button>
                        <a href="{{ route('certificates.lookup') }}" class="rbt-btn btn-sm btn-border-gradient radius-round">
                            <i class="feather-search me-1"></i> Verify Another Certificate
                        </a>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print, header, footer, .rbt-breadcrumb-default {
        display: none !important;
    }
    .certificate-container {
        border: 2px solid #000 !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 40px !important;
    }
}
</style>
@endsection
