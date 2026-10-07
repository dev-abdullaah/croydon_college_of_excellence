@extends('backend.layouts.app')
@section('title', 'Edit User: ' . $user->name)

@section('page')
<!--begin::Content Header-->
<div class="text-center mt-2">
    <h1 class="m-0">Edit User: {{ $user->name }}</h1>
</div>
<!--end::Content Header-->

<div id="kt_app_content" class="app-content flex-column-fluid">
    <!--begin::Content container-->
    <div id="kt_app_content_container" class="app-container container-xxl">
        <!--begin::Card-->
        <div class="card card-flush">
            <!--begin::Card header-->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <div class="card-toolbar flex-row-fluid gap-5">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Manage Users</a>
                </div>
            </div>
            <!--end::Card header-->

            <div class="card-body pt-0">
                @if (isset($errors) && $errors->any())
                <div class="alert alert-danger p-4 mb-5">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!--begin::Form-->
                <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                    @method('PUT')
                    @csrf

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Full Name</label>
                        <div class="col-md-10">
                            <input type="text" name="name" class="form-control" placeholder="Full Name" value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Username</label>
                        <div class="col-md-10">
                            <input type="text" name="username" class="form-control" placeholder="Username" value="{{ old('username', $user->username) }}" required>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Email Address</label>
                        <div class="col-md-10">
                            <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Assigned Role</label>
                        <div class="col-md-10">
                            <select name="role" class="form-select" required>
                                <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator</option>
                                <option value="co_admin" {{ old('role', $user->role) === 'co_admin' ? 'selected' : '' }}>Co-Administrator</option>
                                <option value="super_admin" {{ old('role', $user->role) === 'super_admin' ? 'selected' : '' }}>Super Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label fs-4">Account Status</label>
                        <div class="col-md-10 d-flex align-items-center">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} />
                                <span class="form-check-label fw-bold text-gray-800">Account Active (Permitted to sign in)</span>
                            </label>
                        </div>
                    </div>

                    <div class="separator my-6"></div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label fs-4">Change Password</label>
                        <div class="col-md-10">
                            <input type="password" name="password" class="form-control" placeholder="New Password (leave blank to keep current)">
                            <input type="password" name="password_confirmation" class="form-control mt-2" placeholder="Confirm new password">
                        </div>
                    </div>

                    <!--begin::Card toolbar-->
                    <div class="card-toolbar flex-row-fluid d-flex gap-3">
                        <button type="submit" class="btn btn-primary">Update User</button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                    <!--end::Card toolbar-->
                </form>
                <!--end::Form-->
            </div>
        </div>
    </div>
</div>
@endsection
