<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** Roles nobody edits: the two that can do everything, and client accounts. */
    private const PROTECTED_ROLES = [...User::FULL_ACCESS_ROLES, 'client'];

    public function index(Request $request)
    {
        $user = $request->user();
        $catalog = PermissionCatalog::names();

        $roles = Role::with('permissions')->withCount('users')->get()->map(function ($role) use ($catalog) {
            $fullAccess = in_array($role->name, User::FULL_ACCESS_ROLES, true);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $fullAccess
                    ? $catalog
                    : array_values(array_intersect($catalog, $role->permissions->pluck('name')->all())),
                'users_count' => $role->users_count,
                'is_super_admin' => $fullAccess,
                'is_protected' => in_array($role->name, self::PROTECTED_ROLES, true),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        return Inertia::render('TeamManagement/Roles', [
            'roles' => $roles,
            'permissions' => $catalog,
            'catalog' => PermissionCatalog::modules(),
            // The permissions this person may hand out: their own.
            'grantable' => $user->hasFullAccess()
                ? $catalog
                : array_values(array_intersect($catalog, $user->getAllPermissions()->pluck('name')->all())),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);

        $permissions = $this->grantable($request, $validated['permissions'] ?? [], []);

        Role::create(['name' => $validated['name']])->syncPermissions($permissions);

        return back();
    }

    public function update(Request $request, Role $role)
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->withErrors(['name' => 'This role cannot be modified.']);
        }

        // Nobody widens their own access: a role is edited by someone outside it.
        if (! $request->user()->hasFullAccess() && $request->user()->hasRole($role->name)) {
            return back()->withErrors(['name' => 'You cannot edit a role you belong to.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);

        $permissions = $this->grantable(
            $request,
            $validated['permissions'] ?? [],
            $role->permissions->pluck('name')->all(),
        );

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($permissions);

        return back();
    }

    public function destroy(Request $request, Role $role)
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->withErrors(['name' => 'This role cannot be deleted.']);
        }

        if (! $request->user()->hasFullAccess() && $request->user()->hasRole($role->name)) {
            return back()->withErrors(['name' => 'You cannot delete a role you belong to.']);
        }

        // Users in the recycle bin do not count: only people who can still sign in.
        if ($role->users()->exists()) {
            return back()->withErrors(['name' => 'This role still has users. Move them to another role first.']);
        }

        $role->delete();

        return back();
    }

    /**
     * The permissions a role ends up with: what was ticked, plus the pages
     * those buttons sit on.
     *
     * Someone who is not full access can only hand out permissions they hold
     * themselves. Ones the role already had that they do not hold are left as
     * they were, so editing a role never quietly strips it either.
     *
     * @param  list<string>  $requested
     * @param  list<string>  $current
     * @return list<string>
     */
    private function grantable(Request $request, array $requested, array $current): array
    {
        $user = $request->user();
        $requested = PermissionCatalog::withRequired($requested);

        if ($user->hasFullAccess()) {
            return $requested;
        }

        $own = $user->getAllPermissions()->pluck('name')->all();

        return array_values(array_unique([
            ...array_intersect($requested, $own),
            ...array_diff($current, $own),
        ]));
    }
}
