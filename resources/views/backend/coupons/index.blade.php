@extends('backend.layouts.app')
@section('title', 'Promotional Coupons & Discounts')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">
                🏷️ Promotional Coupons & Discounts
            </h1>
            <span class="text-muted fs-7 fw-semibold mt-1">Manage promotional discount campaigns, percentages, fixed vouchers, and enrollment boosters</span>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="{{ route('admin.coupons.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus fa-xs me-1"></i> Create Coupon
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">

        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center p-4 mb-5">
            <i class="fas fa-check-circle fs-3 text-success me-3"></i>
            <span class="fw-semibold">{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center p-4 mb-5">
            <i class="fas fa-exclamation-circle fs-3 text-danger me-3"></i>
            <span class="fw-semibold">{{ session('error') }}</span>
        </div>
        @endif

        <!-- KPI Mini Cards -->
        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-4 col-md-6">
                <div class="card card-flush bg-light-primary border border-primary border-dashed">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7">Total Promo Campaigns</span>
                        <div class="fs-2hx fw-bold text-primary mt-1">{{ number_format($stats['total']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card card-flush bg-light-success border border-success border-dashed">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7">Active Vouchers</span>
                        <div class="fs-2hx fw-bold text-success mt-1">{{ number_format($stats['active']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card card-flush bg-light-info border border-info border-dashed">
                    <div class="card-body py-4 px-5">
                        <span class="text-muted fw-semibold fs-7">Total Times Redeemed</span>
                        <div class="fs-2hx fw-bold text-info mt-1">{{ number_format($stats['redemptions']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-flush shadow-sm">
            <!-- Filter toolbar -->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <form method="GET" action="{{ route('admin.coupons.index') }}" class="d-flex flex-wrap align-items-center gap-3 w-100">
                    <div class="d-flex align-items-center position-relative my-1">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-solid w-250px ps-4" placeholder="Search coupon code..." />
                    </div>

                    <select name="type" class="form-select form-select-solid w-175px">
                        <option value="">All Types</option>
                        <option value="percentage" {{ request('type') === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                        <option value="fixed" {{ request('type') === 'fixed' ? 'selected' : '' }}>Fixed Amount (£)</option>
                    </select>

                    <select name="status" class="form-select form-select-solid w-150px">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                    </select>

                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    @if(request()->hasAny(['search', 'type', 'status']))
                        <a href="{{ route('admin.coupons.index') }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                </form>
            </div>

            <!-- Table -->
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-striped table-hover fs-7 gy-3">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                                <th>Code</th>
                                <th>Discount</th>
                                <th>Scope</th>
                                <th>Usage</th>
                                <th>Min Spend</th>
                                <th>Validity Period</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 fw-semibold">
                            @forelse($coupons as $coupon)
                            <tr>
                                <td>
                                    <span class="badge badge-light-primary fw-bolder fs-7 px-3 py-2 text-uppercase font-monospace">
                                        {{ $coupon->code }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $coupon->discount_type === 'percentage' ? 'badge-light-success' : 'badge-light-info' }} fs-7 fw-bold">
                                        {{ $coupon->formattedDiscount() }}
                                    </span>
                                </td>
                                <td>
                                    @if($coupon->course)
                                        <span class="badge badge-light-dark fs-8 text-truncate mw-200px" title="{{ $coupon->course->name }}">
                                            {{ $coupon->course->name }}
                                        </span>
                                    @else
                                        <span class="badge badge-light-secondary fs-8">All Courses</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-dark fw-bold">{{ $coupon->times_used }}</span>
                                    @if($coupon->max_uses)
                                        <span class="text-muted fs-8">/ {{ $coupon->max_uses }} max</span>
                                    @else
                                        <span class="text-muted fs-8">/ ∞</span>
                                    @endif
                                </td>
                                <td>
                                    @if($coupon->min_spend)
                                        £{{ number_format($coupon->min_spend / 100, 2) }}
                                    @else
                                        <span class="text-muted">None</span>
                                    @endif
                                </td>
                                <td>
                                    @if($coupon->expires_at)
                                        <div class="fs-8">
                                            @if($coupon->expires_at->isPast())
                                                <span class="text-danger fw-bold">Expired {{ $coupon->expires_at->format('M d, Y') }}</span>
                                            @else
                                                <span class="text-muted">Expires {{ $coupon->expires_at->format('M d, Y') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted fs-8">Never expires</span>
                                    @endif
                                </td>
                                <td>
                                    @if($coupon->is_active)
                                        <span class="badge badge-light-success fs-8">Active</span>
                                    @else
                                        <span class="badge badge-light-danger fs-8">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <!-- Toggle Status -->
                                        <form method="POST" action="{{ route('admin.coupons.toggle', $coupon) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-icon btn-sm btn-light-{{ $coupon->is_active ? 'warning' : 'success' }}" title="{{ $coupon->is_active ? 'Deactivate coupon' : 'Activate coupon' }}">
                                                <i class="fas fa-{{ $coupon->is_active ? 'pause' : 'play' }} fa-xs"></i>
                                            </button>
                                        </form>

                                        <!-- Edit -->
                                        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-icon btn-sm btn-light-primary" title="Edit Coupon">
                                            <i class="fas fa-edit fa-xs"></i>
                                        </a>

                                        <!-- Delete -->
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="d-inline" onsubmit="return confirm('Delete coupon {{ $coupon->code }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-sm btn-light-danger" title="Delete coupon">
                                                <i class="fas fa-trash fa-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-10 text-muted">
                                    <i class="fas fa-ticket-alt fs-2x mb-3 d-block text-gray-400"></i>
                                    No promotional coupons found.
                                    <div class="mt-2">
                                        <a href="{{ route('admin.coupons.create') }}" class="btn btn-sm btn-primary">Create Your First Coupon</a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    {{ $coupons->links() }}
                </div>
            </div>
        </div>

    </div>
</div>
<!--end::Content-->
@endsection
