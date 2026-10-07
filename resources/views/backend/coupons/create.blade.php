@extends('backend.layouts.app')
@section('title', 'Create Promotional Coupon')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                Create Promotional Coupon
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Set up a promotional code, percentage discount, or fixed voucher</span>
        </div>
        <div>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left fa-xs me-1"></i> Back to Coupons
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        @if($errors->any())
        <div class="alert alert-danger p-4 mb-5">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="card card-flush shadow-sm">
            <form method="POST" action="{{ route('admin.coupons.store') }}" class="form">
                @csrf

                <div class="card-body p-9">
                    <!-- Coupon Code -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6">Coupon Code</label>
                        <div class="col-lg-8 fv-row">
                            <input type="text" name="code" value="{{ old('code') }}" class="form-control form-control-solid text-uppercase font-monospace fw-bold" placeholder="e.g. CROYDON20 or AUTUMN50" required />
                            <div class="form-text">Will automatically be converted to uppercase. Students enter this at checkout.</div>
                        </div>
                    </div>

                    <!-- Discount Type & Value -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6">Discount Type & Value</label>
                        <div class="col-lg-4 fv-row mb-3 mb-lg-0">
                            <select name="discount_type" id="discount_type" class="form-select form-select-solid" required>
                                <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>Percentage Discount (%)</option>
                                <option value="fixed" {{ old('discount_type') === 'fixed' ? 'selected' : '' }}>Fixed Amount Voucher (£)</option>
                            </select>
                        </div>
                        <div class="col-lg-4 fv-row">
                            <div class="input-group">
                                <span class="input-group-text" id="discount_addon">%</span>
                                <input type="number" step="0.01" min="0.01" name="discount_value" value="{{ old('discount_value') }}" class="form-control form-control-solid" placeholder="e.g. 20 (for 20%) or 15.00 (for £15)" required />
                            </div>
                        </div>
                    </div>

                    <!-- Applicable Course Scope -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Applicable Course Scope</label>
                        <div class="col-lg-8 fv-row">
                            <select name="course_id" class="form-select form-select-solid">
                                <option value="">🌐 All Courses (Site-wide)</option>
                                @foreach($courses as $c)
                                    <option value="{{ $c->id }}" {{ old('course_id') == $c->id ? 'selected' : '' }}>
                                        {{ $c->name }} (£{{ number_format($c->price / 100, 2) }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Leave as "All Courses" or restrict this discount to a specific course.</div>
                        </div>
                    </div>

                    <!-- Minimum Spend -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Minimum Spend (£)</label>
                        <div class="col-lg-8 fv-row">
                            <div class="input-group">
                                <span class="input-group-text">£</span>
                                <input type="number" step="0.01" min="0" name="min_spend" value="{{ old('min_spend') }}" class="form-control form-control-solid" placeholder="e.g. 50.00 (optional)" />
                            </div>
                            <div class="form-text">Minimum course price required to activate the discount. Leave empty for no restriction.</div>
                        </div>
                    </div>

                    <!-- Maximum Usage Limit -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Maximum Uses (Redemptions)</label>
                        <div class="col-lg-8 fv-row">
                            <input type="number" min="1" name="max_uses" value="{{ old('max_uses') }}" class="form-control form-control-solid" placeholder="e.g. 100 (optional)" />
                            <div class="form-text">Total redemptions allowed across all students. Leave empty for unlimited.</div>
                        </div>
                    </div>

                    <!-- Validity Period -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Validity Window</label>
                        <div class="col-lg-4 fv-row mb-3 mb-lg-0">
                            <label class="form-label fs-8 text-muted">Starts At (Optional)</label>
                            <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control form-control-solid" />
                        </div>
                        <div class="col-lg-4 fv-row">
                            <label class="form-label fs-8 text-muted">Expires At (Optional)</label>
                            <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="form-control form-control-solid" />
                        </div>
                    </div>

                    <!-- Active Toggle -->
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Active Immediately</label>
                        <div class="col-lg-8 d-flex align-items-center">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', '1') == '1' ? 'checked' : '' }} />
                                <label class="form-check-label fw-semibold text-gray-700" for="is_active">
                                    Enable coupon immediately upon creation
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9 gap-3">
                    <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('discount_type');
    const addon = document.getElementById('discount_addon');
    function updateAddon() {
        addon.textContent = typeSelect.value === 'percentage' ? '%' : '£';
    }
    typeSelect.addEventListener('change', updateAddon);
    updateAddon();
});
</script>
@endsection
