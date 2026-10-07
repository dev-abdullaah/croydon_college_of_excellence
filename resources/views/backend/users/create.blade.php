@extends('backend.layouts.app')
@section('title', 'Add User')

@section('page')
<!--begin::Content Header-->
<div class="text-center mt-2">
    <h1 class="m-0">Add User</h1>
</div>
<!--end::Content Header-->

<div id="kt_app_content" class="app-content flex-column-fluid">
    <!--begin::Content container-->
    <div id="kt_app_content_container" class="app-container container-xxl">
        <!--begin::Card-->
        <div class="card card-flush">
            <!--begin::Card header-->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5">
                <!--begin::Card toolbar-->
                <div class="card-toolbar flex-row-fluid gap-5">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Manage Users</a>
                </div>
                <!--end::Card toolbar-->
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
                <form action="{{ route('admin.users.store') }}" method="POST">
                    @method('POST')
                    @csrf

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Full Name</label>
                        <div class="col-md-10">
                            <input type="text" name="name" class="form-control" placeholder="Full Name" value="{{ old('name') }}" required>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Username</label>
                        <div class="col-md-10">
                            <input type="text" name="username" class="form-control" placeholder="Username" value="{{ old('username') }}" required>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Email Address</label>
                        <div class="col-md-10">
                            <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ old('email') }}" required>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Assigned Role</label>
                        <div class="col-md-10">
                            <select name="role" class="form-select" required>
                                <option value="admin" {{ old('role', 'admin') === 'admin' ? 'selected' : '' }}>Administrator</option>
                                <option value="co_admin" {{ old('role') === 'co_admin' ? 'selected' : '' }}>Co-Administrator</option>
                                <option value="super_admin" {{ old('role') === 'super_admin' ? 'selected' : '' }}>Super Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-5">
                        <label class="col-md-2 col-form-label required fs-4">Password</label>
                        <div class="col-md-10">
                            <input type="password" name="password" class="form-control" placeholder="Password (Minimum 8 characters)" required>
                            <input type="password" name="password_confirmation" class="form-control mt-2" placeholder="Confirm password" required>
                        </div>
                    </div>

                    <!--begin::Card toolbar-->
                    <div class="card-toolbar flex-row-fluid d-flex gap-3">
                        <button type="submit" class="btn btn-primary">Submit</button>
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
