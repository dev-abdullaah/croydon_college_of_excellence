@extends('backend.layouts.app')
@section('title', 'Manage Courses')

@section('page')
<!--begin::Content Header-->
<div class="text-center mt-2">
    <h1 class="m-0">Manage Courses</h1>
</div>
<!--end::Content Header-->

<!--begin::Content-->
<div id="kt_app_content" class="app-content flex-column-fluid">
    <!--begin::Content container-->
    <div id="kt_app_content_container" class="app-container container-xxl">
        <!--begin::Products-->
        <div class="card card-flush">
            <!--begin::Card header-->
            <div class="card-header align-items-center py-5 gap-2 gap-md-5 flex-wrap">
                <!--begin::Card title-->
                <div class="card-title">
                    <span class="fs-4 fw-bold text-gray-800">Courses Catalogue</span>
                </div>
                <!--end::Card title-->
            </div>
            <!--end::Card header-->
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <table class="table align-middle table-striped table-hover fs-6 gy-3">
                    <thead>
                        <tr class="text-start text-black-400 fw-bold fs-6 gs-0">
                            <th class="text-start" style="width: 50px">SL</th>
                            <th class="text-start">Course Name</th>
                            <th class="text-start">Slug</th>
                            <th class="text-start">Price</th>
                            <th class="text-start">Sales</th>
                            <th class="text-start">Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        @forelse($courses as $course)
                        <tr>
                            <td class="text-start pe-0">
                                {{ $loop->iteration }}
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <a href="{{ route('admin.courses.edit', $course->id) }}" class="text-gray-900 text-hover-primary fw-bold fs-6">
                                        {{ $course->name }}
                                    </a>
                                    <span class="text-muted fs-8">{{ Str::limit($course->short_description, 60) }}</span>
                                </div>
                            </td>
                            <td><code>{{ $course->slug }}</code></td>
                            <td class="text-start pe-0 fw-bold text-dark">
                                {{ $course->formattedPrice() }}
                            </td>
                            <td class="text-start pe-0">
                                <span class="badge badge-light-primary fw-bold">{{ $course->paid_purchases_count }}</span>
                            </td>
                            <td class="text-start pe-0">
                                <span class="badge {{ $course->is_active ? 'badge-light-success' : 'badge-light-danger' }} fw-bold">
                                    {{ $course->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <a class="btn btn-primary btn-sm" href="{{ route('admin.courses.edit', $course->id) }}">Edit</a>
                                <form action="{{ route('admin.courses.toggle', $course->id) }}" method="post" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn {{ $course->is_active ? 'btn-danger' : 'btn-success' }} btn-sm">
                                        {{ $course->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">No courses found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Products-->
    </div>
    <!--end::Content container-->
</div>
<!--end::Content-->
@endsection
