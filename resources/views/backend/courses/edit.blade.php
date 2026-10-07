@extends('backend.layouts.app')
@section('title', 'Edit Course: ' . $course->name)

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                ✏️ Edit Course: {{ $course->name }}
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Configure pricing, marketing copy, feature bullet points, and Stripe gateway IDs</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-light">← Back to Courses</a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        @if (isset($errors) && $errors->any())
        <div class="alert alert-danger mb-5">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Stripe Price Warning Alert -->
        <div class="alert alert-warning d-flex align-items-center p-5 mb-6">
            <i class="fas fa-exclamation-triangle fs-2hx text-warning me-4"></i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-warning fw-bold">Stripe Price ID Synchronization Notice</h4>
                <span class="fs-7 text-gray-800">
                    Changing the price below updates the display and catalog price on the website. In Stripe Checkout, customers are charged according to the configured <strong>Stripe Price ID</strong>. When adjusting prices, ensure you update or configure a matching Stripe Price ID to prevent transaction discrepancies.
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.courses.update', $course->id) }}" class="form">
            @csrf
            @method('PUT')

            <div class="row g-5 g-xl-8">
                <!-- Main Form Column -->
                <div class="col-xl-8">
                    <div class="card card-flush shadow-sm mb-5">
                        <div class="card-header pt-5">
                            <h3 class="card-title fw-bold text-gray-800">Course Metadata</h3>
                        </div>
                        <div class="card-body pt-0">
                            <!-- Course Name -->
                            <div class="mb-5">
                                <label class="required form-label fw-bold">Course Title</label>
                                <input type="text" name="name" class="form-control form-control-solid" value="{{ old('name', $course->name) }}" required />
                            </div>

                            <!-- Badge -->
                            <div class="mb-5">
                                <label class="form-label fw-bold">Badge Text (Optional)</label>
                                <input type="text" name="badge" class="form-control form-control-solid" value="{{ old('badge', $course->badge) }}" placeholder="e.g. Most Popular, Practice Pack" />
                            </div>

                            <!-- Short Description -->
                            <div class="mb-5">
                                <label class="form-label fw-bold">Short Summary (Tagline)</label>
                                <textarea name="short_description" class="form-control form-control-solid" rows="2">{{ old('short_description', $course->short_description) }}</textarea>
                            </div>

                            <!-- Full Description -->
                            <div class="mb-5">
                                <label class="form-label fw-bold">Full Course Description</label>
                                <textarea name="description" class="form-control form-control-solid" rows="4">{{ old('description', $course->description) }}</textarea>
                            </div>

                            <!-- Features List -->
                            <div class="mb-5">
                                <label class="form-label fw-bold">Marketing Feature Bullet Points (One per line)</label>
                                @php
                                    $featuresText = is_array($course->features) ? implode("\n", $course->features) : '';
                                @endphp
                                <textarea name="features" class="form-control form-control-solid font-monospace fs-7" rows="5" placeholder="Feature item 1&#10;Feature item 2&#10;Feature item 3">{{ old('features', $featuresText) }}</textarea>
                                <span class="text-muted fs-8">Each line will be displayed as a checkmark bullet point on course cards.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Settings Column -->
                <div class="col-xl-4">
                    <div class="card card-flush shadow-sm mb-5">
                        <div class="card-header pt-5">
                            <h3 class="card-title fw-bold text-gray-800">Pricing & Stripe</h3>
                        </div>
                        <div class="card-body pt-0">
                            <!-- Price in Pounds -->
                            <div class="mb-5">
                                <label class="required form-label fw-bold">Price in Pounds (£)</label>
                                <div class="input-group input-group-solid">
                                    <span class="input-group-text">£</span>
                                    <input type="number" step="0.01" min="0" name="price" class="form-control form-control-solid" value="{{ old('price', $course->priceInPounds()) }}" required />
                                </div>
                                <span class="text-muted fs-8">Stored as {{ $course->price }} pence in the database.</span>
                            </div>

                            <!-- Stripe Price ID Override -->
                            <div class="mb-5">
                                <label class="form-label fw-bold">Stripe Price ID Override</label>
                                <input type="text" name="stripe_price_id" class="form-control form-control-solid font-monospace fs-7" value="{{ old('stripe_price_id', $course->stripe_price_id) }}" placeholder="price_1..." />
                                <span class="text-muted fs-8">Overrides config fallback (<code>config/stripe.php</code>).</span>
                            </div>

                            <!-- Sort Order -->
                            <div class="mb-5">
                                <label class="form-label fw-bold">Display Sort Order</label>
                                <input type="number" name="sort_order" class="form-control form-control-solid" value="{{ old('sort_order', $course->sort_order) }}" />
                            </div>

                            <!-- Active Status -->
                            <div class="mb-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $course->is_active) ? 'checked' : '' }} />
                                    <span class="form-check-label fw-bold text-gray-800">Publish course (Active)</span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 fw-bold">Save Course Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>
<!--end::Content-->
@endsection
