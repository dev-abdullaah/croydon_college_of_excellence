@extends('website.layouts.master')
@section('title', 'Verify Certificate — Croydon College of Excellence')

@section('content')
<div class="rbt-breadcrumb-default ptb--50 ptb_md--30 ptb_sm--30 bg-gradient-1">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="breadcrumb-inner text-center">
                    <h2 class="title">Public Credential Verification</h2>
                    <ul class="page-list justify-content-center">
                        <li class="rbt-breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li><div class="icon-right"><i class="feather-chevron-right"></i></div></li>
                        <li class="rbt-breadcrumb-item active">Verify Credential</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="rbt-section-gap bg-color-white">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">

                <div class="rbt-service rbt-service-2 radius-10 shadow-sm border p-4 p-md-5 text-center">
                    <div class="mb-3">
                        <i class="feather-award text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="title mb-2">Croydon College Credential Registry</h3>
                    <p class="text-muted small mb-4">
                        Enter the unique certificate serial number (e.g. <code>CCE-2026-ABC123</code>) printed on the document or transcript to confirm its official authenticity.
                    </p>

                    <form method="GET" action="{{ route('certificates.lookup') }}">
                        <div class="input-group mb-4">
                            <input type="text" name="number" class="form-control form-control-lg text-center fw-bold"
                                   placeholder="CCE-2026-XXXXXX" required
                                   style="letter-spacing: 2px; text-transform: uppercase;">
                        </div>

                        <button type="submit" class="rbt-btn btn-gradient radius-round btn-md w-100 justify-content-center">
                            <span><i class="feather-check-circle me-1"></i> Verify Credential Authenticity</span>
                        </button>
                    </form>

                    <div class="mt-4 pt-3 border-top text-muted small">
                        <i class="feather-lock me-1"></i> Official electronic verification registry supported by Croydon College of Excellence.
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
