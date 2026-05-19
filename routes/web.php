<?php

use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'permission:view dashboard'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // General
    Route::get('/client', fn () => Inertia::render('Client'))->middleware('permission:view client')->name('client');
    Route::get('/inventory', fn () => Inertia::render('Inventory'))->middleware('permission:view inventory')->name('inventory');
    Route::get('/orders', fn () => Inertia::render('Orders'))->middleware('permission:view orders')->name('orders');
    Route::get('/apps', fn () => Inertia::render('Apps'))->middleware('permission:view apps')->name('apps');
    Route::get('/chats', fn () => Inertia::render('Chats'))->name('chats');
    Route::get('/users', fn () => Inertia::render('Users'))->name('users');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'page'])->name('notifications');
    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('/api/notifications/unread-count', [NotificationController::class, 'unread'])->name('api.notifications.unread');
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
    Route::delete('/api/notifications/{id}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');

    // Settings
    Route::get('/settings', [SettingsController::class, 'profile'])->name('settings');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar.update');
    Route::delete('/settings/avatar', [SettingsController::class, 'removeAvatar'])->name('settings.avatar.remove');
    Route::get('/settings/account', fn () => Inertia::render('Settings/Account'))->name('settings.account');
    Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotificationPreferences'])->name('settings.notifications.update');
    Route::get('/settings/email', [SettingsController::class, 'email'])->name('settings.email');
    Route::put('/settings/email/preferences', [SettingsController::class, 'updateEmailPreferences'])->name('settings.email.preferences.update');
    Route::put('/settings/email/smtp', [SettingsController::class, 'updateEmailSettings'])->name('settings.email.smtp.update');
    Route::get('/settings/security', [SettingsController::class, 'security'])->name('settings.security');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/two-factor/enable', [SettingsController::class, 'enableTwoFactor'])->name('settings.two-factor.enable');
    Route::post('/settings/two-factor/confirm', [SettingsController::class, 'confirmTwoFactor'])->name('settings.two-factor.confirm');
    Route::delete('/settings/two-factor', [SettingsController::class, 'disableTwoFactor'])->name('settings.two-factor.disable');
    Route::get('/settings/two-factor/recovery-codes', [SettingsController::class, 'showRecoveryCodes'])->name('settings.two-factor.recovery-codes');
    Route::post('/settings/two-factor/recovery-codes', [SettingsController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes.regenerate');

    // Team Management
    Route::get('/team-management/roles', [RoleController::class, 'index'])->middleware('permission:view roles')->name('team-management.roles');
    Route::post('/team-management/roles', [RoleController::class, 'store'])->middleware('permission:create roles')->name('team-management.roles.store');
    Route::put('/team-management/roles/{role}', [RoleController::class, 'update'])->middleware('permission:edit roles')->name('team-management.roles.update');
    Route::delete('/team-management/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete roles')->name('team-management.roles.destroy');

    Route::get('/team-management/permissions', [PermissionController::class, 'index'])->middleware('permission:view permissions')->name('team-management.permissions');
    Route::post('/team-management/permissions', [PermissionController::class, 'store'])->middleware('permission:create permissions')->name('team-management.permissions.store');
    Route::put('/team-management/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:edit permissions')->name('team-management.permissions.update');
    Route::delete('/team-management/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:delete permissions')->name('team-management.permissions.destroy');

    Route::get('/team-management/teams', [TeamController::class, 'index'])->middleware('permission:view teams')->name('team-management.teams');
    Route::post('/team-management/teams', [TeamController::class, 'store'])->middleware('permission:create teams')->name('team-management.teams.store');
    Route::put('/team-management/teams/{team}', [TeamController::class, 'update'])->middleware('permission:edit teams')->name('team-management.teams.update');
    Route::delete('/team-management/teams/{team}', [TeamController::class, 'destroy'])->middleware('permission:delete teams')->name('team-management.teams.destroy');

    Route::get('/team-management/users', [UserRoleController::class, 'index'])->middleware('permission:view users')->name('team-management.users');
    Route::post('/team-management/users', [UserRoleController::class, 'store'])->middleware('permission:create users')->name('team-management.users.store');
    Route::put('/team-management/users/{user}', [UserRoleController::class, 'update'])->middleware('permission:edit users')->name('team-management.users.update');
    Route::delete('/team-management/users/{user}', [UserRoleController::class, 'destroy'])->middleware('permission:delete users')->name('team-management.users.destroy');

    // Help
    Route::get('/help-center', fn () => Inertia::render('HelpCenter'))->name('help-center');

    // Admin Email Settings (requires permission)
    Route::prefix('admin')->group(function () {
        Route::get('/email-settings', [EmailSettingsController::class, 'index'])->name('admin.email-settings');
        Route::put('/email-settings', [EmailSettingsController::class, 'update'])->name('admin.email-settings.update');
        Route::post('/email-settings/test', [EmailSettingsController::class, 'test'])->name('admin.email-settings.test');
        Route::get('/email-logs', [EmailSettingsController::class, 'logs'])->name('admin.email-logs');
        Route::get('/email-statistics', [EmailSettingsController::class, 'statistics'])->name('admin.email-statistics');
    });
});

// Error pages
Route::get('/errors/unauthorized', fn () => Inertia::render('Errors/Unauthorized'))->name('errors.unauthorized');
Route::get('/errors/forbidden', fn () => Inertia::render('Errors/Forbidden'))->name('errors.forbidden');
Route::get('/errors/not-found', fn () => Inertia::render('Errors/NotFound'))->name('errors.not-found');
Route::get('/errors/internal-server-error', fn () => Inertia::render('Errors/InternalServerError'))->name('errors.internal-server-error');
Route::get('/errors/maintenance-error', fn () => Inertia::render('Errors/MaintenanceError'))->name('errors.maintenance-error');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
