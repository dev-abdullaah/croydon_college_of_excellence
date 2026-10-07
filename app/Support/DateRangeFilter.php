<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

class DateRangeFilter
{
    /**
     * Resolve start and end Carbon timestamps from request.
     *
     * @return array{0: Carbon|null, 1: Carbon|null, 2: string} [startDate, endDate, activePreset]
     */
    public static function resolve(Request $request): array
    {
        $preset = $request->input('date_range', 'all_time');

        $startDate = null;
        $endDate = null;

        switch ($preset) {
            case 'today':
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
                break;

            case 'yesterday':
                $startDate = Carbon::yesterday()->startOfDay();
                $endDate = Carbon::yesterday()->endOfDay();
                break;

            case 'last_7_days':
                $startDate = Carbon::today()->subDays(6)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                break;

            case 'this_month':
                $startDate = Carbon::now()->startOfMonth()->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                break;

            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth()->startOfDay();
                $endDate = Carbon::now()->subMonth()->endOfMonth()->endOfDay();
                break;

            case 'this_year':
                $startDate = Carbon::now()->startOfYear()->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                break;

            case 'custom':
                if ($start = $request->input('start_date')) {
                    $startDate = Carbon::parse($start)->startOfDay();
                }
                if ($end = $request->input('end_date')) {
                    $endDate = Carbon::parse($end)->endOfDay();
                }
                break;

            case 'all_time':
            default:
                $preset = 'all_time';
                $startDate = null;
                $endDate = null;
                break;
        }

        return [$startDate, $endDate, $preset];
    }
}
