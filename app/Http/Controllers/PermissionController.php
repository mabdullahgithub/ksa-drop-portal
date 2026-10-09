<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Inertia\Inertia;

class PermissionController extends Controller
{
    /**
     * Read-only. The permissions are the catalog: routes and buttons are
     * gated on these names, so one renamed or removed from the browser would
     * quietly lock people out.
     */
    public function index()
    {
        return Inertia::render('TeamManagement/Permissions', [
            'catalog' => PermissionCatalog::modules(),
            // Which roles hold each permission.
            'roles' => Permission::with('roles:id,name')
                ->whereIn('name', PermissionCatalog::names())
                ->get()
                ->mapWithKeys(fn (Permission $permission) => [$permission->name => $permission->roles->pluck('name')->all()]),
        ]);
    }
}
