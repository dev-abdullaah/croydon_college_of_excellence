<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    protected function authorizeAdmin(): void
    {
        $currentUser = Auth::guard('admin')->user();
        abort_unless($currentUser && $currentUser->isAdmin(), 403, 'Administrator access required.');
    }

    protected function authorizeSuperAdmin(): void
    {
        $currentUser = Auth::guard('admin')->user();
        abort_unless($currentUser && $currentUser->isSuperAdmin(), 403, 'Super Administrator access required.');
    }

    public function index(): View
    {
        $this->authorizeAdmin();

        $users = User::orderBy('id')->paginate(15);

        return view('backend.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorizeAdmin();

        return view('backend.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $currentUser = Auth::guard('admin')->user();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'role'     => ['required', 'string', 'in:super_admin,admin,co_admin,staff'],
        ]);

        $role = $validated['role'];
        if ($role === 'staff') {
            $role = 'co_admin';
        }
        if ($role === 'super_admin' && ! $currentUser->isSuperAdmin()) {
            $role = 'admin';
        }

        $newUser = User::create([
            'name'              => $validated['name'],
            'username'          => $validated['username'] ?? null,
            'email'             => $validated['email'],
            'password'          => $validated['password'],
            'role'              => $role,
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        \App\Models\AdminAuditLog::record(
            action: 'user_created',
            auditable: $newUser,
            notes: "Created new administrative user account '{$newUser->name}' with role '{$newUser->role}'."
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$validated['name']}' created successfully.");
    }

    public function edit(User $user): View
    {
        $currentUser = Auth::guard('admin')->user();
        abort_unless($currentUser && ($currentUser->isSuperAdmin() || $currentUser->id === $user->id), 403);

        return view('backend.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $currentUser = Auth::guard('admin')->user();
        abort_unless($currentUser && ($currentUser->isSuperAdmin() || $currentUser->id === $user->id), 403);
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'username'  => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'email'     => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'      => ['required', 'string', 'in:super_admin,admin,co_admin,staff'],
            'password'  => ['nullable', 'string', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $role = $validated['role'];
        if ($role === 'staff') {
            $role = 'co_admin';
        }
        if ($role === 'super_admin' && ! $currentUser->isSuperAdmin()) {
            $role = $user->role;
        }

        $data = [
            'name'      => $validated['name'],
            'username'  => $validated['username'] ?? null,
            'email'     => $validated['email'],
            'role'      => $role,
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        \App\Models\AdminAuditLog::record(
            action: 'user_updated',
            auditable: $user,
            notes: "Updated user '{$user->name}' (Role: {$user->role}, Active: " . ($user->is_active ? 'Yes' : 'No') . ")."
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' updated successfully.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        if (Auth::guard('admin')->id() === $user->id) {
            return back()->with('error', 'You cannot delete your own logged-in account.');
        }

        if ($user->role === 'super_admin' && User::where('role', 'super_admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the only remaining Super Administrator.');
        }

        \App\Models\AdminAuditLog::record(
            action: 'user_deleted',
            notes: "Deleted user account '{$user->name}' ({$user->email}, Role: {$user->role})."
        );

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' deleted successfully.");
    }
}
