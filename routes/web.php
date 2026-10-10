<?php

use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPaymentController;
use App\Http\Controllers\Api\ConnectorSettingsController;
use App\Http\Controllers\Api\ImileWebhookController;
use App\Http\Controllers\Api\LogesTechsController;
use App\Http\Controllers\Api\LogesTechsWebhookController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\RiderPaymentController;
use App\Http\Controllers\Api\StockScanController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentEventController;
use App\Http\Controllers\Api\MetaWhatsAppWebhookController;
use App\Http\Controllers\Api\WhatsAppConversationController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\ShipmentAnalyticsController;
use App\Http\Controllers\ClientPageController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\EmailSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecycleBinController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\Embedded\EmbeddedAppController;
use App\Http\Controllers\Embedded\EmbeddedDashboardController;
use App\Http\Controllers\Embedded\EmbeddedSettingsController;
use App\Http\Controllers\ShopifyController;
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\ShopifyFulfillmentCallbackController;
use App\Http\Controllers\ShopifyPendingOrderController;
use App\Http\Controllers\ShopifySyncFailureController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route(auth()->user()->defaultLandingRoute());
    }
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    $user = auth()->user();

    // Users without dashboard access land on the first page they can view
    // instead of hitting a 403.
    if (!$user->can('view dashboard')) {
        return redirect()->route($user->defaultLandingRoute());
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'role:!client', 'client.access'])->group(function () {
    // General
    Route::get('/client', fn () => Inertia::render('Client'))->middleware('permission:view client')->name('client');
    Route::get('/client/{client}', [ClientPageController::class, 'show'])->middleware('permission:view client details')->name('client.show');
    Route::get('/inventory', fn () => Inertia::render('Inventory'))->middleware('permission:view inventory')->name('inventory');
    Route::get('/orders', fn () => Inertia::render('Orders'))->middleware('permission:view orders')->name('orders');

    // WhatsApp inbox — the confirmation conversations started when an agent
    // marks a call unanswered.
    Route::get('/whatsapp', fn () => Inertia::render('WhatsApp'))->middleware('permission:view whatsapp')->name('whatsapp');
    Route::get('/apps', fn () => Inertia::render('Apps'))->middleware('permission:view apps')->name('apps');

    // Connectors API
    Route::get('/api/connectors', [ConnectorController::class, 'index'])->middleware('permission:view apps|toggle connectors|configure jnt connector|configure imile connector|configure logestechs connector|configure whatsapp connector|view warehouses')->name('api.connectors.index');
    Route::patch('/api/connectors/{connector}/toggle', [ConnectorController::class, 'toggle'])->middleware('permission:toggle connectors')->name('api.connectors.toggle');
    Route::get('/api/connectors/{connector}/settings', [ConnectorSettingsController::class, 'show'])->middleware('permission:configure jnt connector|configure imile connector|configure logestechs connector|configure whatsapp connector')->name('api.connectors.settings.show');
    Route::put('/api/connectors/{connector}/settings', [ConnectorSettingsController::class, 'update'])->middleware('permission:configure jnt connector|configure imile connector|configure logestechs connector|configure whatsapp connector')->name('api.connectors.settings.update');
    Route::post('/api/connectors/{connector}/settings/reveal', [ConnectorSettingsController::class, 'reveal'])->middleware('permission:reveal connector secrets')->name('api.connectors.settings.reveal');
    Route::post('/api/connectors/{connector}/test', [ConnectorSettingsController::class, 'test'])->middleware('permission:test connector connection')->name('api.connectors.settings.test');

    // J&T Express Settings Page
    Route::get('/apps/jnt-express', fn () => Inertia::render('Apps/JntSettings'))->middleware('permission:configure jnt connector|view warehouses')->name('apps.jnt-express');

    // iMile Settings Page
    Route::get('/apps/imile', fn () => Inertia::render('Apps/ImileSettings'))->middleware('permission:configure imile connector')->name('apps.imile');

    // LogesTechs (Navix) Settings Page
    Route::get('/apps/logestechs', fn () => Inertia::render('Apps/LogesTechsSettings'))->middleware('permission:configure logestechs connector')->name('apps.logestechs');

    Route::get('/apps/whatsapp', fn () => Inertia::render('Apps/WhatsAppSettings'))->middleware('permission:configure whatsapp connector')->name('apps.whatsapp');

    // LogesTechs district lookup — feeds the Create Shipment dialog's district
    // picker. Gated on 'edit orders' rather than 'edit apps' since it's used
    // while creating a shipment, not while configuring the connector.
    Route::get('/api/logestechs/villages', [LogesTechsController::class, 'villages'])
        ->middleware('permission:create shipments')
        ->name('api.logestechs.villages');

    // Warehouses API
    Route::prefix('api/warehouses')->group(function () {
        // Also read by the Create Shipment dialog, to pick the sender address.
        Route::get('/', [WarehouseController::class, 'index'])->middleware('permission:view warehouses|create shipments')->name('api.warehouses.index');
        Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:create warehouses')->name('api.warehouses.store');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:edit warehouses')->name('api.warehouses.update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:delete warehouses')->name('api.warehouses.destroy');
    });

    // Shipments API
    Route::prefix('api/shipments')->group(function () {
        Route::get('/', [ShipmentController::class, 'index'])->middleware('permission:view shipments')->name('api.shipments.index');
        Route::post('/', [ShipmentController::class, 'store'])->middleware('permission:create shipments')->name('api.shipments.store');
        Route::post('/bulk', [ShipmentController::class, 'bulkStore'])->middleware('permission:create shipments')->name('api.shipments.bulk');
        Route::post('/waybills/bulk', [InvoiceController::class, 'bulkKsaExpressWaybills'])->middleware('permission:generate waybills')->name('api.shipments.waybills.bulk');
        Route::get('/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:view shipments')->name('api.shipments.show');
        Route::put('/{shipment}', [ShipmentController::class, 'update'])->middleware('permission:edit shipments')->name('api.shipments.update');
        Route::post('/{shipment}/track', [ShipmentController::class, 'track'])->middleware('permission:refresh shipment tracking')->name('api.shipments.track');
        Route::post('/{shipment}/cancel', [ShipmentController::class, 'cancel'])->middleware('permission:cancel shipments')->name('api.shipments.cancel');
        Route::post('/{shipment}/escalate', [ShipmentController::class, 'escalate'])->middleware('permission:escalate shipments')->name('api.shipments.escalate');
        Route::post('/{shipment}/unassign-rider', [ShipmentController::class, 'unassignRider'])->middleware('permission:unassign shipment rider')->name('api.shipments.unassign-rider');
        Route::post('/{shipment}/return', [ShipmentController::class, 'markReturned'])->middleware('permission:mark shipments returned')->name('api.shipments.return');
        Route::post('/{shipment}/receive-at-hub', [ShipmentController::class, 'receiveAtHub'])->middleware('permission:receive shipments at hub')->name('api.shipments.receive-at-hub');
        Route::post('/{shipment}/invoice', [InvoiceController::class, 'generateShipping'])->middleware('permission:generate waybills')->name('api.shipments.invoice');
    });

    // Rider proof-of-delivery photos (private disk)
    Route::get('/api/shipment-events/{event}/photo', [ShipmentEventController::class, 'photo'])->middleware('permission:view delivery proof photos')->name('api.shipment-events.photo');

    // KSA Express riders. Outside the client restriction: a rider carries
    // parcels for every client, and what they hold, owe and earned has to
    // read the same whoever is looking.
    Route::withoutMiddleware('client.access')->group(function () {
        Route::get('/riders', [RiderController::class, 'page'])->middleware('permission:view riders')->name('riders');
        Route::get('/api/riders', [RiderController::class, 'index'])->middleware('permission:view riders')->name('api.riders.index');
        Route::get('/api/riders/presence', [RiderController::class, 'presence'])->middleware('permission:view riders')->name('api.riders.presence');
        Route::get('/api/riders/performance', [RiderController::class, 'performance'])->middleware('permission:view rider performance')->name('api.riders.performance');
        Route::get('/api/riders/{rider}/photo', [RiderController::class, 'photo'])->middleware('permission:view riders')->name('api.riders.photo');
        // Also the counts beside the filters on a rider's orders sheet.
        Route::get('/api/riders/{rider}/performance', [RiderController::class, 'riderPerformance'])->middleware('permission:view rider performance|view rider parcels')->name('api.riders.rider-performance');
        Route::get('/api/riders/{rider}/parcels', [RiderController::class, 'riderParcels'])->middleware('permission:view rider parcels')->name('api.riders.parcels');
        // What an inventory manager scanned OUT and IN.
        Route::get('/api/riders/{rider}/stock-scans', [StockScanController::class, 'manager'])->middleware('permission:view inventory manager scans')->name('api.riders.stock-scans');
        // Cash a rider owes and has handed in.
        Route::prefix('api/riders/{rider}/payments')->scopeBindings()->group(function () {
            Route::get('/', [RiderPaymentController::class, 'index'])->middleware('permission:view rider payments')->name('api.riders.payments.index');
            Route::post('/', [RiderPaymentController::class, 'store'])->middleware('permission:record rider payments')->name('api.riders.payments.store');
            Route::post('/{payment}/void', [RiderPaymentController::class, 'void'])->middleware('permission:void rider payments')->name('api.riders.payments.void');
        });
        Route::prefix('api/riders')->group(function () {
            // Before /{rider}: "support" isn't a rider id.
            Route::put('/support', [RiderController::class, 'updateSupport'])->middleware('permission:edit rider support contact')->name('api.riders.support');
            Route::post('/', [RiderController::class, 'store'])->middleware('permission:create riders')->name('api.riders.store');
            Route::put('/{rider}', [RiderController::class, 'update'])->middleware('permission:edit riders')->name('api.riders.update');
            Route::post('/{rider}/status', [RiderController::class, 'status'])->middleware('permission:suspend riders')->name('api.riders.status');
            Route::post('/{rider}/activation-link', [RiderController::class, 'activationLink'])->middleware('permission:send rider app link')->name('api.riders.activation-link');
            Route::post('/{rider}/pin', [RiderController::class, 'resetPin'])->middleware('permission:set rider pin')->name('api.riders.pin');
            Route::post('/{rider}/sign-out', [RiderController::class, 'signOut'])->middleware('permission:sign rider out')->name('api.riders.sign-out');
            Route::post('/{rider}/photo', [RiderController::class, 'uploadPhoto'])->middleware('permission:change rider photo')->name('api.riders.photo.upload');
            Route::delete('/{rider}/photo', [RiderController::class, 'removePhoto'])->middleware('permission:change rider photo')->name('api.riders.photo.remove');
            Route::delete('/{rider}', [RiderController::class, 'destroy'])->middleware('permission:delete riders')->name('api.riders.destroy');
        });
    });

    // Shipment analytics
    Route::get('/api/shipments-analytics', [ShipmentAnalyticsController::class, 'index'])->middleware('permission:view shipment analytics')->name('api.shipments.analytics');

    // J&T API health check
    Route::get('/api/jnt/health', [ShipmentController::class, 'health'])->middleware('permission:check courier api health')->name('api.jnt.health');

    // iMile API health check
    Route::get('/api/imile/health', [ShipmentController::class, 'health'])->defaults('courier', 'imile')->middleware('permission:check courier api health')->name('api.imile.health');

    // Shipping analytics admin page
    Route::get('/apps/jnt-express/analytics', fn () => Inertia::render('Apps/ShipmentAnalytics'))->middleware('permission:view shipment analytics')->name('apps.jnt-express.analytics');

    // Invoices API (waybill only)
    Route::get('/api/invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->middleware('permission:view waybills')->name('api.invoices.preview');
    Route::get('/api/invoices/{invoice}/download', [InvoiceController::class, 'download'])->middleware('permission:download waybills')->name('api.invoices.download');
    Route::delete('/api/invoices/{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:delete waybills')->name('api.invoices.destroy');

    Route::get('/chats', fn () => Inertia::render('Chats'))->name('chats');

    // Orders API
    Route::prefix('api/orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('permission:view orders')->name('api.orders.index');
        Route::get('/statistics', [OrderController::class, 'statistics'])->middleware('permission:view order stats|view order tag cards|view order shipment status cards|view dashboard order stats|view dashboard revenue|view dashboard tag cards|view dashboard shipment status')->name('api.orders.statistics');
        Route::get('/filter-options', [OrderController::class, 'filterOptions'])->middleware('permission:view orders')->name('api.orders.filter-options');
        Route::get('/export', [OrderController::class, 'export'])->middleware('permission:export orders')->name('api.orders.export');
        Route::get('/{order}', [OrderController::class, 'show'])->middleware('permission:view order details')->name('api.orders.show');
        Route::put('/{order}', [OrderController::class, 'update'])->middleware('permission:edit orders|tag orders')->name('api.orders.update');
        Route::post('/{order}/fulfillment-status', [OrderController::class, 'updateFulfillmentStatus'])->middleware('permission:update order fulfillment status')->name('api.orders.fulfillment-status');
        Route::post('/{order}/financial-status', [OrderController::class, 'updateFinancialStatus'])->middleware('permission:update order payment status')->name('api.orders.financial-status');
        Route::post('/{order}/call-status', [OrderController::class, 'updateCallStatus'])->middleware('permission:update order call status')->name('api.orders.call-status');
        Route::get('/{order}/whatsapp-messages', [OrderController::class, 'whatsappMessages'])->middleware('permission:view order whatsapp messages')->name('api.orders.whatsapp-messages');
        Route::post('/bulk-update', [OrderController::class, 'bulkUpdate'])->middleware('permission:update order fulfillment status|update order payment status|update order call status|tag orders|cancel orders')->name('api.orders.bulk-update');
        Route::post('/bulk-delete', [OrderController::class, 'bulkDestroy'])->middleware('permission:delete orders')->name('api.orders.bulk-delete');
        // Takes the id as a plain int, not a bound model: implicit binding applies
        // both SoftDeletingScope and the shopify_visible global scope, so it would
        // 404 on exactly the orders this needs to reach.
        Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('permission:delete orders')->name('api.orders.destroy');
    });

    // WhatsApp inbox API
    Route::prefix('api/whatsapp')->group(function () {
        // Unlock sits outside the whatsapp.unlocked gate (it is what opens it)
        // and is throttled so the PIN cannot be brute-forced. The prefix keeps
        // its attempt count apart from the recycle bin's.
        Route::post('/unlock', [WhatsAppConversationController::class, 'unlock'])
            ->middleware(['permission:view whatsapp conversations', 'throttle:5,1,whatsapp-unlock'])
            ->name('api.whatsapp.unlock');
        Route::post('/lock', [WhatsAppConversationController::class, 'lock'])->middleware('permission:view whatsapp conversations')->name('api.whatsapp.lock');

        // Counts only, and the dashboard tiles read them, so they stay outside the lock.
        Route::get('/stats', [WhatsAppConversationController::class, 'stats'])->middleware('permission:view whatsapp stats|view dashboard whatsapp stats')->name('api.whatsapp.stats');

        Route::middleware('whatsapp.unlocked')->group(function () {
            Route::get('/conversations', [WhatsAppConversationController::class, 'index'])->middleware('permission:view whatsapp conversations')->name('api.whatsapp.conversations');
            Route::get('/conversations/{order}', [WhatsAppConversationController::class, 'show'])->middleware('permission:view whatsapp conversations')->name('api.whatsapp.conversation');
            Route::post('/conversations/{order}/reply', [WhatsAppConversationController::class, 'reply'])->middleware('permission:reply whatsapp')->name('api.whatsapp.reply');

            // The messaging on/off switch.
            Route::put('/messaging', [WhatsAppConversationController::class, 'messaging'])->middleware('permission:toggle whatsapp messaging')->name('api.whatsapp.messaging');
        });
    });

    // Clients API
    Route::prefix('api/clients')->group(function () {
        Route::get('/', [ClientController::class, 'index'])->middleware('permission:view client')->name('api.clients.index');
        Route::get('/statistics', [ClientController::class, 'statistics'])->middleware('permission:view client stats|view dashboard client stats')->name('api.clients.statistics');
        Route::get('/filter-options', [ClientController::class, 'filterOptions'])->middleware('permission:view client')->name('api.clients.filter-options');
        Route::get('/export', [ClientController::class, 'export'])->middleware('permission:export clients')->name('api.clients.export');
        Route::post('/', [ClientController::class, 'store'])->middleware('permission:create client')->name('api.clients.store');
        Route::get('/{client}', [ClientController::class, 'show'])->middleware('permission:view client details')->name('api.clients.show');
        Route::put('/{client}', [ClientController::class, 'update'])->middleware('permission:edit client')->name('api.clients.update');
        Route::patch('/{client}/status', [ClientController::class, 'updateStatus'])->middleware('permission:change client status')->name('api.clients.update-status');
        Route::post('/{client}/reset-password', [ClientController::class, 'resetPassword'])->middleware('permission:change client password')->name('api.clients.reset-password');
        Route::post('/{client}/send-reset-link', [ClientController::class, 'sendPasswordResetLink'])->middleware('permission:send client reset link')->name('api.clients.send-reset-link');
        Route::post('/bulk-update', [ClientController::class, 'bulkUpdate'])->middleware('permission:change client status')->name('api.clients.bulk-update');
        Route::post('/bulk-delete', [ClientController::class, 'bulkDestroy'])->middleware('permission:delete client')->name('api.clients.bulk-delete');
        Route::delete('/{client}', [ClientController::class, 'destroy'])->middleware('permission:delete client')->name('api.clients.destroy');
        // Client Products (Inventory)
        Route::get('/{client}/products', [ClientController::class, 'products'])->middleware('permission:view client products')->name('api.clients.products.index');
        Route::post('/{client}/products', [ClientController::class, 'storeProduct'])->middleware('permission:create client products')->name('api.clients.products.store');
        Route::put('/{client}/products/{product}', [ClientController::class, 'updateProduct'])->middleware('permission:edit client products')->name('api.clients.products.update');
        Route::patch('/{client}/products/{product}/verify', [ClientController::class, 'verifyProduct'])->middleware('permission:review client products')->name('api.clients.products.verify');
        Route::patch('/{client}/products/{product}/review', [ClientController::class, 'reviewProduct'])->middleware('permission:review client products')->name('api.clients.products.review');
        Route::delete('/{client}/products/{product}', [ClientController::class, 'destroyProduct'])->middleware('permission:delete client products')->name('api.clients.products.destroy');
        Route::delete('/{client}/products/{product}/images/{image}', [ClientController::class, 'destroyProductImage'])->middleware('permission:delete client product images')->name('api.clients.products.images.destroy');

        Route::get('/{client}/payments', [ClientPaymentController::class, 'index'])->middleware('permission:view client payments')->name('api.clients.payments.index');
        Route::post('/{client}/payments', [ClientPaymentController::class, 'store'])->middleware('permission:record client payments')->name('api.clients.payments.store');
        Route::delete('/{client}/payments/{payment}', [ClientPaymentController::class, 'destroy'])->middleware('permission:delete client payments')->name('api.clients.payments.destroy');
    });

    // Parcels the inventory managers scanned OUT and IN, and what it did to stock.
    Route::get('/api/stock-scans', [StockScanController::class, 'index'])->middleware('permission:view stock scans')->name('api.stock-scans.index');

    // Inventory / Products API
    Route::prefix('api/products')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:view inventory')->name('api.products.index');
        Route::get('/statistics', [ProductController::class, 'statistics'])->middleware('permission:view inventory stats')->name('api.products.statistics');
        Route::get('/filter-options', [ProductController::class, 'filterOptions'])->middleware('permission:view inventory')->name('api.products.filter-options');
        Route::get('/export', [ProductController::class, 'export'])->middleware('permission:export inventory')->name('api.products.export');
        Route::post('/import', [ProductController::class, 'import'])->middleware('permission:import inventory')->name('api.products.import');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:view product details')->name('api.products.show');
        Route::put('/{product}', [ProductController::class, 'update'])->middleware('permission:edit inventory|change product status|publish products')->name('api.products.update');
        Route::post('/bulk-delete', [ProductController::class, 'bulkDestroy'])->middleware('permission:delete inventory')->name('api.products.bulk-delete');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:delete inventory')->name('api.products.destroy');
    });

    // Settings — a person's own account.
    Route::get('/settings', [SettingsController::class, 'profile'])->middleware('permission:view settings')->name('settings');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->middleware('permission:edit profile')->name('settings.profile.update');
    Route::post('/settings/avatar', [SettingsController::class, 'updateAvatar'])->middleware('permission:change avatar')->name('settings.avatar.update');
    Route::delete('/settings/avatar', [SettingsController::class, 'removeAvatar'])->middleware('permission:change avatar')->name('settings.avatar.remove');
    Route::get('/settings/account', fn () => Inertia::render('Settings/Account'))->middleware('permission:view settings')->name('settings.account');
    Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->middleware('permission:view settings')->name('settings.notifications');
    Route::put('/settings/notifications', [SettingsController::class, 'updateNotificationPreferences'])->middleware('permission:edit notification preferences')->name('settings.notifications.update');
    Route::get('/settings/email', [SettingsController::class, 'email'])->middleware('permission:view settings')->name('settings.email');
    Route::put('/settings/email/preferences', [SettingsController::class, 'updateEmailPreferences'])->middleware('permission:edit email preferences')->name('settings.email.preferences.update');
    Route::put('/settings/email/smtp', [SettingsController::class, 'updateEmailSettings'])->middleware('permission:edit email settings')->name('settings.email.smtp.update');
    Route::get('/settings/security', [SettingsController::class, 'security'])->middleware('permission:view settings')->name('settings.security');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->middleware('permission:change password')->name('settings.password.update');
    Route::middleware('permission:manage two-factor')->group(function () {
        Route::post('/settings/two-factor/enable', [SettingsController::class, 'enableTwoFactor'])->name('settings.two-factor.enable');
        Route::post('/settings/two-factor/confirm', [SettingsController::class, 'confirmTwoFactor'])->name('settings.two-factor.confirm');
        Route::delete('/settings/two-factor', [SettingsController::class, 'disableTwoFactor'])->name('settings.two-factor.disable');
        Route::get('/settings/two-factor/recovery-codes', [SettingsController::class, 'showRecoveryCodes'])->name('settings.two-factor.recovery-codes');
        Route::post('/settings/two-factor/recovery-codes', [SettingsController::class, 'regenerateRecoveryCodes'])->name('settings.two-factor.recovery-codes.regenerate');
    });

    // Team Management
    Route::get('/team-management/roles', [RoleController::class, 'index'])->middleware('permission:view roles')->name('team-management.roles');
    Route::post('/team-management/roles', [RoleController::class, 'store'])->middleware('permission:create roles')->name('team-management.roles.store');
    Route::put('/team-management/roles/{role}', [RoleController::class, 'update'])->middleware('permission:edit roles')->name('team-management.roles.update');
    Route::delete('/team-management/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete roles')->name('team-management.roles.destroy');

    // Read-only: the permissions are the catalog (App\Support\PermissionCatalog).
    Route::get('/team-management/permissions', [PermissionController::class, 'index'])->middleware('permission:view permissions')->name('team-management.permissions');

    Route::get('/team-management/teams', [TeamController::class, 'index'])->middleware('permission:view teams')->name('team-management.teams');
    Route::post('/team-management/teams', [TeamController::class, 'store'])->middleware('permission:create teams')->name('team-management.teams.store');
    Route::put('/team-management/teams/{team}', [TeamController::class, 'update'])->middleware('permission:edit teams')->name('team-management.teams.update');
    Route::delete('/team-management/teams/{team}', [TeamController::class, 'destroy'])->middleware('permission:delete teams')->name('team-management.teams.destroy');

    Route::get('/team-management/users', [UserRoleController::class, 'index'])->middleware('permission:view users')->name('team-management.users');
    Route::post('/team-management/users', [UserRoleController::class, 'store'])->middleware('permission:create users')->name('team-management.users.store');
    Route::post('/team-management/users/bulk-delete', [UserRoleController::class, 'bulkDestroy'])->middleware('permission:delete users')->name('team-management.users.bulk-destroy');
    Route::put('/team-management/users/{user}', [UserRoleController::class, 'update'])->middleware('permission:edit users')->name('team-management.users.update');
    Route::delete('/team-management/users/{user}', [UserRoleController::class, 'destroy'])->middleware('permission:delete users')->name('team-management.users.destroy');

    // Recycle Bin
    // Each tab has its own three permissions (see, restore, delete forever),
    // and emptying a tab needs 'empty recycle bin' on top of that tab's delete
    // forever. Note the separator inside one middleware is a PIPE and means
    // "any of": two permissions that must both hold are two middlewares.
    Route::get('/recycle-bin', [RecycleBinController::class, 'page'])
        ->middleware('permission:view recycle bin')->name('recycle-bin');

    Route::prefix('api/recycle-bin')->group(function () {
        // Unlock sits outside the recyclebin.unlocked gate (it is what opens
        // it) and is throttled so the PIN cannot be brute-forced.
        Route::post('/unlock', [RecycleBinController::class, 'unlock'])
            ->middleware(['permission:view recycle bin', 'throttle:5,1'])
            ->name('api.recycle-bin.unlock');
        Route::post('/lock', [RecycleBinController::class, 'lock'])
            ->middleware('permission:view recycle bin')
            ->name('api.recycle-bin.lock');

        Route::get('/counts', [RecycleBinController::class, 'counts'])
            ->middleware('recyclebin.unlocked')->middleware('permission:view recycle bin')->name('api.recycle-bin.counts');

        foreach (['orders' => 'Orders', 'clients' => 'Clients', 'users' => 'Users', 'riders' => 'Riders', 'inventory' => 'Inventory'] as $tab => $suffix) {
            // The inventory tab holds two kinds of row, catalogue products and
            // clients' own, each with its own permissions: either opens the
            // tab and the controller keeps to the kind the person may touch.
            $any = fn (string $action) => $tab === 'inventory'
                ? "permission:{$action} deleted inventory|{$action} deleted client products"
                : "permission:{$action} deleted {$tab}";

            // Riders sit outside the client restriction, here as on their own
            // page: deleting one for good has to reach every parcel they carried.
            Route::withoutMiddleware($tab === 'riders' ? ['client.access'] : [])->group(function () use ($tab, $suffix, $any) {
                Route::get("/{$tab}", [RecycleBinController::class, $tab])
                    ->middleware('recyclebin.unlocked')->middleware($any('view'))->name("api.recycle-bin.{$tab}");
                Route::post("/{$tab}/restore", [RecycleBinController::class, "restore{$suffix}"])
                    ->middleware('recyclebin.unlocked')->middleware($any('restore'))->name("api.recycle-bin.{$tab}.restore");
                Route::post("/{$tab}/purge", [RecycleBinController::class, "purge{$suffix}"])
                    ->middleware('recyclebin.unlocked')->middleware($any('purge'))->name("api.recycle-bin.{$tab}.purge");
                Route::post("/{$tab}/purge-all", [RecycleBinController::class, "purgeAll{$suffix}"])
                    ->middleware('recyclebin.unlocked')->middleware('permission:empty recycle bin')->middleware($any('purge'))->name("api.recycle-bin.{$tab}.purge-all");
            });
        }
    });

    // Tags
    Route::get('/tags', [TagController::class, 'index'])->middleware('permission:view tags')->name('tags');
    // Also read by the orders list, which shows each order's tag in its colour.
    Route::get('/api/tags', [TagController::class, 'list'])->middleware('permission:view tags|tag orders|view orders')->name('api.tags.index');
    Route::post('/tags', [TagController::class, 'store'])->middleware('permission:create tags')->name('tags.store');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->middleware('permission:edit tags')->name('tags.update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->middleware('permission:delete tags')->name('tags.destroy');

    // Help
    Route::get('/help-center', fn () => Inertia::render('HelpCenter'))->middleware('permission:view help center')->name('help-center');

    // Admin Email Settings
    Route::prefix('admin')->group(function () {
        Route::get('/email-settings', [EmailSettingsController::class, 'index'])->middleware('permission:view email settings')->name('admin.email-settings');
        Route::put('/email-settings', [EmailSettingsController::class, 'update'])->middleware('permission:edit email settings')->name('admin.email-settings.update');
        Route::post('/email-settings/test', [EmailSettingsController::class, 'test'])->middleware('permission:send test email')->name('admin.email-settings.test');
        Route::get('/email-logs', [EmailSettingsController::class, 'logs'])->middleware('permission:view email logs')->name('admin.email-logs');
        Route::get('/email-statistics', [EmailSettingsController::class, 'statistics'])->middleware('permission:view email statistics')->name('admin.email-statistics');
        Route::delete('/email-graveyard/{entry}', [EmailSettingsController::class, 'restoreFromGraveyard'])->middleware('permission:restore graveyard emails')->name('admin.email-graveyard.restore');
    });
});

// Impersonate
Route::middleware(['auth', 'verified', 'client.access'])->group(function () {
    Route::post('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
    Route::post('/impersonate/{client}', [ImpersonateController::class, 'impersonate'])->name('impersonate.start');
});

// Shared API endpoints
Route::middleware(['auth', 'verified', 'client.access'])->group(function () {
    Route::get('/api/connectors/enabled', [ConnectorController::class, 'enabledWithComingSoon'])->name('api.connectors.enabled');

    // Notifications — every role, clients included: the header bell polls these
    // on every page, and the controller scopes strictly to the logged-in user.
    // Keeping them in the role:!client group made the client portal log 403s
    // on every pageview (flagged by Shopify app review as web errors).
    Route::get('/notifications', [NotificationController::class, 'page'])->name('notifications');
    Route::get('/api/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('/api/notifications/unread-count', [NotificationController::class, 'unread'])->name('api.notifications.unread');
    Route::post('/api/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('api.notifications.read');
    Route::post('/api/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('api.notifications.read-all');
    Route::delete('/api/notifications/{id}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');
});

// Shopify OAuth callback — must live at the redirect_uri path (not under /portal).
// Public: it is reached both by logged-in clients (portal-initiated connect) and
// by merchants installing straight from the Shopify App Store, who have no portal
// session at all. Authenticity is enforced inside the controller (signed state +
// Shopify HMAC), not by login middleware — an auth redirect here breaks the
// OAuth hop and fails Shopify app review.
Route::get('/shopify/callback', [ShopifyController::class, 'callback'])->name('shopify.callback');

// Client Portal - account restricted page (no role:client check so suspended clients can see it)
Route::middleware(['auth', 'verified'])->get('/portal/suspended', fn () => Inertia::render('Portal/Suspended'))->name('portal.suspended');

// Client Portal
Route::prefix('portal')->middleware(['auth', 'verified', 'role:client', 'client.access'])->group(function () {
    Route::get('/', fn () => Inertia::render('Portal/Dashboard'))->name('portal.dashboard');
    Route::get('/orders', fn () => Inertia::render('Portal/Orders'))->name('portal.orders');
    Route::get('/inventory', fn () => Inertia::render('Portal/Inventory'))->name('portal.inventory');
    Route::get('/revenue', fn () => Inertia::render('Portal/Revenue'))->name('portal.revenue');
    Route::get('/finance', fn () => Inertia::render('Portal/Finance'))->name('portal.finance');
    Route::get('/products', fn () => Inertia::render('Portal/Products'))->name('portal.products');
    Route::get('/connectors', fn () => Inertia::render('Portal/Connectors'))->name('portal.connectors');
    Route::get('/settings', fn () => Inertia::render('Portal/Settings/CompanyProfile'))->name('portal.settings');
    Route::get('/settings/security', [SettingsController::class, 'portalSecurity'])->name('portal.settings.security');

    // Portal API
    Route::get('/api/dashboard', [PortalController::class, 'dashboard'])->name('portal.api.dashboard');
    Route::get('/api/orders', [PortalController::class, 'orders'])->name('portal.api.orders');
    Route::post('/api/orders/import', [PortalController::class, 'importOrders'])->name('portal.api.orders.import');
    Route::post('/api/orders', [PortalController::class, 'storeOrder'])->name('portal.api.orders.store');
    Route::get('/api/orders/import-template', [PortalController::class, 'ordersImportTemplate'])->name('portal.api.orders.import-template');
    Route::get('/api/orders/statistics', [PortalController::class, 'orderStatistics'])->name('portal.api.orders.statistics');
    Route::get('/api/orders/filter-options', [PortalController::class, 'orderFilterOptions'])->name('portal.api.orders.filter-options');
    Route::get('/api/orders/export', [PortalController::class, 'exportOrders'])->name('portal.api.orders.export');
    Route::get('/api/orders/{order}', [PortalController::class, 'showOrder'])->name('portal.api.orders.show');
    Route::get('/api/sku-search', [PortalController::class, 'skuSearch'])->name('portal.api.sku-search');
    Route::get('/api/inventory', [PortalController::class, 'inventory'])->name('portal.api.inventory');
    Route::post('/api/inventory', [PortalController::class, 'storeInventory'])->name('portal.api.inventory.store');
    Route::put('/api/inventory/{product}', [PortalController::class, 'updateInventory'])->name('portal.api.inventory.update');
    Route::delete('/api/inventory/{product}', [PortalController::class, 'destroyInventory'])->name('portal.api.inventory.destroy');
    Route::delete('/api/inventory/{product}/images/{image}', [PortalController::class, 'destroyInventoryImage'])->name('portal.api.inventory.images.destroy');
    Route::get('/api/products', [PortalController::class, 'products'])->name('portal.api.products');
    Route::get('/api/products/filter-options', [PortalController::class, 'productFilterOptions'])->name('portal.api.products.filter-options');
    Route::get('/api/products/download', [PortalController::class, 'productsDownload'])->name('portal.api.products.download');
    Route::get('/api/products/{product}', [PortalController::class, 'productShow'])->name('portal.api.products.show');
    Route::get('/api/revenue', [PortalController::class, 'revenue'])->name('portal.api.revenue');
    Route::get('/api/finance', [PortalController::class, 'finance'])->name('portal.api.finance');
    Route::get('/api/invoices/{invoice}/preview', [InvoiceController::class, 'portalPreview'])->name('portal.api.invoices.preview');
    Route::get('/api/invoices/{invoice}/download', [InvoiceController::class, 'portalDownload'])->name('portal.api.invoices.download');
    Route::post('/settings/company-profile', [PortalController::class, 'updateCompanyProfile'])->name('portal.settings.company-profile.update');
    Route::post('/settings/logo', [PortalController::class, 'updateLogo'])->name('portal.settings.logo.update');
    Route::delete('/settings/logo', [PortalController::class, 'removeLogo'])->name('portal.settings.logo.remove');

    // Shopify — connection management. Note there is no route that starts a
    // fresh OAuth handshake from the portal: installation only ever begins on
    // a Shopify-owned surface (App Store install, or reopening the app from
    // Shopify Admin) — App Store review requirement 2.3.1. This only claims
    // an already-installed, unlinked connection.
    Route::post('/api/shopify/claim', [ShopifyController::class, 'claim'])->name('portal.shopify.claim');
    Route::delete('/api/shopify/disconnect', [ShopifyController::class, 'disconnect'])->name('portal.shopify.disconnect');
    Route::put('/api/shopify/sync-mode', [ShopifyController::class, 'updateSyncMode'])->name('portal.shopify.sync-mode');
    Route::post('/api/shopify/retry-webhooks', [ShopifyController::class, 'retryWebhooks'])->name('portal.shopify.retry-webhooks');

    // Shopify — manual-approval queue
    Route::get('/api/shopify/pending', [ShopifyPendingOrderController::class, 'index'])->name('portal.shopify.pending.index');
    Route::post('/api/shopify/pending/submit-bulk', [ShopifyPendingOrderController::class, 'submitBulk'])->name('portal.shopify.pending.submit-bulk');
    Route::post('/api/shopify/pending/{orderId}/submit', [ShopifyPendingOrderController::class, 'submit'])->name('portal.shopify.pending.submit');
    Route::delete('/api/shopify/pending/{orderId}/dismiss', [ShopifyPendingOrderController::class, 'dismiss'])->name('portal.shopify.pending.dismiss');

    // Shopify — orders that failed to sync, and manual replay
    Route::get('/api/shopify/failures', [ShopifySyncFailureController::class, 'index'])->name('portal.shopify.failures.index');
    Route::get('/api/shopify/failures/count', [ShopifySyncFailureController::class, 'count'])->name('portal.shopify.failures.count');
    Route::post('/api/shopify/failures/retry-all', [ShopifySyncFailureController::class, 'retryAll'])->name('portal.shopify.failures.retry-all');
    Route::post('/api/shopify/failures/{failureId}/retry', [ShopifySyncFailureController::class, 'retry'])->name('portal.shopify.failures.retry');
    Route::delete('/api/shopify/failures/{failureId}', [ShopifySyncFailureController::class, 'discard'])->name('portal.shopify.failures.discard');
});

// Merchant pricing & off-platform billing disclosure — public, no auth.
// Linked from the Shopify App Store listing ("Pricing details" → off-platform
// billing), so it must stay reachable without a session.
Route::get('/pricing', fn () => Inertia::render('Pricing'))->name('pricing');
Route::redirect('/billing', '/pricing');

// Tracking — single search page + JSON API for AJAX lookup. Public, so both
// are throttled per IP to stop bots hammering the database or guessing
// order numbers.
Route::get('/track', [TrackingController::class, 'search'])->middleware('throttle:60,1')->name('tracking.search');
Route::get('/api/track/{identifier}', [TrackingController::class, 'api'])->middleware('throttle:20,1')->name('tracking.api');

// Webhooks (no auth, no CSRF)
Route::post('/webhooks/jnt-express', [WebhookController::class, 'handleJntExpress'])->name('webhooks.jnt-express');
Route::post('/webhooks/jnt-express/tracking', [WebhookController::class, 'handleJntTracking'])->name('webhooks.jnt-express.tracking');
Route::post('/webhooks/jnt-express/return', [WebhookController::class, 'handleJntReturn'])->name('webhooks.jnt-express.return');
Route::post('/webhooks/jnt-express/cod', [WebhookController::class, 'handleJntCod'])->name('webhooks.jnt-express.cod');
Route::post('/webhooks/jnt-express/otp', [WebhookController::class, 'handleJntOtp'])->name('webhooks.jnt-express.otp');

// iMile tracking-push webhook — no signature to verify (iMile support confirmed
// the payload requires no encryption/decryption); see ImileWebhookController.
Route::post('/webhooks/imile/tracking', [ImileWebhookController::class, 'handleTracking'])->name('webhooks.imile.tracking');

// LogesTechs (Navix) status webhook — authenticated by the webhookUsername/
// webhookPassword pair LogesTechs echoes in the payload (set in their portal's
// webhook panel; no signature exists). Fires once per status change with no
// retry, so SyncShipmentTracking is the backstop. See LogesTechsWebhookController.
Route::post('/webhooks/logestechs/tracking', [LogesTechsWebhookController::class, 'handleTracking'])->name('webhooks.logestechs.tracking');

// Meta WhatsApp Cloud API — one URL for everything. GET is Meta's one-time
// subscription handshake; POST carries both inbound customer replies and
// delivery/read receipts, verified against the X-Hub-Signature-256 HMAC
// inside the controller. Register this single URL in the Meta app dashboard.
Route::get('/webhooks/whatsapp', [MetaWhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [MetaWhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp');

// Shopify webhooks — public, HMAC-verified inside the controller (CSRF excluded via bootstrap/app.php 'webhooks/*')
//
// Stripped of the session/cookie/Inertia half of the `web` group. Shopify must
// get a response inside five seconds or it records the delivery as failed, and
// the session middleware alone was spending two database round trips on every
// single webhook to no purpose: Shopify sends no cookie, so StartSession opened
// a brand-new session each time and wrote a junk row to the `sessions` table
// that nothing would ever read.
//
// The row itself was not the expensive part — the table it grew was. Session
// garbage collection fires on a [2, 100] lottery, so roughly one webhook in
// fifty ran DELETE FROM sessions WHERE last_activity <= ? across a table these
// same webhooks had been inflating for months. On MySQL that scan takes seconds
// and locks, and every concurrent webhook waiting behind it blew the five-second
// budget too — which is why the failures arrive in bursts rather than evenly.
//
// Nothing here needs any of it: the handler verifies an HMAC, queues a job and
// returns a bare 200. It never reads the session, sets a cookie, or renders a
// view.
Route::post('/webhooks/shopify', [ShopifyWebhookController::class, 'handle'])
    ->withoutMiddleware([
        // Must go together with StartSession. The path is already CSRF-exempt
        // via bootstrap/app.php, but the middleware still runs and still tries
        // to attach an XSRF cookie on the way out — which reads the session,
        // and with no session store on the request that throws, turning every
        // webhook into a 500. Removing the session without removing this is
        // strictly worse than leaving both in place.
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\AddLinkHeadersForPreloadedAssetsUnlessInertia::class,
    ])
    ->name('webhooks.shopify');

// Fulfillment service callbacks. Shopify is given only the prefix (at
// fulfillmentServiceCreate) and appends these three paths itself, so the names
// and shapes are Shopify's, not ours.
//
// Same middleware exclusions, for the same reasons as the webhook route above:
// Shopify sends no cookie and reads no response body beyond the status.
Route::prefix('webhooks/shopify/fulfillment')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\AddLinkHeadersForPreloadedAssetsUnlessInertia::class,
    ])
    ->group(function () {
        Route::post('/fulfillment_order_notification', [ShopifyFulfillmentCallbackController::class, 'notification'])
            ->name('webhooks.shopify.fulfillment.notification');
        Route::get('/fetch_stock', [ShopifyFulfillmentCallbackController::class, 'fetchStock'])
            ->name('webhooks.shopify.fulfillment.stock');
        // Shopify's older fulfillment-service contract appended .json to these
        // paths. Answering both costs nothing and removes one way for stock
        // lookups to 404 without anyone noticing.
        Route::get('/fetch_stock.json', [ShopifyFulfillmentCallbackController::class, 'fetchStock'])
            ->name('webhooks.shopify.fulfillment.stock.json');
        Route::get('/fetch_tracking_numbers', [ShopifyFulfillmentCallbackController::class, 'fetchTrackingNumbers'])
            ->name('webhooks.shopify.fulfillment.tracking');
    });

// Embedded Shopify Admin app — rendered inside the Shopify Admin iframe.
// No Laravel session: the shell loads publicly (App Bridge boots it), and the
// API routes are authenticated per-request via App Bridge session tokens (JWT).
Route::prefix('embedded/shopify')->middleware('shopify.csp')->group(function () {
    Route::get('/', [EmbeddedAppController::class, 'index'])->name('embedded.shopify.index');
    Route::get('/settings', [EmbeddedAppController::class, 'index'])->name('embedded.shopify.settings');

    // Verifies the session token itself rather than via shopify.session —
    // that middleware requires an already-linked client, which an unlinked
    // store doesn't have yet. Used to mint the claim token for onboarding.
    Route::get('/api/claim-token', [EmbeddedAppController::class, 'claimToken'])->name('embedded.shopify.claim-token');

    Route::middleware('shopify.session')->prefix('api')->group(function () {
        Route::get('/dashboard', [EmbeddedDashboardController::class, 'index'])->name('embedded.shopify.dashboard');
        Route::get('/settings', [EmbeddedSettingsController::class, 'show'])->name('embedded.shopify.settings.show');
        Route::put('/settings', [EmbeddedSettingsController::class, 'update'])->name('embedded.shopify.settings.update');

        // The merchant asking us for an order, from our own app rather than
        // from Shopify admin's Request fulfillment action. Both end at the same
        // mutation (App Store requirement 5.5.1).
        Route::post('/orders/{order}/request-fulfillment', [EmbeddedDashboardController::class, 'requestFulfillment'])
            ->name('embedded.shopify.orders.request-fulfillment');
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
