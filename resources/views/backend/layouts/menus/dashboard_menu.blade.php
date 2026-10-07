<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2" y="2" width="9" height="9" rx="2" fill="currentColor" />
                    <rect opacity="0.5" x="13" y="2" width="9" height="9" rx="2" fill="currentColor" />
                    <rect opacity="0.5" x="13" y="13" width="9" height="9" rx="2" fill="currentColor" />
                    <rect opacity="0.5" x="2" y="13" width="9" height="9" rx="2" fill="currentColor" />
                </svg>
            </span>
        </span>
        <span class="menu-title">Dashboard</span>
    </a>
</div>
