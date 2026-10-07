<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.3" d="M20 14H18V4C18 3.4 17.6 3 17 3H7C6.4 3 6 3.4 6 4V14H4C3.4 14 3 14.4 3 15V19C3 19.6 3.4 20 4 20H20C20.6 20 21 19.6 21 19V15C21 14.4 20.6 14 20 14Z" fill="currentColor"/>
                    <path d="M12 11C13.6569 11 15 9.65685 15 8C15 6.34315 13.6569 5 12 5C10.3431 5 9 6.34315 9 8C9 9.65685 10.3431 11 12 11Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
        <span class="menu-title">Audit Trail</span>
    </a>
</div>
