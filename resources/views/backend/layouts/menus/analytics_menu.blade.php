<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}" href="{{ route('admin.analytics.index') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.3" d="M14 10V20H10V10H14ZM6 14V20H2V14H6ZM22 4V20H18V4H22Z" fill="currentColor"/>
                    <path d="M20 2H4C2.9 2 2 2.9 2 4V6H22V4C22 2.9 21.1 2 20 2Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
        <span class="menu-title">Analytics & Reports</span>
    </a>
</div>
