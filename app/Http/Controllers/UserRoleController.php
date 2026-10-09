<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use App\Notifications\UserUpdatedNotification;
use App\Notifications\UserCreatedNotification;
use App\Support\ClientAccess;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();

        $users = User::with(['roles', 'assignedClients:id'])->withExists('client')->get()
            ->map(function ($user) {
                $fullAccess = $user->hasFullAccess();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'is_super_admin' => $fullAccess,
                    // Client accounts are listed on their own tab, apart from the team.
                    'is_client' => $user->client_exists || $user->hasRole('client'),
                    // Which clients this person handles; full access is never limited.
                    'client_access' => $fullAccess ? User::CLIENT_ACCESS_ALL : $user->client_access,
                    'client_ids' => $user->assignedClients->pluck('id'),
                    'created_at' => $user->created_at,
                ];
            })
            // The client sign-in accounts are a tab, and a permission, of their own.
            ->reject(fn ($user) => $user['is_client'] && ! $actor->can('view client accounts'))
            ->values();

        return Inertia::render('TeamManagement/Users', [
            'users' => $users,
            // Only the roles this person may hand out.
            'roles' => $this->assignableRoles($actor),
            // For the "Add New Role" dialog inside the user form.
            'catalog' => $actor->can('create roles') ? PermissionCatalog::modules() : [],
            'grantable' => $actor->hasFullAccess()
                ? PermissionCatalog::names()
                : array_values(array_intersect(PermissionCatalog::names(), $actor->getAllPermissions()->pluck('name')->all())),
            // Already limited to the clients this person handles themselves.
            'clients' => $actor->can('assign client access')
                ? Client::orderBy('company_name')->get(['id', 'company_name', 'short_id'])
                    ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->company_name, 'code' => $client->short_id])
                : [],
            // False for someone limited to their own clients: they cannot hand out "all".
            'canGrantAllClients' => ClientAccess::idsFor($actor) === null,
        ]);
    }

    public function store(Request $request)
    {
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
            'client_access' => ['sometimes', Rule::in([User::CLIENT_ACCESS_ALL, User::CLIENT_ACCESS_ASSIGNED])],
            'client_ids' => 'sometimes|array',
            'client_ids.*' => 'integer',
        ]);

        $actor = $request->user();

        // Auto-generate a secure password (16 chars with letters, numbers, symbols)
        $generatedPassword = Str::password(16, true, true, false, true);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $generatedPassword,
        ]);

        if ($actor->can('assign user roles')) {
            $roles = array_intersect($validated['roles'] ?? [], $this->assignableRoles($actor));
            if ($roles !== []) {
                $user->assignRole(array_values($roles));
            }
        }

        $this->applyClientAccess($actor, $user, $validated, creating: true);

        // Send welcome email with credentials to the new user
        $user->notify(new WelcomeUserNotification(
            password: $generatedPassword,
            createdBy: $actor->name
        ));

        // Notify admins and superadmins about the new user
        $admins = User::role(['superadmin', 'admin'])
            ->where('id', '!=', $actor->id)
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new UserCreatedNotification(
                createdUser: $user,
                createdBy: $actor
            ));
        }

        return back()->with('success', 'User created successfully. Login credentials have been sent to their email.');
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();

        if ($user->hasFullAccess() && ! $actor->hasFullAccess()) {
            return back()->withErrors(['roles' => 'Super admin and developer users cannot be modified.']);
        }

        if ($user->hasRole('superadmin')) {
            return back()->withErrors(['roles' => 'Super admin user roles cannot be modified.']);
        }

        $validated = $request->validate([
            'roles' => 'sometimes|array',
            'roles.*' => 'string|exists:roles,name',
            'client_access' => ['sometimes', Rule::in([User::CLIENT_ACCESS_ALL, User::CLIENT_ACCESS_ASSIGNED])],
            'client_ids' => 'sometimes|array',
            'client_ids.*' => 'integer',
        ]);

        $changes = [];

        if (array_key_exists('roles', $validated) && $actor->can('assign user roles')) {
            // Nobody widens their own access.
            if ($user->is($actor) && ! $actor->hasFullAccess()) {
                return back()->withErrors(['roles' => 'You cannot change your own roles.']);
            }

            $assignable = $this->assignableRoles($actor);
            $current = $user->roles->pluck('name')->all();

            // Roles this person may not hand out stay exactly as they were.
            $roles = collect($validated['roles'])->intersect($assignable)
                ->merge(array_diff($current, $assignable))
                ->unique()->values();

            $user->syncRoles($roles);
            $changes['roles'] = $roles->toArray();
        }

        $this->applyClientAccess($actor, $user, $validated);

        if ($changes !== []) {
            // Notify the user about role changes
            $user->notify(new UserUpdatedNotification(
                updatedUser: $user,
                updatedBy: $actor,
                changes: $changes
            ));
        }

        return back();
    }

    /**
     * Send several users to the recycle bin. Full-access accounts and the
     * caller are skipped, same as the single delete.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $deleted = 0;

        User::whereIn('id', $validated['ids'])->whereKeyNot(auth()->id())->get()
            ->reject(fn (User $user) => $user->hasFullAccess())
            ->each(function (User $user) use (&$deleted) {
                $user->delete();
                $deleted++;
            });

        $skipped = count($validated['ids']) - $deleted;

        return back()->with('success', "Moved {$deleted} " . str('user')->plural($deleted) . ' to the recycle bin.'
            . ($skipped > 0 ? " Skipped {$skipped} (super admins, developers and your own account can't be deleted)." : ''));
    }

    public function destroy(User $user)
    {
        // Prevent deleting superadmin and developer users
        if ($user->hasFullAccess()) {
            return back()->withErrors(['user' => 'Super admin and developer users cannot be deleted.']);
        }

        // Prevent user from deleting themselves
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        // Soft delete: the user goes to the recycle bin and can be restored.
        $user->delete();

        return back()->with('success', "{$user->name} was moved to the recycle bin.");
    }

    /**
     * The roles a person may give to someone else.
     *
     * `superadmin` is never handed out from the browser, and `developer` only
     * by someone who already has full access. Anyone else can hand out a role
     * only when they hold every permission in it, so assigning roles is never
     * a way to give out more than you have.
     *
     * @return list<string>
     */
    private function assignableRoles(User $actor): array
    {
        $roles = Role::with('permissions')->where('name', '!=', 'superadmin')->get();

        if ($actor->hasFullAccess()) {
            return $roles->pluck('name')->all();
        }

        $own = $actor->getAllPermissions()->pluck('name')->all();

        return $roles
            ->reject(fn (Role $role) => in_array($role->name, User::FULL_ACCESS_ROLES, true))
            ->filter(fn (Role $role) => array_diff($role->permissions->pluck('name')->all(), $own) === [])
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * Save which clients a person handles, when the request says and the
     * actor may assign them.
     *
     * Someone limited to their own clients can only hand out those, and can
     * never hand out "all"; clients of the target they cannot see are kept.
     */
    private function applyClientAccess(User $actor, User $user, array $validated, bool $creating = false): void
    {
        $own = ClientAccess::idsFor($actor);

        if (! array_key_exists('client_access', $validated) || ! $actor->can('assign client access')) {
            // A new user made by someone who is limited starts with no clients
            // rather than with every client.
            if ($creating && $own !== null) {
                $user->forceFill(['client_access' => User::CLIENT_ACCESS_ASSIGNED])->save();
            }

            return;
        }

        if ($user->is($actor) && ! $actor->hasFullAccess()) {
            throw ValidationException::withMessages(['client_access' => 'You cannot change your own clients.']);
        }

        $mode = $validated['client_access'];
        $ids = array_map('intval', $validated['client_ids'] ?? []);

        if ($own !== null) {
            if ($mode === User::CLIENT_ACCESS_ALL) {
                throw ValidationException::withMessages(['client_access' => 'You can only assign the clients you handle yourself.']);
            }

            if (array_diff($ids, $own) !== []) {
                throw ValidationException::withMessages(['client_ids' => 'You can only assign the clients you handle yourself.']);
            }

            // Clients the target already has that the actor cannot see stay.
            // Read from the pivot itself: through the Client model the actor's
            // own restriction would hide exactly the ones this is about.
            $current = DB::table('client_user_access')->where('user_id', $user->id)->pluck('client_id')->all();
            $ids = [...$ids, ...array_diff($current, $own)];
        }

        // Only ids of real clients, whoever is asking.
        $ids = Client::withoutGlobalScope('client_access')->withTrashed()->whereIn('id', $ids)->pluck('id')->all();

        $user->forceFill(['client_access' => $mode])->save();
        $user->assignedClients()->sync($mode === User::CLIENT_ACCESS_ASSIGNED ? $ids : []);
    }
}
