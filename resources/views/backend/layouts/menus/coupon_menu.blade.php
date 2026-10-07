<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.3" d="M20 19.725V18.725C20 18.125 19.6 17.725 19 17.725H5C4.4 17.725 4 18.125 4 18.725V19.725H20Z" fill="currentColor"/>
                    <path d="M4 17.725V5.725C4 5.125 4.4 4.725 5 4.725H19C19.6 4.725 20 5.125 20 5.725V17.725H4ZM12 8.725C10.9 8.725 10 9.625 10 10.725C10 11.825 10.9 12.725 12 12.725C13.1 12.725 14 11.825 14 10.725C14 9.625 13.1 8.725 12 8.725Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
        <span class="menu-title">Coupons & Discounts</span>
    </a>
</div>
