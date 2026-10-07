<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCouponController extends Controller
{
    /**
     * Display listing of coupons.
     */
    public function index(Request $request): View
    {
        $query = Coupon::with('course')->withCount('purchases');

        if ($search = $request->input('search')) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($type = $request->input('type')) {
            $query->where('discount_type', $type);
        }

        $coupons = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'total'       => Coupon::count(),
            'active'      => Coupon::where('is_active', true)->count(),
            'redemptions' => Coupon::sum('times_used'),
        ];

        return view('backend.coupons.index', compact('coupons', 'stats'));
    }

    /**
     * Show create coupon form.
     */
    public function create(): View
    {
        $courses = Course::orderBy('name')->get();
        return view('backend.coupons.create', compact('courses'));
    }

    /**
     * Store new coupon.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'discount_type'  => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'course_id'      => ['nullable', 'exists:courses,id'],
            'min_spend'      => ['nullable', 'numeric', 'min:0'],
            'max_uses'       => ['nullable', 'integer', 'min:1'],
            'starts_at'      => ['nullable', 'date'],
            'expires_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $discountType = $validated['discount_type'];

        // If percentage, discount_value is integer percentage (1-100)
        // If fixed, input is in GBP (£), convert to pence
        if ($discountType === Coupon::DISCOUNT_PERCENTAGE) {
            $discountValue = min(100, max(1, (int) round($validated['discount_value'])));
        } else {
            $discountValue = (int) round($validated['discount_value'] * 100);
        }

        $minSpend = !empty($validated['min_spend']) ? (int) round($validated['min_spend'] * 100) : null;

        $coupon = Coupon::create([
            'code'           => $code,
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'course_id'      => $validated['course_id'] ?? null,
            'min_spend'      => $minSpend,
            'max_uses'       => $validated['max_uses'] ?? null,
            'times_used'     => 0,
            'starts_at'      => $validated['starts_at'] ?? null,
            'expires_at'     => $validated['expires_at'] ?? null,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        AdminAuditLog::record(
            action: 'coupon_created',
            auditable: $coupon,
            newValues: $coupon->toArray(),
            notes: "Created promotional coupon '{$coupon->code}' ({$coupon->formattedDiscount()})."
        );

        return redirect()->route('admin.coupons.index')
            ->with('success', "Coupon '{$coupon->code}' successfully created.");
    }

    /**
     * Show edit form.
     */
    public function edit(Coupon $coupon): View
    {
        $courses = Course::orderBy('name')->get();
        return view('backend.coupons.edit', compact('coupon', 'courses'));
    }

    /**
     * Update coupon.
     */
    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:50', 'unique:coupons,code,' . $coupon->id],
            'discount_type'  => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'course_id'      => ['nullable', 'exists:courses,id'],
            'min_spend'      => ['nullable', 'numeric', 'min:0'],
            'max_uses'       => ['nullable', 'integer', 'min:1'],
            'starts_at'      => ['nullable', 'date'],
            'expires_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $discountType = $validated['discount_type'];

        if ($discountType === Coupon::DISCOUNT_PERCENTAGE) {
            $discountValue = min(100, max(1, (int) round($validated['discount_value'])));
        } else {
            $discountValue = (int) round($validated['discount_value'] * 100);
        }

        $minSpend = !empty($validated['min_spend']) ? (int) round($validated['min_spend'] * 100) : null;

        $oldValues = $coupon->toArray();

        $coupon->update([
            'code'           => $code,
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'course_id'      => $validated['course_id'] ?? null,
            'min_spend'      => $minSpend,
            'max_uses'       => $validated['max_uses'] ?? null,
            'starts_at'      => $validated['starts_at'] ?? null,
            'expires_at'     => $validated['expires_at'] ?? null,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        AdminAuditLog::record(
            action: 'coupon_updated',
            auditable: $coupon,
            oldValues: $oldValues,
            newValues: $coupon->fresh()->toArray(),
            notes: "Updated coupon '{$coupon->code}'."
        );

        return redirect()->route('admin.coupons.index')
            ->with('success', "Coupon '{$coupon->code}' successfully updated.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Coupon $coupon): RedirectResponse
    {
        $oldStatus = $coupon->is_active;
        $coupon->is_active = ! $oldStatus;
        $coupon->save();

        AdminAuditLog::record(
            action: 'coupon_status_toggled',
            auditable: $coupon,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $coupon->is_active],
            notes: "Toggled coupon '{$coupon->code}' active status to " . ($coupon->is_active ? 'Active' : 'Inactive') . "."
        );

        return back()->with('success', "Coupon '{$coupon->code}' is now " . ($coupon->is_active ? 'active' : 'inactive') . ".");
    }

    /**
     * Delete coupon.
     */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        $code = $coupon->code;
        $oldValues = $coupon->toArray();

        $coupon->delete();

        AdminAuditLog::record(
            action: 'coupon_deleted',
            oldValues: $oldValues,
            notes: "Deleted coupon '{$code}'."
        );

        return redirect()->route('admin.coupons.index')
            ->with('success', "Coupon '{$code}' has been deleted.");
    }
}
