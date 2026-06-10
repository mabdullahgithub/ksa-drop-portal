<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ConnectorSettingsController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\ClientPageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check() && auth()->user()->hasRole('client')) {
        return redirect()->route('portal.dashboard');
    }
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'permission:view dashboard'])->name('dashboard');

Route::middleware(['auth', 'verified', 'role:!client'])->group(function () {
    // General
    Route::get('/client', fn () => Inertia::render('Client'))->middleware('permission:view client')->name('client');
    Route::get('/client/{client}', [ClientPageController::class, 'show'])->middleware('permission:view client')->name('client.show');
    Route::get('/inventory', fn () => Inertia::render('Inventory'))->middleware('permission:view inventory')->name('inventory');
    Route::get('/orders', fn () => Inertia::render('Orders'))->middleware('permission:view orders')->name('orders');
    Route::get('/apps', fn () => Inertia::render('Apps'))->middleware('permission:view apps')->name('apps');

    // Connectors API
    Route::get('/api/connectors', [ConnectorController::class, 'index'])->middleware('permission:view apps')->name('api.connectors.index');
    Route::patch('/api/connectors/{connector}/toggle', [ConnectorController::class, 'toggle'])->middleware('permission:edit apps')->name('api.connectors.toggle');
    Route::get('/api/connectors/{connector}/settings', [ConnectorSettingsController::class, 'show'])->middleware('permission:edit apps')->name('api.connectors.settings.show');
    Route::put('/api/connectors/{connector}/settings', [ConnectorSettingsController::class, 'update'])->middleware('permission:edit apps')->name('api.connectors.settings.update');
    Route::post('/api/connectors/{connector}/test', [ConnectorSettingsController::class, 'test'])->middleware('permission:edit apps')->name('api.connectors.settings.test');

    // J&T Express Settings Page
    Route::get('/apps/jnt-express', fn () => Inertia::render('Apps/JntSettings'))->middleware('permission:edit apps')->name('apps.jnt-express');

    // Warehouses API
    Route::prefix('api/warehouses')->middleware('permission:edit apps')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('api.warehouses.index');
        Route::post('/', [WarehouseController::class, 'store'])->name('api.warehouses.store');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('api.warehouses.update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('api.warehouses.destroy');
    });

    // Shipments API
    Route::prefix('api/shipments')->group(function () {
        Route::get('/', [ShipmentController::class, 'index'])->middleware('permission:view orders')->name('api.shipments.index');
        Route::post('/', [ShipmentController::class, 'store'])->middleware('permission:edit orders')->name('api.shipments.store');
        Route::post('/bulk', [ShipmentController::class, 'bulkStore'])->middleware('permission:edit orders')->name('api.shipments.bulk');
        Route::get('/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:view orders')->name('api.shipments.show');
        Route::post('/{shipment}/track', [ShipmentController::class, 'track'])->middleware('permission:view orders')->name('api.shipments.track');
        Route::post('/{shipment}/cancel', [ShipmentController::class, 'cancel'])->middleware('permission:edit orders')->name('api.shipments.cancel');
        Route::post('/{shipment}/invoice', [InvoiceController::class, 'generateShipping'])->middleware('permission:edit orders')->name('api.shipments.invoice');
    });

    // Invoices API (waybill only)
    Route::get('/api/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->middleware('permission:view orders')->name('api.invoices.preview');
    Route::get('/api/invoices/{invoice}/download', [InvoiceController::class, 'download'])->middleware('permission:view orders')->name('api.invoices.download');

    Route::get('/chats', fn () => Inertia::render('Chats'))->name('chats');
    Route::get('/users', fn () => Inertia::render('Users'))->middleware('permission:view users')->name('users');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'page'])->name('notifications');
    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('/api/notifications/unread-count', [NotificationController::class, 'unread'])->name('api.notifications.unread');
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
    Route::delete('/api/notifications/{id}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');

    // Orders API
    Route::prefix('api/orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('permission:view orders')->name('api.orders.index');
        Route::get('/statistics', [OrderController::class, 'statistics'])->middleware('permission:view orders')->name('api.orders.statistics');
        Route::get('/filter-options', [OrderController::class, 'filterOptions'])->middleware('permission:view orders')->name('api.orders.filter-options');
        Route::get('/export', [OrderController::class, 'export'])->middleware('permission:view orders')->name('api.orders.export');
        Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:view orders')->name('api.orders.show');
        Route::put('/{order}', [OrderController::class, 'update'])->middleware('permission:edit orders')->name('api.orders.update');
        Route::post('/{order}/fulfillment-status', [OrderController::class, 'updateFulfillmentStatus'])->middleware('permission:edit orders')->name('api.orders.fulfillment-status');
        Route::post('/{order}/financial-status', [OrderController::class, 'updateFinancialStatus'])->middleware('permission:edit orders')->name('api.orders.financial-status');
        Route::post('/bulk-update', [OrderController::class, 'bulkUpdate'])->middleware('permission:edit orders')->name('api.orders.bulk-update');
    });

    // Clients API
    Route::prefix('api/clients')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->middleware('permission:view client')->name('api.clients.index');
        Route::get('/statistics', [ClientController::class, 'statistics'])->middleware('permission:view client')->name('api.clients.statistics');
        Route::get('/filter-options', [ClientController::class, 'filterOptions'])->middleware('permission:view client')->name('api.clients.filter-options');
        Route::get('/export', [ClientController::class, 'export'])->middleware('permission:view client')->name('api.clients.export');
        Route::post('/', [ClientController::class, 'store'])->middleware('permission:create client')->name('api.clients.store');
        Route::get('/{client}', [ClientController::class, 'show'])->middleware('permission:view client')->name('api.clients.show');
        Route::put('/{client}', [ClientController::class, 'update'])->middleware('permission:edit client')->name('api.clients.update');
        Route::patch('/{client}/status', [ClientController::class, 'updateStatus'])->middleware('permission:edit client')->name('api.clients.update-status');
        Route::post('/bulk-update', [ClientController::class, 'bulkUpdate'])->middleware('permission:edit client')->name('api.clients.bulk-update');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->middleware('permission:delete client')->name('api.clients.destroy');
        // Client Products (Inventory)
        Route::get('/{client}/products', [ClientController::class, 'products'])->middleware('permission:view client')->name('api.clients.products.index');
        Route::post('/{client}/products', [ClientController::class, 'storeProduct'])->middleware('permission:edit client')->name('api.clients.products.store');
        Route::put('/{client}/products/{product}', [ClientController::class, 'updateProduct'])->middleware('permission:edit client')->name('api.clients.products.update');
        Route::patch('/{client}/products/{product}/verify', [ClientController::class, 'verifyProduct'])->middleware('permission:edit client')->name('api.clients.products.verify');
        Route::patch('/{client}/products/{product}/review', [ClientController::class, 'reviewProduct'])->middleware('permission:edit client')->name('api.clients.products.review');
        Route::delete('/{client}/products/{product}', [ClientController::class, 'destroyProduct'])->middleware('permission:delete client')->name('api.clients.products.destroy');
        Route::delete('/{client}/products/{product}/images/{image}', [ClientController::class, 'destroyProductImage'])->middleware('permission:edit client')->name('api.clients.products.images.destroy');
    });

    // Inventory / Products API
    Route::prefix('api/products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:view inventory')->name('api.products.index');
        Route::get('/statistics', [ProductController::class, 'statistics'])->middleware('permission:view inventory')->name('api.products.statistics');
        Route::get('/filter-options', [ProductController::class, 'filterOptions'])->middleware('permission:view inventory')->name('api.products.filter-options');
        Route::get('/export', [ProductController::class, 'export'])->middleware('permission:view inventory')->name('api.products.export');
        Route::post('/import', [ProductController::class, 'import'])->middleware('permission:edit inventory')->name('api.products.import');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:view inventory')->name('api.products.show');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:edit inventory')->name('api.products.update');
    });

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

    // Tags
    Route::get('/tags', [TagController::class, 'index'])->middleware('permission:view tags')->name('tags');
    Route::get('/api/tags', [TagController::class, 'list'])->middleware('permission:view tags')->name('api.tags.index');
    Route::post('/tags', [TagController::class, 'store'])->middleware('permission:create tags')->name('tags.store');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->middleware('permission:edit tags')->name('tags.update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->middleware('permission:delete tags')->name('tags.destroy');

    // Help
    Route::get('/help-center', fn () => Inertia::render('HelpCenter'))->name('help-center');

    // Admin Email Settings
    Route::prefix('admin')->middleware('permission:manage-email-settings')->group(function () {
        Route::get('/email-settings', [EmailSettingsController::class, 'index'])->name('admin.email-settings');
        Route::put('/email-settings', [EmailSettingsController::class, 'update'])->name('admin.email-settings.update');
        Route::post('/email-settings/test', [EmailSettingsController::class, 'test'])->name('admin.email-settings.test');
        Route::get('/email-logs', [EmailSettingsController::class, 'logs'])->name('admin.email-logs');
        Route::get('/email-statistics', [EmailSettingsController::class, 'statistics'])->name('admin.email-statistics');
    });
});

// Impersonate
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
    Route::post('/impersonate/{client}', [ImpersonateController::class, 'impersonate'])->name('impersonate.start');
});

// Client Portal - account restricted page (no role:client check so suspended clients can see it)
Route::middleware(['auth', 'verified'])->get('/portal/suspended', fn () => Inertia::render('Portal/Suspended'))->name('portal.suspended');

// Client Portal
Route::prefix('portal')->middleware(['auth', 'verified', 'role:client'])->group(function () {
    Route::get('/', fn () => Inertia::render('Portal/Dashboard'))->name('portal.dashboard');
    Route::get('/orders', fn () => Inertia::render('Portal/Orders'))->name('portal.orders');
    Route::get('/inventory', fn () => Inertia::render('Portal/Inventory'))->name('portal.inventory');
    Route::get('/revenue', fn () => Inertia::render('Portal/Revenue'))->name('portal.revenue');
    Route::get('/finance', fn () => Inertia::render('Portal/Finance'))->name('portal.finance');
    Route::get('/products', fn () => Inertia::render('Portal/Products'))->name('portal.products');
    Route::get('/settings', fn () => Inertia::render('Portal/Settings/CompanyProfile'))->name('portal.settings');
    Route::get('/settings/security', [SettingsController::class, 'portalSecurity'])->name('portal.settings.security');

    // Portal API
    Route::get('/api/dashboard', [PortalController::class, 'dashboard'])->name('portal.api.dashboard');
    Route::get('/api/orders', [PortalController::class, 'orders'])->name('portal.api.orders');
    Route::post('/api/orders/import', [PortalController::class, 'importOrders'])->name('portal.api.orders.import');
    Route::get('/api/orders/export', [PortalController::class, 'exportOrders'])->name('portal.api.orders.export');
    Route::get('/api/inventory', [PortalController::class, 'inventory'])->name('portal.api.inventory');
    Route::post('/api/inventory', [PortalController::class, 'storeInventory'])->name('portal.api.inventory.store');
    Route::put('/api/inventory/{product}', [PortalController::class, 'updateInventory'])->name('portal.api.inventory.update');
    Route::delete('/api/inventory/{product}', [PortalController::class, 'destroyInventory'])->name('portal.api.inventory.destroy');
    Route::delete('/api/inventory/{product}/images/{image}', [PortalController::class, 'destroyInventoryImage'])->name('portal.api.inventory.images.destroy');
    Route::get('/api/products', [PortalController::class, 'products'])->name('portal.api.products');
    Route::get('/api/products/filter-options', [PortalController::class, 'productFilterOptions'])->name('portal.api.products.filter-options');
    Route::get('/api/products/{product}', [PortalController::class, 'productShow'])->name('portal.api.products.show');
    Route::get('/api/revenue', [PortalController::class, 'revenue'])->name('portal.api.revenue');
    Route::get('/api/finance', [PortalController::class, 'finance'])->name('portal.api.finance');
    Route::get('/api/invoices/{invoice}/preview', [InvoiceController::class, 'portalPreview'])->name('portal.api.invoices.preview');
    Route::get('/api/invoices/{invoice}/download', [InvoiceController::class, 'portalDownload'])->name('portal.api.invoices.download');
    Route::post('/settings/company-profile', [PortalController::class, 'updateCompanyProfile'])->name('portal.settings.company-profile.update');
    Route::post('/settings/logo', [PortalController::class, 'updateLogo'])->name('portal.settings.logo.update');
    Route::delete('/settings/logo', [PortalController::class, 'removeLogo'])->name('portal.settings.logo.remove');
});

// Public Tracking Page (no auth required)
Route::get('/track/{trackingNumber}', [TrackingController::class, 'show'])->name('tracking.show');
Route::get('/api/track/{trackingNumber}', [TrackingController::class, 'api'])->name('tracking.api');

// Webhooks (no auth, no CSRF)
Route::post('/webhooks/jnt-express', [WebhookController::class, 'handleJntExpress'])->name('webhooks.jnt-express');

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
