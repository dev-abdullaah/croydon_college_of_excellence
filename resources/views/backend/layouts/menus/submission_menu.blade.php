<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.submissions.*') ? 'active' : '' }}" href="{{ route('admin.submissions.index') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.3" d="M21 19H3C2.4 19 2 18.6 2 18V6C2 5.4 2.4 5 3 5H21C21.6 5 22 5.4 22 6V18C22 18.6 21.6 19 21 19Z" fill="currentColor"/>
                    <path d="M21 5L12 12L3 5H21Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
        <span class="menu-title">Inquiries & Leads</span>
        @php
            $unreadSubmissionsCount = \App\Models\ContactSubmission::unread()->count();
        @endphp
        @if($unreadSubmissionsCount > 0)
            <span class="badge badge-danger badge-circle fw-bold fs-8">{{ $unreadSubmissionsCount }}</span>
        @endif
    </a>
</div>
