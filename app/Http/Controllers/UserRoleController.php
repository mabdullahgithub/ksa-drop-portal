<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use App\Notifications\UserUpdatedNotification;
use App\Notifications\UserCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index()
    {
        abort_if(!auth()->user()->can('view users'), 403, 'Unauthorized');

        $users = User::with('roles')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'is_super_admin' => $user->hasRole('superadmin'),
                'created_at' => $user->created_at,
            ];
        });

        $roles = Role::all()->pluck('name');
        $permissions = \Spatie\Permission\Models\Permission::all()->pluck('name');

        return Inertia::render('TeamManagement/Users', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        abort_if(!auth()->user()->can('create users'), 403, 'Unauthorized');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        // Auto-generate a secure password (16 chars with letters, numbers, symbols)
        $generatedPassword = Str::password(16, true, true, false, true);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $generatedPassword,
        ]);

        // Assign roles but filter out superadmin
        $roles = collect($validated['roles'] ?? [])->filter(fn ($role) => $role !== 'superadmin');
        if ($roles->isNotEmpty()) {
            $user->assignRole($roles->toArray());
        }

        // Send welcome email with credentials to the new user
        $user->notify(new WelcomeUserNotification(
            password: $generatedPassword,
            createdBy: auth()->user()->name
        ));

        // Notify admins and superadmins about the new user
        $admins = User::role(['superadmin', 'admin'])
            ->where('id', '!=', auth()->id())
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new UserCreatedNotification(
                createdUser: $user,
                createdBy: auth()->user()
            ));
        }

        return back()->with('success', 'User created successfully. Login credentials have been sent to their email.');
    }

    public function update(Request $request, User $user)
    {
        abort_if(!auth()->user()->can('edit users'), 403, 'Unauthorized');

        if ($user->hasRole('superadmin')) {
            return back()->withErrors(['roles' => 'Super admin user roles cannot be modified.']);
        }

        $validated = $request->validate([
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $roles = collect($validated['roles'] ?? [])->filter(fn ($role) => $role !== 'superadmin');
        $user->syncRoles($roles);

        // Notify the user about role changes
        $user->notify(new UserUpdatedNotification(
            updatedUser: $user,
            updatedBy: auth()->user(),
            changes: ['roles' => $roles->toArray()]
        ));

        return back();
    }

    public function destroy(User $user)
    {
        abort_if(!auth()->user()->can('delete users'), 403, 'Unauthorized');

        // Prevent deleting superadmin users
        if ($user->hasRole('superadmin')) {
            return back()->withErrors(['user' => 'Super admin users cannot be deleted.']);
        }

        // Prevent user from deleting themselves
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back();
    }
}
