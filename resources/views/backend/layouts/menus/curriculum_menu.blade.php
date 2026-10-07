<div class="menu-item">
    <a class="menu-link {{ request()->routeIs('admin.curriculum.*') ? 'active' : '' }}" href="{{ route('admin.curriculum.index') }}">
        <span class="menu-icon">
            <span class="svg-icon svg-icon-2">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 14H18V4H20C20.6 4 21 4.4 21 5V13C21 13.6 20.6 14 20 14Z" fill="currentColor"/>
                    <path opacity="0.3" d="M17 19H3C2.4 19 2 18.6 2 18V6C2 5.4 2.4 5 3 5H17C17.6 5 18 5.4 18 6V18C18 18.6 17.6 19 17 19Z" fill="currentColor"/>
                </svg>
            </span>
        </span>
        <span class="menu-title">Curriculum &amp; Question Bank</span>
    </a>
</div>
