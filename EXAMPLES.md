# Permission System - Usage Examples

## Table of Contents
- [Frontend Examples](#frontend-examples)
- [Backend Examples](#backend-examples)
- [Common Patterns](#common-patterns)

## Frontend Examples

### 1. Hide Buttons Based on Permissions

```tsx
import { Can } from '@/components/can'
import { Button } from '@/components/ui/button'

function UserManagement() {
  return (
    <div>
      {/* Only show if user has 'create users' permission */}
      <Can permission="create users">
        <Button>Add User</Button>
      </Can>

      {/* Only show if user has 'delete users' permission */}
      <Can permission="delete users">
        <Button variant="destructive">Delete User</Button>
      </Can>
    </div>
  )
}
```

### 2. Hide Menu Items in Dropdown

```tsx
import { Can } from '@/components/can'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

function ActionsMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger>Actions</DropdownMenuTrigger>
      <DropdownMenuContent>
        {/* Always visible */}
        <DropdownMenuItem>View Details</DropdownMenuItem>

        {/* Only show if user can edit */}
        <Can permission="edit users">
          <DropdownMenuItem>Edit</DropdownMenuItem>
        </Can>

        {/* Only show if user can delete */}
        <Can permission="delete users">
          <DropdownMenuItem className="text-destructive">
            Delete
          </DropdownMenuItem>
        </Can>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
```

### 3. Conditional Rendering with usePermissions Hook

```tsx
import { usePermissions } from '@/hooks/use-permissions'

function Dashboard() {
  const { can, hasRole } = usePermissions()

  return (
    <div>
      {can('view analytics') && (
        <div>
          <h2>Analytics</h2>
          <AnalyticsChart />
        </div>
      )}

      {hasRole('admin') && (
        <div>
          <h2>Admin Panel</h2>
          <AdminControls />
        </div>
      )}
    </div>
  )
}
```

### 4. Check Multiple Permissions (OR logic)

```tsx
import { Can } from '@/components/can'

function UserProfile() {
  return (
    <div>
      {/* Show if user has ANY of these permissions */}
      <Can permission={['edit users', 'view users', 'manage users']}>
        <UserDetailsSection />
      </Can>
    </div>
  )
}
```

### 5. Check Multiple Permissions (AND logic)

```tsx
import { Can } from '@/components/can'

function SensitiveAction() {
  return (
    <div>
      {/* Show only if user has ALL of these permissions */}
      <Can permission={['edit users', 'delete users']} requireAll>
        <Button variant="destructive">Delete All Users</Button>
      </Can>
    </div>
  )
}
```

### 6. Show Fallback Content

```tsx
import { Can } from '@/components/can'

function ProtectedContent() {
  return (
    <Can 
      permission="view reports" 
      fallback={
        <div className="text-muted-foreground">
          You don't have permission to view reports.
        </div>
      }
    >
      <ReportsTable />
    </Can>
  )
}
```

### 7. Disable Button Instead of Hiding

```tsx
import { usePermissions } from '@/hooks/use-permissions'
import { Button } from '@/components/ui/button'

function UserForm() {
  const { can } = usePermissions()

  return (
    <form>
      <input type="text" name="name" />
      <Button 
        type="submit" 
        disabled={!can('edit users')}
      >
        Save Changes
      </Button>
    </form>
  )
}
```

### 8. Complex Permission Logic

```tsx
import { usePermissions } from '@/hooks/use-permissions'

function ComplexPermissionCheck() {
  const { can, hasRole, canAll } = usePermissions()

  // Check if user is admin OR has all required permissions
  const canManageUsers = hasRole('admin') || canAll(['view users', 'edit users', 'delete users'])

  return (
    <div>
      {canManageUsers && (
        <AdvancedUserManagement />
      )}
    </div>
  )
}
```

## Backend Examples

### 1. Protect Individual Routes

```php
// routes/web.php

// Single permission check
Route::get('/users', [UserController::class, 'index'])
    ->middleware('permission:view users');

// Multiple permissions (user needs ANY one)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('permission:view dashboard|view analytics');

// Role-based protection
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');
```

### 2. Protect Route Groups

```php
// routes/web.php

Route::middleware(['auth', 'permission:view users'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
});

// Different permissions for different actions
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])
        ->middleware('permission:view users');
    
    Route::post('/', [UserController::class, 'store'])
        ->middleware('permission:create users');
    
    Route::put('/{user}', [UserController::class, 'update'])
        ->middleware('permission:edit users');
    
    Route::delete('/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:delete users');
});
```

### 3. Protect in Controller Constructor

```php
// app/Http/Controllers/UserController.php

class UserController extends Controller
{
    public function __construct()
    {
        // Protect all methods
        $this->middleware('permission:view users');
        
        // Or protect specific methods
        $this->middleware('permission:view users')->only(['index', 'show']);
        $this->middleware('permission:create users')->only(['create', 'store']);
        $this->middleware('permission:edit users')->only(['edit', 'update']);
        $this->middleware('permission:delete users')->only(['destroy']);
    }
}
```

### 4. Manual Permission Checks in Controller

```php
// app/Http/Controllers/UserController.php

class UserController extends Controller
{
    public function update(Request $request, User $user)
    {
        // Simple check
        if (!auth()->user()->can('edit users')) {
            abort(403, 'Unauthorized');
        }

        // Check if user can edit their own profile OR has edit users permission
        if ($user->id !== auth()->id() && !auth()->user()->can('edit users')) {
            abort(403, 'You can only edit your own profile');
        }

        // Using helper
        abort_unless(auth()->user()->can('edit users'), 403);

        // Update user...
    }
}
```

### 5. Check Permissions in Blade (if using Blade)

```blade
@can('view users')
    <a href="/users">View Users</a>
@endcan

@canany(['edit users', 'delete users'])
    <button>Manage Users</button>
@endcanany

@hasrole('admin')
    <div>Admin Panel</div>
@endhasrole
```

## Common Patterns

### Pattern 1: Dashboard with Conditional Sections

```tsx
import { usePermissions } from '@/hooks/use-permissions'

function Dashboard() {
  const { can } = usePermissions()

  return (
    <div className="grid gap-4">
      {/* Everyone can see this */}
      <WelcomeSection />

      {/* Conditional sections */}
      {can('view analytics') && <AnalyticsSection />}
      {can('view reports') && <ReportsSection />}
      {can('view orders') && <OrdersSection />}
      {can('manage users') && <UsersSection />}
    </div>
  )
}
```

### Pattern 2: Action Buttons Row

```tsx
import { Can } from '@/components/can'
import { Button } from '@/components/ui/button'

function UserActionButtons({ userId }) {
  return (
    <div className="flex gap-2">
      {/* Always visible */}
      <Button variant="outline">View Profile</Button>

      {/* Conditional actions */}
      <Can permission="edit users">
        <Button>Edit</Button>
      </Can>

      <Can permission="reset passwords">
        <Button variant="secondary">Reset Password</Button>
      </Can>

      <Can permission="delete users">
        <Button variant="destructive">Delete</Button>
      </Can>
    </div>
  )
}
```

### Pattern 3: Sidebar Navigation with Nested Items

```tsx
// In sidebar-data.ts
export const sidebarData = {
  navGroups: [
    {
      title: 'Management',
      items: [
        {
          title: 'Users',
          icon: Users,
          permission: 'view users',
          items: [
            {
              title: 'All Users',
              url: '/users',
              permission: 'view users',
            },
            {
              title: 'Add User',
              url: '/users/create',
              permission: 'create users',
            },
          ],
        },
      ],
    },
  ],
}
```

### Pattern 4: Resource-Specific Permissions

```tsx
import { usePermissions } from '@/hooks/use-permissions'

function DocumentActions({ document, currentUser }) {
  const { can } = usePermissions()
  
  // User can edit if they own it OR have global edit permission
  const canEdit = document.userId === currentUser.id || can('edit all documents')
  
  // Only document owner OR admins can delete
  const canDelete = document.userId === currentUser.id || can('delete all documents')

  return (
    <div>
      {canEdit && <Button>Edit</Button>}
      {canDelete && <Button variant="destructive">Delete</Button>}
    </div>
  )
}
```

### Pattern 5: Form with Conditional Fields

```tsx
import { Can } from '@/components/can'
import { usePermissions } from '@/hooks/use-permissions'

function UserForm() {
  const { can } = usePermissions()

  return (
    <form>
      {/* Everyone can fill these */}
      <input name="name" />
      <input name="email" />

      {/* Only admins see these */}
      <Can permission="assign roles">
        <select name="role">
          <option>User</option>
          <option>Manager</option>
          <option>Admin</option>
        </select>
      </Can>

      <Can permission="manage permissions">
        <PermissionsCheckboxes />
      </Can>

      {/* Disable submit if can't edit */}
      <button type="submit" disabled={!can('edit users')}>
        Save
      </button>
    </form>
  )
}
```

### Pattern 6: Data Table with Action Column

```tsx
import { usePermissions } from '@/hooks/use-permissions'

function UsersTable({ users }) {
  const { can } = usePermissions()

  return (
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          {(can('edit users') || can('delete users')) && <th>Actions</th>}
        </tr>
      </thead>
      <tbody>
        {users.map(user => (
          <tr key={user.id}>
            <td>{user.name}</td>
            <td>{user.email}</td>
            {(can('edit users') || can('delete users')) && (
              <td>
                {can('edit users') && <button>Edit</button>}
                {can('delete users') && <button>Delete</button>}
              </td>
            )}
          </tr>
        ))}
      </tbody>
    </table>
  )
}
```

## Best Practices

1. **Always protect on backend**: Frontend hiding is UX, backend middleware is security
2. **Use descriptive names**: `view users` not `users.view`
3. **Be consistent**: Use same permission names in frontend and backend
4. **Test each role**: Create test users for each role and verify UI/access
5. **Use OR for flexibility**: Multiple permissions in array gives more flexibility
6. **Use AND for strictness**: `requireAll` for critical operations
7. **Group related permissions**: Keep CRUD together (view/create/edit/delete)
8. **Cache permissions**: Already cached via Inertia shared props
