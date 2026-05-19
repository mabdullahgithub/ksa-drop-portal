<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
                'is_super_admin' => $role->name === 'superadmin',
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        $permissions = Permission::all()->pluck('name');

        return Inertia::render('TeamManagement/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $validated['name']]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return back();
    }

    public function update(Request $request, Role $role)
    {
        if ($role->name === 'superadmin') {
            return back()->withErrors(['name' => 'Super admin role cannot be modified.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back();
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'superadmin') {
            return back()->withErrors(['name' => 'Super admin role cannot be deleted.']);
        }

        $role->delete();

        return back();
    }
}
