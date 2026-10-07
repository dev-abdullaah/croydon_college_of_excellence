<form method="GET" class="d-flex align-items-center gap-2">
    @foreach(request()->except(['date_range', 'start_date', 'end_date', 'page']) as $k => $v)
        @if(is_array($v))
            @foreach($v as $subV)
                <input type="hidden" name="{{ $k }}[]" value="{{ $subV }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <div class="d-flex align-items-center">
        <select name="date_range" class="form-select form-select-sm form-select-solid fs-7 fw-semibold border border-gray-300 w-160px" onchange="this.form.submit()">
            <option value="all_time" {{ ($dateRangePreset ?? '') === 'all_time' ? 'selected' : '' }}>📅 All Time</option>
            <option value="today" {{ ($dateRangePreset ?? '') === 'today' ? 'selected' : '' }}>📅 Today</option>
            <option value="yesterday" {{ ($dateRangePreset ?? '') === 'yesterday' ? 'selected' : '' }}>📅 Yesterday</option>
            <option value="last_7_days" {{ ($dateRangePreset ?? '') === 'last_7_days' ? 'selected' : '' }}>📅 Last 7 Days</option>
            <option value="this_month" {{ ($dateRangePreset ?? '') === 'this_month' ? 'selected' : '' }}>📅 This Month</option>
            <option value="last_month" {{ ($dateRangePreset ?? '') === 'last_month' ? 'selected' : '' }}>📅 Last Month</option>
            <option value="this_year" {{ ($dateRangePreset ?? '') === 'this_year' ? 'selected' : '' }}>📅 This Year</option>
        </select>
    </div>
    @if(isset($startDate) && isset($endDate) && ($dateRangePreset ?? '') !== 'all_time')
        <span class="badge badge-light-primary fs-8">
            {{ $startDate->format('M d') }} - {{ $endDate->format('M d, Y') }}
        </span>
    @endif
</form>
