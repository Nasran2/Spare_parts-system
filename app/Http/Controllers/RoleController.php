<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    private function canSeeSuperAdmin(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    private function superAdminRoleNames(): array
    {
        return ['Super Admin', 'superadmin', 'super_admin'];
    }

    private function isReservedSuperAdminName(string $name): bool
    {
        $normalized = strtolower(str_replace([' ', '-'], ['_', '_'], trim($name)));

        return in_array($normalized, ['super_admin', 'superadmin'], true);
    }

    private function visibleRolesQuery()
    {
        $query = Role::query();

        if (! $this->canSeeSuperAdmin()) {
            $query->whereNotIn('name', $this->superAdminRoleNames());
        }

        return $query;
    }

    private function abortIfHiddenSuperAdminRole(Role $role): void
    {
        if (! $this->canSeeSuperAdmin() && $role->isSuperAdminRole()) {
            abort(404);
        }
    }

    private function sanitizeAssignablePermissions(array $permissions): array
    {
        if ($this->canSeeSuperAdmin()) {
            return $permissions;
        }

        return array_values(array_diff($permissions, [
            'privacy_mode.settings',
            'privacy_mode.bypass',
        ]));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = $this->visibleRolesQuery()->withCount('users')->get();

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name',
                Rule::notIn($this->canSeeSuperAdmin() ? [] : $this->superAdminRoleNames()),
            ],
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'is_active' => 'boolean',
        ]);

        if (! $this->canSeeSuperAdmin() && $this->isReservedSuperAdminName($validated['name'])) {
            return back()->withErrors(['name' => 'This role name is not available.'])->withInput();
        }

        $validated['is_active'] = $request->has('is_active');
        $validated['permissions'] = $this->sanitizeAssignablePermissions($validated['permissions']);

        Role::create($validated);

        return redirect()->route('roles.index')
            ->with('success', 'Role created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::withCount('users')->findOrFail($id);
        $this->abortIfHiddenSuperAdminRole($role);

        return view('roles.show', compact('role'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $role = Role::findOrFail($id);
        $this->abortIfHiddenSuperAdminRole($role);

        return view('roles.edit', compact('role'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::findOrFail($id);
        $this->abortIfHiddenSuperAdminRole($role);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($id),
                Rule::notIn($this->canSeeSuperAdmin() ? [] : $this->superAdminRoleNames()),
            ],
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
            'is_active' => 'boolean',
        ]);

        if (! $this->canSeeSuperAdmin() && $this->isReservedSuperAdminName($validated['name'])) {
            return back()->withErrors(['name' => 'This role name is not available.'])->withInput();
        }

        $validated['is_active'] = $request->has('is_active');
        $validated['permissions'] = $this->sanitizeAssignablePermissions($validated['permissions']);

        $role->update($validated);

        return redirect()->route('roles.index')
            ->with('success', 'Role updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::withCount('users')->findOrFail($id);
        $this->abortIfHiddenSuperAdminRole($role);

        if ($role->users_count > 0) {
            return redirect()->route('roles.index')
                ->with('error', 'Cannot delete role with assigned users!');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully!');
    }
}
