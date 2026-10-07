@php
    $pendingAdmissionsCount = \App\Models\Admission::pending()->count();
@endphp
<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.admissions.*') ? 'active' : '' }}" href="{{ route('admin.admissions.index') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L1 7L12 12L23 7L12 2Z" fill="currentColor"/>
                    <path opacity="0.3" d="M1 7V17L12 22L23 17V7L12 12L1 7Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
        <span class="menu-title">Student Admissions</span>
        @if($pendingAdmissionsCount > 0)
            <span class="badge badge-warning fs-9 px-2 py-1 ms-auto" title="{{ $pendingAdmissionsCount }} pending admission requests">
                {{ $pendingAdmissionsCount }}
            </span>
        @endif
    </a>
</div>
