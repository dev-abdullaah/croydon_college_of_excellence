{{-- Session feedback shared by every page that redirects. --}}
@if (session('success'))
    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
@endif

@if (session('info'))
    <div class="alert alert-info" role="alert">{{ session('info') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Please check the form:</strong>
        <ul class="mb-0 mt-2 ps-3">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
