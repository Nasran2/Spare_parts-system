<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function canSeeSuperAdmin(): bool
    {
        return auth()->user()?->isSuperAdmin() === true;
    }

    private function visibleRolesQuery()
    {
        $query = Role::query();

        if (! $this->canSeeSuperAdmin()) {
            $query->whereNotIn('name', ['Super Admin', 'superadmin', 'super_admin']);
        }

        return $query;
    }

    private function visibleUsersQuery()
    {
        $query = User::query();

        if (! $this->canSeeSuperAdmin()) {
            $query->whereDoesntHave('role', function ($roleQuery) {
                $roleQuery->whereIn('name', ['Super Admin', 'superadmin', 'super_admin']);
            });
        }

        return $query;
    }

    private function abortIfHiddenSuperAdminUser(User $user): void
    {
        $user->loadMissing('role');

        if (! $this->canSeeSuperAdmin() && $user->isSuperAdmin()) {
            abort(404);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = $this->visibleUsersQuery()->with('role')->get();

        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = $this->visibleRolesQuery()->where('is_active', true)->get();
        $stores = \App\Models\Store::where('is_active', true)->get();

        return view('users.create', compact('roles', 'stores'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                    if (! $this->canSeeSuperAdmin()) {
                        $query->whereNotIn('name', ['Super Admin', 'superadmin', 'super_admin']);
                    }
                }),
            ],
            'is_active' => 'boolean',
            'stores' => 'nullable|array',
            'stores.*' => 'exists:stores,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->has('is_active');

        $user = User::create($validated);
        
        if ($request->has('stores')) {
            $user->stores()->sync($request->stores);
        }

        return redirect()->route('users.index')
            ->with('success', 'User created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with('role')->findOrFail($id);
        $this->abortIfHiddenSuperAdminUser($user);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $user = User::with('role')->findOrFail($id);
        $this->abortIfHiddenSuperAdminUser($user);

        $roles = $this->visibleRolesQuery()->where('is_active', true)->get();
        $stores = \App\Models\Store::where('is_active', true)->get();
        $userStores = $user->stores->pluck('id')->toArray();

        return view('users.edit', compact('user', 'roles', 'stores', 'userStores'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::with('role')->findOrFail($id);
        $this->abortIfHiddenSuperAdminUser($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,'.$id,
            'email' => 'required|email|unique:users,email,'.$id,
            'password' => 'nullable|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                    if (! $this->canSeeSuperAdmin()) {
                        $query->whereNotIn('name', ['Super Admin', 'superadmin', 'super_admin']);
                    }
                }),
            ],
            'is_active' => 'boolean',
            'stores' => 'nullable|array',
            'stores.*' => 'exists:stores,id',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        $user->update($validated);
        
        if ($request->has('stores')) {
            $user->stores()->sync($request->stores);
        } else {
            $user->stores()->sync([]);
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::with('role')->findOrFail($id);
        $this->abortIfHiddenSuperAdminUser($user);

        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account!');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully!');
    }
}
