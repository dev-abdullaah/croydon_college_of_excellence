@extends('website.layouts.master')

@section('content')

<!-- Hero Section -->
<div class="rbt-conatct-area bg-gradient-9 rbt-section-gap">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="section-title text-lg-start">
                    <span class="subtitle bg-primary-opacity">Life in the UK Test Preparation</span>
                    <h2 class="title">Pass Your Citizenship Test with Confidence</h2>
                    <p class="mt-3 mb-0" style="font-size: 1.15rem; color: var(--color-body);">
                        The complete self-study platform: <strong>10 structured lessons</strong> covering the full official handbook 
                        + <strong>24 timed mock tests</strong> (576 questions) simulating the real Home Office exam.
                    </p>
                </div>
            </div>
            <div class="col-lg-5 text-center mt-4 mt-lg-0">
                <img src="{{ asset('assets/images/course/life_uk_course.png') }}" alt="Life in the UK Course" class="img-fluid" style="max-height: 320px; border-radius: 16px; box-shadow: 0 20px 60px rgba(47, 87, 239, 0.25);">
            </div>
        </div>
    </div>
</div>

<!-- Course Content -->
<div class="rbt-course-details-area ptb-5" style="font-family: var(--font-croydon);">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="course-details-content">
                    
                    <!-- Online Course & Mock Tests Callout -->
                    <div class="alert alert-primary p-4 radius-10 mt-4 mb-4" style="background: linear-gradient(135deg, #f0f4ff, #e8f0fe); border: 1.5px solid #c7d7fe;">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <span class="badge bg-primary mb-2">ONLINE PREPARATION & MOCK TESTS</span>
                                <h4 class="title mb-2">Self-Study Online: 10 Lessons & 24 Full-Length Mock Tests</h4>
                                <p class="mb-0 text-muted">
                                    Prepare directly on our digital learning platform. Read structured lessons, test yourself with 576 practice questions, and review your scores instantly.
                                </p>
                            </div>
                            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                                <a href="{{ route('courses.index') }}" class="rbt-btn btn-gradient radius-round btn-sm mb-2 w-100 justify-content-center text-center">
                                    <span>Browse Online Courses</span>
                                </a>
                                @auth
                                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="feather-user me-1"></i> Go to My Account
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary w-100">
                                        <i class="feather-log-in me-1"></i> Sign In To Your Account
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>

                    <!-- 10 Structured Lessons -->
                    <div class="lz-card p-4 mb-4">
                        <h3 class="title mb-4"><i class="feather-layers me-2 text-primary"></i>10 Structured Lessons</h3>
                        <p class="text-muted mb-4">Full official handbook content broken down into easy-to-read cards:</p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="lz-lesson-item p-4 bg-color-white-off rounded-3 border border-light h-100">
                                    <h5 class="title mb-2">Lessons 1&ndash;2</h5>
                                    <p class="mb-0 fw-medium">Values, Principles & What is the UK</p>
                                    <p class="text-muted small mt-1 mb-0">British values, principles, and the nations that make up the United Kingdom</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="lz-lesson-item p-4 bg-color-white-off rounded-3 border border-light h-100">
                                    <h5 class="title mb-2">Lessons 3&ndash;6</h5>
                                    <p class="mb-0 fw-medium">Complete British History from Early Times to Post-War</p>
                                    <p class="text-muted small mt-1 mb-0">Romans, Anglo-Saxons, Normans, Tudors, Stuarts, Empire, World Wars, and modern Britain</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="lz-lesson-item p-4 bg-color-white-off rounded-3 border border-light h-100">
                                    <h5 class="title mb-2">Lesson 7</h5>
                                    <p class="mb-0 fw-medium">Modern, Thriving Society, Culture & Sport</p>
                                    <p class="text-muted small mt-1 mb-0">UK today: diversity, arts, media, sports, traditions, and cultural life</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="lz-lesson-item p-4 bg-color-white-off rounded-3 border border-light h-100">
                                    <h5 class="title mb-2">Lesson 8</h5>
                                    <p class="mb-0 fw-medium">UK Government, The Law & Your Role</p>
                                    <p class="text-muted small mt-1 mb-0">Parliament, democracy, voting, courts, rights and responsibilities of citizens</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="lz-lesson-item p-4 bg-color-white-off rounded-3 border border-light h-100">
                                    <h5 class="title mb-2">Lessons 9&ndash;10</h5>
                                    <p class="mb-0 fw-medium">Community Involvement, Everyday Life & Traditions</p>
                                    <p class="text-muted small mt-1 mb-0">Local communities, everyday life, customs, festivals, and British traditions</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 24 Timed Mock Tests -->
                    <div class="lz-card p-4 mb-4">
                        <h3 class="title mb-4"><i class="feather-clock me-2 text-success"></i>24 Timed Mock Tests</h3>
                        <p class="text-muted mb-4">Simulate the real Home Office examination format:</p>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="lz-feature-item p-4 bg-success-soft rounded-3 h-100 text-center">
                                    <i class="feather-file-text text-success mb-2" style="font-size: 2.5rem;"></i>
                                    <h5 class="title mb-1">576 Questions</h5>
                                    <p class="text-muted small mb-0">24 separate papers covering all 10 topics</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="lz-feature-item p-4 bg-primary-soft rounded-3 h-100 text-center">
                                    <i class="feather-timer text-primary mb-2" style="font-size: 2.5rem;"></i>
                                    <h5 class="title mb-1">45-Minute Timer</h5>
                                    <p class="text-muted small mb-0">Practice against the clock to build speed</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="lz-feature-item p-4 bg-warning-soft rounded-3 h-100 text-center">
                                    <i class="feather-target text-warning mb-2" style="font-size: 2.5rem;"></i>
                                    <h5 class="title mb-1">Real Pass Mark</h5>
                                    <p class="text-muted small mb-0">18 out of 24 (75%) required to pass</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="lz-feature-item p-4 bg-info-soft rounded-3 h-100 text-center">
                                    <i class="feather-check-circle text-info mb-2" style="font-size: 2.5rem;"></i>
                                    <h5 class="title mb-1">Instant Scoring</h5>
                                    <p class="text-muted small mb-0">Know your result the moment you submit</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="lz-feature-item p-4 bg-danger-soft rounded-3 h-100 text-center">
                                    <i class="feather-help-circle text-danger mb-2" style="font-size: 2.5rem;"></i>
                                    <h5 class="title mb-1">Teacher Answer Keys</h5>
                                    <p class="text-muted small mb-0">Review full explanations and retry anytime</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="text-center pt-2">
                        @auth
                            <a href="{{ route('dashboard') }}" class="rbt-btn btn-gradient radius-round btn-lg me-3">
                                <i class="feather-play me-1"></i> Continue Learning
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rbt-btn btn-gradient radius-round btn-lg me-3">
                                <i class="feather-log-in me-1"></i> Sign In to Start
                            </a>
                            <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg">
                                <i class="feather-user-plus me-1"></i> Create Free Account
                            </a>
                        @endauth
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.lz-lesson-item,
.lz-feature-item {
    transition: all 0.2s ease;
}
.lz-lesson-item:hover,
.lz-feature-item:hover {
    border-color: var(--lz-accent-mid);
    box-shadow: 0 4px 16px rgba(47, 87, 239, 0.1);
    transform: translateY(-2px);
}

/* Soft color variants */
.bg-primary-soft { background: rgba(47, 87, 239, 0.1) !important; }
.bg-success-soft { background: rgba(31, 146, 84, 0.1) !important; }
.bg-warning-soft { background: rgba(255, 143, 36, 0.1) !important; }
.bg-info-soft { background: rgba(27, 162, 219, 0.1) !important; }
.bg-danger-soft { background: rgba(255, 0, 3, 0.1) !important; }
</style>
@endpush

@endsection