@extends('backend.layouts.app')
@section('title', 'Manage Purchases')

@section('page')
<div class="text-center mt-2">
    <h1 class="m-0">Manage Purchases</h1>
</div>

<div id="kt_app_content" class="app-content flex-column-fluid">
    <div id="kt_app_content_container" class="app-container container-xxl">
        <div class="card card-flush">
            <div class="mt-5 pt-5"></div>
            <div class="card-body pt-0">
                <!-- Filter Section -->
                <div class="card card-flush mb-4 shadow-sm border border-gray-200 purchase-filter-card">
                    <div class="card-body py-4 px-4 px-lg-6">
                        <form method="GET" action="{{ route('admin.purchases.index') }}" id="filterForm" class="purchase-filter-form">
                            <div class="row g-3 align-items-center">
                                <!-- Search Field -->
                                <div class="col-xl-4 col-lg-4 col-md-12">
                                    <div class="search-input-wrapper">
                                        <input type="text"
                                            class="form-control form-control-sm"
                                            name="search"
                                            id="purchaseSearchInput"
                                            placeholder="Search student name, email, stripe ref..."
                                            value="{{ request('search') }}"
                                            autocomplete="off">
                                        <span class="search-icon">
                                            <i class="bi bi-search fs-5"></i>
                                        </span>
                                    </div>
                                </div>

                                <!-- Course Filter -->
                                <div class="col-xl-3 col-lg-3 col-md-4 col-6">
                                    <select class="form-select form-select-sm" name="course_id" id="courseFilter">
                                        <option value="">All Courses</option>
                                        @foreach($courses as $courseOption)
                                            <option value="{{ $courseOption->id }}" {{ request('course_id') == $courseOption->id ? 'selected' : '' }}>
                                                {{ $courseOption->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Status Filter -->
                                <div class="col-xl-2 col-lg-2 col-md-3 col-6">
                                    <select class="form-select form-select-sm" name="status" id="statusFilter">
                                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Status</option>
                                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                                        <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>Refunded</option>
                                    </select>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-xl-3 col-lg-3 col-md-5 col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-sm btn-primary px-3 d-inline-flex align-items-center gap-1" title="Apply Filter">
                                        <i class="fas fa-filter fa-xs"></i> <span>Filter</span>
                                    </button>
                                    <a href="{{ route('admin.purchases.index') }}" class="btn btn-sm btn-light border px-2 d-inline-flex align-items-center gap-1 text-gray-700" title="Reset all filters">
                                        <i class="fas fa-undo-alt fa-xs"></i> <span>Reset</span>
                                    </a>
                                    <a href="{{ route('admin.purchases.export', request()->query()) }}" class="btn btn-sm btn-light-success border px-3 d-inline-flex align-items-center gap-1" title="Export current filtered purchases to CSV">
                                        <i class="fas fa-file-csv fa-xs"></i> <span>Export</span>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Status Badges - Modern Rounded Design from Snapkart -->
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.purchases.index', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}"
                            class="status-badge {{ request('status', 'all') == 'all' ? 'active all' : 'all' }}">
                            <i class="fas fa-chart-line fa-xs me-1"></i>
                            All Purchases
                            <span class="badge-count">{{ $counts['all'] ?? 0 }}</span>
                        </a>

                        <a href="{{ route('admin.purchases.index', array_merge(request()->except('status', 'page'), ['status' => 'paid'])) }}"
                            class="status-badge {{ request('status') == 'paid' ? 'active confirmed' : 'confirmed' }}">
                            <i class="fas fa-check-circle fa-xs me-1"></i>
                            Paid
                            <span class="badge-count">{{ $counts['paid'] ?? 0 }}</span>
                        </a>

                        <a href="{{ route('admin.purchases.index', array_merge(request()->except('status', 'page'), ['status' => 'pending'])) }}"
                            class="status-badge {{ request('status') == 'pending' ? 'active pending' : 'pending' }}">
                            <i class="fas fa-clock fa-xs me-1"></i>
                            Pending
                            <span class="badge-count">{{ $counts['pending'] ?? 0 }}</span>
                        </a>

                        <a href="{{ route('admin.purchases.index', array_merge(request()->except('status', 'page'), ['status' => 'failed'])) }}"
                            class="status-badge {{ request('status') == 'failed' ? 'active cancelled' : 'cancelled' }}">
                            <i class="fas fa-times-circle fa-xs me-1"></i>
                            Failed
                            <span class="badge-count">{{ $counts['failed'] ?? 0 }}</span>
                        </a>
                    </div>
                </div>

                <!-- Purchases Table -->
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                                <th>Purchase #</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @forelse($purchases as $purchase)
                            @php
                                $badgeClass = match($purchase->status) {
                                    'paid'      => 'badge-light-success',
                                    'pending'   => 'badge-light-warning',
                                    'failed'    => 'badge-light-danger',
                                    'refunded'  => 'badge-light-dark',
                                    default     => 'badge-light-secondary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.purchases.show', $purchase) }}" class="text-gray-800 text-hover-primary fw-bold">
                                        #{{ $purchase->id }}
                                    </a>
                                    @if($purchase->stripe_checkout_session_id)
                                        <div class="text-muted font-monospace fs-8">{{ $purchase->stripe_checkout_session_id }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-35px symbol-circle me-3">
                                            <span class="symbol-label bg-light-primary text-primary fw-bold fs-7">
                                                {{ strtoupper(substr($purchase->student->name ?? 'S', 0, 1)) }}
                                            </span>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="text-gray-800 fw-bold fs-7">{{ $purchase->student->name ?? 'Guest' }}</span>
                                            <span class="text-muted fs-8">{{ $purchase->student->email ?? '' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-gray-800 fw-semibold">{{ $purchase->course->name ?? 'Deleted Course' }}</span>
                                </td>
                                <td class="fw-bold text-dark">
                                    £ {{ number_format($purchase->amount / 100, 2) }}
                                </td>
                                <td>
                                    <span class="badge {{ $badgeClass }} fw-bold fs-8">
                                        {{ ucfirst($purchase->status) }}
                                    </span>
                                </td>
                                <td class="text-muted fs-7">
                                    {{ $purchase->created_at->format('d M Y, H:i') }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn btn-sm btn-light-primary fw-bold">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">No purchases found matching the criteria.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $purchases->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
