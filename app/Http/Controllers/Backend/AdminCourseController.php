<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCourseController extends Controller
{
    public function index(): View
    {
        $courses = Course::withCount([
            'purchases as paid_purchases_count' => fn ($q) => $q->where('status', 'paid'),
        ])->orderBy('sort_order')->get();

        return view('backend.courses.index', compact('courses'));
    }

    public function edit(Course $course): View
    {
        return view('backend.courses.edit', compact('course'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'badge'             => ['nullable', 'string', 'max:50'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string'],
            'price'             => ['required', 'numeric', 'min:0'],
            'stripe_price_id'   => ['nullable', 'string', 'max:255'],
            'features'          => ['nullable', 'string'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $features = [];
        if (! empty($validated['features'])) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        $priceInPence = (int) round($validated['price'] * 100);

        $course->update([
            'name'              => $validated['name'],
            'badge'             => $validated['badge'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'description'       => $validated['description'] ?? null,
            'price'             => $priceInPence,
            'stripe_price_id'   => $validated['stripe_price_id'] ?? null,
            'features'          => $features,
            'sort_order'        => $validated['sort_order'] ?? 0,
            'is_active'         => $request->boolean('is_active'),
        ]);

        \App\Models\AdminAuditLog::record(
            action: 'course_updated',
            auditable: $course,
            notes: "Updated course '{$course->name}' (Price: £" . number_format($priceInPence / 100, 2) . ", Active: " . ($course->is_active ? 'Yes' : 'No') . ")."
        );

        return redirect()->route('admin.courses.index')
            ->with('success', "Course '{$course->name}' updated successfully.");
    }

    public function toggleStatus(Course $course): RedirectResponse
    {
        $course->is_active = ! $course->is_active;
        $course->save();

        $state = $course->is_active ? 'activated' : 'deactivated';

        \App\Models\AdminAuditLog::record(
            action: 'course_status_toggled',
            auditable: $course,
            notes: "Course '{$course->name}' status toggled to {$state}."
        );

        return back()->with('success', "Course '{$course->name}' has been {$state}.");
    }

    public function toggle(Course $course): RedirectResponse
    {
        return $this->toggleStatus($course);
    }
}
