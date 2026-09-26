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

        $users = User::with('roles')->withExists('client')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'is_super_admin' => $user->hasRole('superadmin'),
                // Client accounts are listed on their own tab, apart from the team.
                'is_client' => $user->client_exists || $user->hasRole('client'),
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users',
                // unique:users also sees deleted users, so say where the clash is.
                function ($attribute, $value, $fail) {
                    if (User::onlyTrashed()->where('email', $value)->exists()) {
                        $fail('A deleted user with this email is in the recycle bin. Restore them from there instead.');
                    }
                }],
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

    /**
     * Send several users to the recycle bin. Superadmins and the caller are
     * skipped, same as the single delete.
     */
    public function bulkDestroy(Request $request)
    {
        abort_if(!auth()->user()->can('delete users'), 403, 'Unauthorized');

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $deleted = 0;

        User::whereIn('id', $validated['ids'])->whereKeyNot(auth()->id())->get()
            ->reject(fn (User $user) => $user->hasRole('superadmin'))
            ->each(function (User $user) use (&$deleted) {
                $user->delete();
                $deleted++;
            });

        $skipped = count($validated['ids']) - $deleted;

        return back()->with('success', "Moved {$deleted} " . str('user')->plural($deleted) . ' to the recycle bin.'
            . ($skipped > 0 ? " Skipped {$skipped} (super admins and your own account can't be deleted)." : ''));
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

        // Soft delete: the user goes to the recycle bin and can be restored.
        $user->delete();

        return back()->with('success', "{$user->name} was moved to the recycle bin.");
    }
}
