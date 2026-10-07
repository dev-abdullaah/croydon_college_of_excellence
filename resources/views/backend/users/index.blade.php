@extends('backend.layouts.app')
@section('title', 'System Users & Administrators')

@section('page')
<!--begin::Toolbar-->
<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
    <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
        <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
            <h1 class="page-heading d-flex text-dark fw-bold fs-2 flex-column justify-content-center my-0">👥 System Users & Administrators</h1>
            <span class="text-muted fs-7 fw-semibold mt-1">User directory, administrator roles, and access controls</span>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary fw-bold">+ Add User</a>
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

        <div class="card card-flush">
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <div class="card-title">
                    <h3 class="fw-bold text-gray-800 m-0">All Administrative Users ({{ $users->total() }})</h3>
                </div>
                <div class="card-toolbar">
                    <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Add User
                    </a>
                </div>
            </div>

            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-striped table-hover fs-7 gy-3">
                        <thead>
                            <tr class="text-start text-gray-700 fw-bold fs-7 text-uppercase gs-0 bg-secondary">
                                <th class="min-w-40px text-start ps-3">#</th>
                                <th class="min-w-60px text-start">Avatar</th>
                                <th class="min-w-150px text-start">Name</th>
                                <th class="min-w-120px text-start">Username</th>
                                <th class="min-w-160px text-start">Email</th>
                                <th class="min-w-120px text-center">Assigned Role</th>
                                <th class="min-w-120px text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            @forelse($users as $user)
                            <tr>
                                <td class="ps-3">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="symbol symbol-35px symbol-circle">
                                        @if($user->photo && file_exists(public_path('img/' . $user->photo)))
                                        <img src="{{ asset('img/' . $user->photo) }}" alt="{{ $user->name }}" style="object-fit: cover;" />
                                        @else
                                        <span class="symbol-label bg-light-primary text-primary fw-bold fs-7">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="text-gray-800 fw-bold fs-6">
                                        {{ $user->name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="font-monospace text-muted fs-7">{{ $user->username }}</span>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td class="text-center">
                                    @php
                                        $badgeClass = match($user->role) {
                                            'super_admin' => 'bg-dark text-white',
                                            'admin'       => 'bg-primary text-white',
                                            default       => 'bg-secondary text-dark',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} fw-bold fs-8 px-3 py-2 text-uppercase">
                                        {{ str_replace('_', ' ', $user->role) }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-icon btn-light-primary" style="width: 32px; height: 32px;" title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if(Auth::guard('admin')->id() !== $user->id)
                                        <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete user {{ $user->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon btn-light-danger" style="width: 32px; height: 32px;" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">No users found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
