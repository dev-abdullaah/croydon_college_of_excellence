<div data-kt-menu-trigger="click" class="menu-item menu-accordion {{ request()->routeIs('admin.students*') ? 'here show' : '' }}">
    <!--begin:Menu link-->
    <span class="menu-link">
        <span class="menu-icon">
            <i class="bi bi-people fs-3"></i>
        </span>
        <span class="menu-title">Students</span>
        <span class="menu-arrow"></span>
    </span>
    <!--end:Menu link-->
    <!--begin:Menu sub-->
    <div class="menu-sub menu-sub-accordion">
        <div class="menu-item">
            <a class="menu-link {{ request()->routeIs('admin.students.index') ? 'active' : '' }}" href="{{ route('admin.students.index') }}">
                <span class="menu-bullet">
                    <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Manage Students</span>
            </a>
        </div>
    </div>
    <!--end:Menu sub-->
</div>
