<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::with('category')->get()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'roles' => $permission->roles->pluck('name'),
                'category_id' => $permission->category_id,
                'category' => $permission->category ? [
                    'id' => $permission->category->id,
                    'name' => $permission->category->name,
                    'label' => $permission->category->label,
                    'order' => $permission->category->order,
                ] : null,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at,
            ];
        });

        $categories = PermissionCategory::orderBy('order')->get();

        return Inertia::render('TeamManagement/Permissions', [
            'permissions' => $permissions,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        // Permissions cannot be created from frontend
        return back()->withErrors(['permission' => 'Permissions can only be created by system administrators.']);
    }

    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update(['name' => $validated['name']]);

        return back();
    }

    public function destroy(Permission $permission)
    {
        // Permissions cannot be deleted
        return back()->withErrors(['permission' => 'Permissions cannot be deleted.']);
    }
}
