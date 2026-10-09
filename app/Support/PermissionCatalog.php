<?php

namespace App\Support;

use App\Models\PermissionCategory;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every permission in the staff portal, in one place.
 *
 * One permission per thing a person can see or press: each page, each section
 * of a page, each button, menu item and bulk action. The migration, the
 * seeder, the role editor and the tests all read this list, so a permission
 * that is not here does not exist.
 *
 * Adding a feature means adding its permission here, gating the route (or the
 * controller, when one endpoint serves several buttons) and gating the button.
 */
final class PermissionCatalog
{
    /**
     * module key => label, description, groups.
     * group label => permission name => [label, description, requires?].
     *
     * `requires` names the permission without which this one is useless (a
     * button on a page needs the page). It defaults to the module's first
     * permission; pass null for none.
     */
    private const MODULES = [
        'dashboard' => [
            'label' => 'Dashboard',
            'description' => 'The home page and each block of numbers on it.',
            'groups' => [
                'Page' => [
                    'view dashboard' => ['Open the dashboard', 'See the Dashboard page.', null],
                ],
                'Sections' => [
                    'view dashboard order stats' => ['Order numbers', 'Total orders, orders today and awaiting shipment.'],
                    'view dashboard revenue' => ['Revenue numbers', 'Total revenue, today\'s revenue and average order value.'],
                    'view dashboard tag cards' => ['Tag cards', 'Orders counted per tag.'],
                    'view dashboard shipment status' => ['Shipment status cards', 'Orders counted per shipment status.'],
                    'view dashboard whatsapp stats' => ['WhatsApp numbers', 'The WhatsApp confirmation counts.'],
                    'view dashboard client stats' => ['Client numbers', 'Total, active, inactive and suspended clients.'],
                ],
            ],
        ],

        'client' => [
            'label' => 'Clients',
            'description' => 'The client list and each client\'s account.',
            'groups' => [
                'Page' => [
                    'view client' => ['Open the client list', 'See the Clients page and its list.', null],
                    'view client stats' => ['Client stat cards', 'The cards above the client list.'],
                    'view client details' => ['Open a client', 'Open a client\'s own page.'],
                    'view client revenue' => ['Client revenue', 'A client\'s order count and total revenue.', 'view client details'],
                ],
                'Actions' => [
                    'create client' => ['Create client', 'The Create Client button.'],
                    'edit client' => ['Edit client details', 'Company, contact and address details.'],
                    'edit client charges' => ['Edit client charges', 'The charges section of the edit form.', 'edit client'],
                    'edit client portal features' => ['Edit portal features', 'Which portal sections the client can open.', 'edit client'],
                    'edit client notes' => ['Edit client notes', 'The internal notes on a client.', 'edit client'],
                    'change client status' => ['Change client status', 'Activate, deactivate or suspend a client.'],
                    'change client password' => ['Change client password', 'Set a new portal password for a client.'],
                    'send client reset link' => ['Send reset link', 'Email a client a password reset link.'],
                    'impersonate client' => ['View client portal', 'Open the portal as the client.'],
                    'export clients' => ['Export clients', 'The Export CSV button.'],
                    'delete client' => ['Delete client', 'Move a client to the recycle bin.'],
                ],
            ],
        ],

        'client products' => [
            'label' => 'Client products',
            'description' => 'The Inventory tab on a client\'s page.',
            'groups' => [
                'Page' => [
                    'view client products' => ['Open the Inventory tab', 'See a client\'s products.', 'view client details'],
                ],
                'Actions' => [
                    'create client products' => ['Add product', 'Add a product for a client.'],
                    'edit client products' => ['Edit product', 'Change a client\'s product.'],
                    'review client products' => ['Review & verify', 'Verify, reject or return a product to pending.'],
                    'delete client product images' => ['Delete product images', 'Remove an image from a client\'s product.'],
                    'delete client products' => ['Delete product', 'Move a client\'s product to the recycle bin.'],
                ],
            ],
        ],

        'client payments' => [
            'label' => 'Client payments',
            'description' => 'The Payments tab on a client\'s page.',
            'groups' => [
                'Page' => [
                    'view client payments' => ['Open the Payments tab', 'See payments recorded for a client.', 'view client details'],
                    'view client payment proof' => ['View payment proof', 'Open the proof attached to a payment.'],
                ],
                'Actions' => [
                    'record client payments' => ['Record payment', 'The Record Payment button.'],
                    'delete client payments' => ['Delete payment', 'Remove a recorded payment.'],
                ],
            ],
        ],

        'orders' => [
            'label' => 'Orders',
            'description' => 'The orders list and everything done to an order.',
            'groups' => [
                'Page' => [
                    'view orders' => ['Open the orders list', 'See the Orders page and its list.', null],
                    'view order stats' => ['Tab counts', 'The order counts on the All / Other Couriers / KSA Express tabs.'],
                    'view order tag cards' => ['Tag cards', 'Orders counted per tag.'],
                    'view order shipment status cards' => ['Shipment status cards', 'Orders counted per shipment status.'],
                    'view order details' => ['Open an order', 'The View Details dialog.'],
                    'view order whatsapp messages' => ['WhatsApp history', 'The WhatsApp messages sent about an order.', 'view order details'],
                ],
                'Actions' => [
                    'edit orders' => ['Edit order', 'Change an order\'s details.', 'view order details'],
                    'tag orders' => ['Manage tag', 'Set or change an order\'s tag.'],
                    'update order fulfillment status' => ['Change fulfillment status', 'Pending, unfulfilled, fulfilled or cancelled.'],
                    'update order payment status' => ['Change payment status', 'Pending, paid, refunded or partially refunded.'],
                    'update order call status' => ['Save call outcome', 'Record how the confirmation call went.', 'view order details'],
                    'cancel orders' => ['Cancel orders', 'Mark orders cancelled, keeping them in the list.'],
                    'export orders' => ['Export orders', 'The Export CSV button.'],
                    'delete orders' => ['Delete orders', 'Move orders to the recycle bin.'],
                ],
            ],
        ],

        'shipments' => [
            'label' => 'Shipments',
            'description' => 'The shipment on an order and each button on it.',
            'groups' => [
                'Page' => [
                    'view shipments' => ['See shipments', 'The shipment panel on an order.', 'view order details'],
                    'view delivery proof photos' => ['Delivery proof photos', 'Photos a rider took at the door.'],
                ],
                'Actions' => [
                    'create shipments' => ['Create shipment', 'Book an order with a courier, one or many at a time.'],
                    'edit shipments' => ['Edit shipment', 'Change a shipment\'s details.'],
                    'refresh shipment tracking' => ['Refresh tracking', 'Ask the courier for the latest status.'],
                    'cancel shipments' => ['Cancel shipment', 'Cancel a shipment with the courier.'],
                    'escalate shipments' => ['Escalate exception', 'Add a note and notify admins.'],
                    'mark shipments returned' => ['Mark returned', 'Mark a KSA Express parcel as returned.'],
                    'receive shipments at hub' => ['Mark received', 'Take a parcel back in at the hub.'],
                    'unassign shipment rider' => ['Unassign rider', 'Take a parcel off a rider.'],
                ],
            ],
        ],

        'waybills' => [
            'label' => 'Waybills',
            'description' => 'The waybill documents on a shipment.',
            'groups' => [
                'Page' => [
                    'view waybills' => ['Preview waybills', 'Open a waybill in the browser.', 'view shipments'],
                ],
                'Actions' => [
                    'download waybills' => ['Download waybills', 'Download a waybill file.'],
                    'generate waybills' => ['Generate waybills', 'Create waybills, one or many at a time.'],
                    'delete waybills' => ['Delete waybills', 'Remove a generated waybill.'],
                ],
            ],
        ],

        'shipment analytics' => [
            'label' => 'Shipment analytics',
            'description' => 'The courier analytics page.',
            'groups' => [
                'Page' => [
                    'view shipment analytics' => ['Open shipment analytics', 'Shipment totals, trends, exceptions and returns.', null],
                    'view cod remittances' => ['COD remittances', 'Cash on delivery collected by the courier.'],
                ],
                'Actions' => [
                    'check courier api health' => ['Check API health', 'Test whether the courier\'s API answers.', null],
                ],
            ],
        ],

        'whatsapp' => [
            'label' => 'WhatsApp',
            'description' => 'The WhatsApp confirmation inbox.',
            'groups' => [
                'Page' => [
                    'view whatsapp' => ['Open the WhatsApp page', 'See the WhatsApp page.', null],
                    'view whatsapp stats' => ['WhatsApp numbers', 'The counts above the inbox.'],
                    'view whatsapp conversations' => ['Read conversations', 'Open the inbox and read messages.'],
                ],
                'Actions' => [
                    'reply whatsapp' => ['Send reply', 'Reply to a customer.', 'view whatsapp conversations'],
                    // Behind the inbox PIN with the conversations.
                    'toggle whatsapp messaging' => ['Switch messaging on/off', 'The messaging switch on the WhatsApp page.', 'view whatsapp conversations'],
                ],
            ],
        ],

        'riders' => [
            'label' => 'Riders',
            'description' => 'KSA Express riders and inventory managers.',
            'groups' => [
                'Page' => [
                    'view riders' => ['Open the riders page', 'See the Riders page and its list.', null],
                    'view rider stats' => ['Rider numbers', 'Active, online, signed in, parcels and deliveries.'],
                    'view rider cash stats' => ['Rider cash numbers', 'Cash collected, cash owed and pay owed.'],
                    'view rider performance' => ['Rider performance', 'Top performers and each rider\'s performance.'],
                    'view rider parcels' => ['Rider orders', 'The parcels a rider holds or handled.'],
                    'view rider payments' => ['Rider cash & pay', 'What a rider owes, was paid and handed in.'],
                ],
                'Actions' => [
                    'create riders' => ['Add rider', 'The Add rider button.'],
                    'edit riders' => ['Edit rider details', 'Change a rider\'s details and pay rates.'],
                    'change rider photo' => ['Change rider photo', 'Upload or remove a rider\'s photo.'],
                    'send rider app link' => ['Send app link', 'Create the link that signs a rider in to the app.'],
                    'set rider pin' => ['Give or reset PIN', 'Set the PIN a rider signs in with.'],
                    'sign rider out' => ['Sign phone out', 'Sign a rider\'s phone out of the app.'],
                    'suspend riders' => ['Suspend / reactivate', 'Suspend a rider or switch them back on.'],
                    'delete riders' => ['Remove rider', 'Move a rider to the recycle bin.'],
                    'record rider payments' => ['Record rider payment', 'Record cash handed in or pay given.', 'view rider payments'],
                    'void rider payments' => ['Void rider payment', 'Cancel a recorded payment.', 'view rider payments'],
                    'edit rider support contact' => ['Rider support contact', 'The WhatsApp number riders see for help.'],
                ],
            ],
        ],

        'inventory' => [
            'label' => 'Inventory',
            'description' => 'The product catalogue.',
            'groups' => [
                'Page' => [
                    'view inventory' => ['Open the inventory list', 'See the Inventory page and its list.', null],
                    'view inventory stats' => ['Inventory numbers', 'Product count, stock value, low and out of stock.'],
                    'view product details' => ['Open a product', 'The View Details dialog.'],
                    'view stock scans' => ['Scan log', 'Parcels scanned out of and in to the warehouse.'],
                ],
                'Actions' => [
                    'edit inventory' => ['Edit product', 'Change a product\'s details.'],
                    'change product status' => ['Change product status', 'Active, draft or archived.'],
                    'publish products' => ['Publish / unpublish', 'Show or hide a product for clients.'],
                    'import inventory' => ['Import CSV', 'The Import CSV button.'],
                    'export inventory' => ['Export CSV', 'The Export CSV button.'],
                    'delete inventory' => ['Delete product', 'Move products to the recycle bin.'],
                ],
            ],
        ],

        'tags' => [
            'label' => 'Tags',
            'description' => 'Order tags.',
            'groups' => [
                'Page' => [
                    'view tags' => ['Open the tags page', 'See the Tags page.', null],
                ],
                'Actions' => [
                    'create tags' => ['Add tag', 'The Add Tag button.'],
                    'edit tags' => ['Edit tag', 'Change a tag.'],
                    'delete tags' => ['Delete tag', 'Remove a tag.'],
                ],
            ],
        ],

        'apps' => [
            'label' => 'Connectors',
            'description' => 'Couriers, WhatsApp and their settings.',
            'groups' => [
                'Page' => [
                    'view apps' => ['Open the connectors page', 'See the Connectors page.', null],
                ],
                'Connectors' => [
                    'toggle connectors' => ['Switch a connector on/off', 'Enable or disable a connector.'],
                    'configure jnt connector' => ['J&T Express settings', 'Open and save the J&T Express settings.'],
                    'configure imile connector' => ['iMile settings', 'Open and save the iMile settings.'],
                    'configure logestechs connector' => ['LogesTechs settings', 'Open and save the LogesTechs settings.'],
                    'configure whatsapp connector' => ['WhatsApp settings', 'Open and save the WhatsApp settings.'],
                    'reveal connector secrets' => ['Reveal saved secrets', 'Show a saved API key or password.'],
                    'test connector connection' => ['Test connection', 'The Test Connection button.'],
                ],
                'Warehouses' => [
                    'view warehouses' => ['See warehouses', 'The Warehouses tab.'],
                    'create warehouses' => ['Add warehouse', 'Add a pickup warehouse.', 'view warehouses'],
                    'edit warehouses' => ['Edit warehouse', 'Change a warehouse.', 'view warehouses'],
                    'delete warehouses' => ['Delete warehouse', 'Remove a warehouse.', 'view warehouses'],
                ],
            ],
        ],

        'recycle bin' => [
            'label' => 'Recycle bin',
            'description' => 'Deleted records, one tab per kind.',
            'groups' => [
                'Page' => [
                    'view recycle bin' => ['Open the recycle bin', 'See the Recycle Bin page.', null],
                    'empty recycle bin' => ['Empty a tab', 'The Empty button. Also needs that tab\'s delete-forever permission.'],
                ],
                'Orders' => [
                    'view deleted orders' => ['See deleted orders', 'The Orders tab.'],
                    'restore deleted orders' => ['Restore orders', 'Put deleted orders back.', 'view deleted orders'],
                    'purge deleted orders' => ['Delete orders forever', 'Permanently delete orders.', 'view deleted orders'],
                ],
                'Clients' => [
                    'view deleted clients' => ['See deleted clients', 'The Clients tab.'],
                    'restore deleted clients' => ['Restore clients', 'Put deleted clients back.', 'view deleted clients'],
                    'purge deleted clients' => ['Delete clients forever', 'Permanently delete clients.', 'view deleted clients'],
                ],
                'Inventory' => [
                    'view deleted inventory' => ['See deleted catalogue products', 'Catalogue products on the Inventory tab.'],
                    'restore deleted inventory' => ['Restore catalogue products', 'Put deleted catalogue products back.', 'view deleted inventory'],
                    'purge deleted inventory' => ['Delete catalogue products forever', 'Permanently delete catalogue products.', 'view deleted inventory'],
                    'view deleted client products' => ['See deleted client products', 'Clients\' own products on the Inventory tab.'],
                    'restore deleted client products' => ['Restore client products', 'Put deleted client products back.', 'view deleted client products'],
                    'purge deleted client products' => ['Delete client products forever', 'Permanently delete client products.', 'view deleted client products'],
                ],
                'Users' => [
                    'view deleted users' => ['See deleted users', 'The Users tab.'],
                    'restore deleted users' => ['Restore users', 'Put deleted users back.', 'view deleted users'],
                    'purge deleted users' => ['Delete users forever', 'Permanently delete users.', 'view deleted users'],
                ],
                'Riders' => [
                    'view deleted riders' => ['See deleted riders', 'The Riders tab.'],
                    'restore deleted riders' => ['Restore riders', 'Put deleted riders back.', 'view deleted riders'],
                    'purge deleted riders' => ['Delete riders forever', 'Permanently delete riders.', 'view deleted riders'],
                ],
            ],
        ],

        'users' => [
            'label' => 'Users',
            'description' => 'Team members and what they can reach.',
            'groups' => [
                'Page' => [
                    'view users' => ['Open the users page', 'See the Users page and the team list.', null],
                    'view client accounts' => ['Clients tab', 'The client sign-in accounts.'],
                ],
                'Actions' => [
                    'create users' => ['Add user', 'The Add User button.'],
                    'edit users' => ['Edit user', 'Open a user to change them.'],
                    'assign user roles' => ['Assign roles', 'Choose a user\'s roles.', 'edit users'],
                    'assign client access' => ['Assign clients', 'Choose which clients a user handles.', 'edit users'],
                    'delete users' => ['Delete user', 'Move users to the recycle bin.'],
                ],
            ],
        ],

        'roles' => [
            'label' => 'Roles',
            'description' => 'Roles and the permissions they carry.',
            'groups' => [
                'Page' => [
                    'view roles' => ['Open the roles page', 'See the Roles page.', null],
                    'view role permissions' => ['See a role\'s permissions', 'The View Permissions dialog.'],
                    'view permissions' => ['Open the permissions page', 'The list of every permission.', null],
                ],
                'Actions' => [
                    'create roles' => ['Add role', 'The Add Role button.'],
                    'edit roles' => ['Edit role', 'Rename a role and change its permissions.'],
                    'delete roles' => ['Delete role', 'Remove a role.'],
                ],
            ],
        ],

        'email' => [
            'label' => 'Email system',
            'description' => 'The mail server the portal sends from.',
            'groups' => [
                'Page' => [
                    'view email settings' => ['Open email settings', 'See the mail server settings.', null],
                    'view email logs' => ['Email logs', 'The recent emails list.'],
                    'view email statistics' => ['Email statistics', 'Sent and failed counts.'],
                ],
                'Actions' => [
                    'edit email settings' => ['Save email settings', 'Change the mail server and switch email on or off.'],
                    'send test email' => ['Send test email', 'The Test Connection button.'],
                    'restore graveyard emails' => ['Restore dead address', 'Allow mail to an address again.'],
                ],
            ],
        ],

        'notifications' => [
            'label' => 'Notifications',
            'description' => 'The bell and the notifications page.',
            'groups' => [
                'Page' => [
                    'view notifications' => ['See notifications', 'The bell and the Notifications page.', null],
                ],
                'Actions' => [
                    'mark notifications read' => ['Mark as read', 'Mark one or all notifications read.'],
                    'delete notifications' => ['Delete notifications', 'Remove a notification.'],
                ],
            ],
        ],

        'settings' => [
            'label' => 'My account',
            'description' => 'A person\'s own profile and sign-in.',
            'groups' => [
                'Page' => [
                    'view settings' => ['Open settings', 'See the Settings pages.', null],
                ],
                'Actions' => [
                    'edit profile' => ['Edit own profile', 'Change own name and email.'],
                    'change avatar' => ['Change own photo', 'Upload or remove own photo.'],
                    'change password' => ['Change own password', 'Set a new password.'],
                    'manage two-factor' => ['Two-factor sign-in', 'Switch two-factor on or off and see recovery codes.'],
                    'edit notification preferences' => ['Toast preferences', 'Where pop-up messages appear.'],
                    'edit email preferences' => ['Email preferences', 'Which emails to receive.'],
                ],
            ],
        ],

        'other' => [
            'label' => 'Other',
            'description' => 'Things that sit outside one page.',
            'groups' => [
                'Page' => [
                    'view stock alerts' => ['Stock warning cards', 'The low and out of stock cards.', null],
                    'view help center' => ['Help center', 'Open the Help Center page.', null],
                ],
            ],
        ],
    ];

    /**
     * How roles from before the catalog keep what they could do: new
     * permission => the old permissions whose holders get it. Each entry is
     * one way to qualify, and a role qualifies by holding every permission in
     * any one of them. '*' is every staff role.
     */
    private const GRANTS = [
        'view dashboard order stats' => [['view dashboard', 'view orders']],
        'view dashboard revenue' => [['view dashboard', 'view orders']],
        'view dashboard tag cards' => [['view dashboard', 'view orders']],
        'view dashboard shipment status' => [['view dashboard', 'view orders']],
        'view dashboard whatsapp stats' => [['view dashboard', 'view whatsapp']],
        'view dashboard client stats' => [['view dashboard', 'view client']],

        'view client stats' => [['view client']],
        'view client details' => [['view client']],
        'view client revenue' => [['view client']],
        'export clients' => [['view client']],
        'edit client charges' => [['edit client']],
        'edit client portal features' => [['edit client']],
        'edit client notes' => [['edit client']],
        'change client status' => [['edit client']],
        'change client password' => [['edit client']],
        'send client reset link' => [['edit client']],

        'view client products' => [['view client']],
        'create client products' => [['edit client']],
        'edit client products' => [['edit client']],
        'review client products' => [['edit client']],
        'delete client product images' => [['edit client']],
        'delete client products' => [['delete client']],

        'view client payments' => [['view client']],
        'view client payment proof' => [['view client']],
        'record client payments' => [['edit client']],
        'delete client payments' => [['edit client']],

        'view order stats' => [['view orders']],
        'view order tag cards' => [['view orders']],
        'view order shipment status cards' => [['view orders']],
        'view order details' => [['view orders']],
        'view order whatsapp messages' => [['view whatsapp']],
        'tag orders' => [['edit orders']],
        'update order fulfillment status' => [['edit orders']],
        'update order payment status' => [['edit orders']],
        'update order call status' => [['edit orders']],
        'cancel orders' => [['edit orders']],
        'export orders' => [['view orders']],

        'view shipments' => [['view orders']],
        'view delivery proof photos' => [['view orders']],
        'refresh shipment tracking' => [['view orders']],
        'create shipments' => [['edit orders']],
        'edit shipments' => [['edit orders']],
        'cancel shipments' => [['edit orders']],
        'escalate shipments' => [['edit orders']],
        'mark shipments returned' => [['edit orders']],
        'receive shipments at hub' => [['edit orders']],
        'unassign shipment rider' => [['edit orders']],

        'view waybills' => [['view orders']],
        'download waybills' => [['view orders']],
        'generate waybills' => [['edit orders']],
        'delete waybills' => [['edit orders']],

        'view shipment analytics' => [['view orders']],
        'view cod remittances' => [['view orders']],
        'check courier api health' => [['edit apps']],

        'view whatsapp stats' => [['view whatsapp']],
        'view whatsapp conversations' => [['view whatsapp']],
        'toggle whatsapp messaging' => [['view whatsapp', 'edit apps']],

        'view riders' => [['manage riders']],
        'view rider stats' => [['view riders'], ['manage riders']],
        'view rider cash stats' => [['view riders'], ['manage riders']],
        'view rider performance' => [['view riders'], ['manage riders']],
        'view rider parcels' => [['view riders'], ['manage riders']],
        'view rider payments' => [['view riders'], ['manage riders'], ['manage rider payments']],
        'create riders' => [['manage riders']],
        'edit riders' => [['manage riders']],
        'change rider photo' => [['manage riders']],
        'send rider app link' => [['manage riders']],
        'set rider pin' => [['manage riders']],
        'sign rider out' => [['manage riders']],
        'suspend riders' => [['manage riders']],
        'delete riders' => [['manage riders']],
        'edit rider support contact' => [['manage riders']],
        'record rider payments' => [['manage rider payments']],
        'void rider payments' => [['manage rider payments']],

        'view inventory stats' => [['view inventory']],
        'view product details' => [['view inventory']],
        'view stock scans' => [['view inventory']],
        'export inventory' => [['view inventory']],
        'change product status' => [['edit inventory']],
        'publish products' => [['edit inventory']],
        'import inventory' => [['edit inventory']],

        'toggle connectors' => [['edit apps']],
        'configure jnt connector' => [['edit apps']],
        'configure imile connector' => [['edit apps']],
        'configure logestechs connector' => [['edit apps']],
        'configure whatsapp connector' => [['edit apps']],
        'reveal connector secrets' => [['edit apps']],
        'test connector connection' => [['edit apps']],
        'view warehouses' => [['edit apps']],
        'create warehouses' => [['edit apps']],
        'edit warehouses' => [['edit apps']],
        'delete warehouses' => [['edit apps']],

        'empty recycle bin' => [['purge recycle bin']],
        'view deleted orders' => [['view recycle bin', 'delete orders']],
        'restore deleted orders' => [['restore recycle bin', 'delete orders']],
        'purge deleted orders' => [['purge recycle bin', 'delete orders']],
        'view deleted clients' => [['view recycle bin', 'delete client']],
        'restore deleted clients' => [['restore recycle bin', 'delete client']],
        'purge deleted clients' => [['purge recycle bin', 'delete client']],
        'view deleted inventory' => [['view recycle bin', 'delete inventory']],
        'restore deleted inventory' => [['restore recycle bin', 'delete inventory']],
        'purge deleted inventory' => [['purge recycle bin', 'delete inventory']],
        'view deleted client products' => [['view recycle bin', 'delete client']],
        'restore deleted client products' => [['restore recycle bin', 'delete client']],
        'purge deleted client products' => [['purge recycle bin', 'delete client']],
        'view deleted users' => [['view recycle bin', 'delete users']],
        'restore deleted users' => [['restore recycle bin', 'delete users']],
        'purge deleted users' => [['purge recycle bin', 'delete users']],
        'view deleted riders' => [['view recycle bin', 'manage riders']],
        'restore deleted riders' => [['restore recycle bin', 'manage riders']],
        'purge deleted riders' => [['purge recycle bin', 'manage riders']],

        'view client accounts' => [['view users']],
        // Creating a user always let its roles be picked.
        'assign user roles' => [['edit users'], ['create users']],
        'assign client access' => [['edit users']],

        'view role permissions' => [['view permissions']],

        'view email settings' => [['manage-email-settings']],
        'view email logs' => [['manage-email-settings']],
        'view email statistics' => [['manage-email-settings']],
        'edit email settings' => [['manage-email-settings']],
        'send test email' => [['manage-email-settings']],
        'restore graveyard emails' => [['manage-email-settings']],

        'view notifications' => [['*']],
        'mark notifications read' => [['*']],

        'view settings' => [['*']],
        'edit profile' => [['*']],
        'change avatar' => [['*']],
        'change password' => [['*']],
        'manage two-factor' => [['*']],
        'edit notification preferences' => [['*']],
        'edit email preferences' => [['*']],

        'view stock alerts' => [['view orders'], ['view inventory'], ['view client']],
        'view help center' => [['*']],
    ];

    /** Permissions from before the catalog that no longer gate anything. */
    private const RETIRED = [
        'create dashboard', 'edit dashboard', 'delete dashboard',
        'create orders', 'create inventory',
        'edit apps', 'create apps', 'delete apps',
        'edit settings',
        'create permissions', 'edit permissions', 'delete permissions',
        'manage riders', 'manage rider payments',
        'manage-email-settings',
        'restore recycle bin', 'purge recycle bin',
    ];

    /** Connector key => the permission that opens and saves its settings. */
    private const CONNECTOR_PERMISSIONS = [
        'jnt_express' => 'configure jnt connector',
        'imile' => 'configure imile connector',
        'logestechs' => 'configure logestechs connector',
        'whatsapp' => 'configure whatsapp connector',
    ];

    /** Every permission name, in catalog order. */
    public static function names(): array
    {
        return array_column(self::flat(), 'name');
    }

    public static function has(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /** @return list<string> */
    public static function retired(): array
    {
        return self::RETIRED;
    }

    /** @return array<string, list<list<string>>> */
    public static function grants(): array
    {
        return self::GRANTS;
    }

    /** The permission for a connector's settings, or null when it has none of its own. */
    public static function connectorPermission(string $connectorKey): ?string
    {
        return self::CONNECTOR_PERMISSIONS[$connectorKey] ?? null;
    }

    /** @return list<string> */
    public static function connectorPermissions(): array
    {
        return array_values(self::CONNECTOR_PERMISSIONS);
    }

    /**
     * The catalog as the role editor draws it.
     *
     * @return list<array{key: string, label: string, description: string, groups: list<array{label: string, permissions: list<array{name: string, label: string, description: string, requires: ?string}>}>}>
     */
    public static function modules(): array
    {
        $modules = [];

        foreach (self::MODULES as $key => $module) {
            $first = array_key_first(reset($module['groups']));
            $groups = [];

            foreach ($module['groups'] as $groupLabel => $permissions) {
                $rows = [];

                foreach ($permissions as $name => $definition) {
                    $requires = array_key_exists(2, $definition) ? $definition[2] : $first;

                    $rows[] = [
                        'name' => $name,
                        'label' => $definition[0],
                        'description' => $definition[1],
                        'requires' => $requires === $name ? null : $requires,
                    ];
                }

                $groups[] = ['label' => $groupLabel, 'permissions' => $rows];
            }

            $modules[] = [
                'key' => $key,
                'label' => $module['label'],
                'description' => $module['description'],
                'groups' => $groups,
            ];
        }

        return $modules;
    }

    /**
     * `$names` plus everything they need to be of any use: a button's page,
     * and that page's own page.
     *
     * @param  iterable<string>  $names
     * @return list<string>
     */
    public static function withRequired(iterable $names): array
    {
        $requires = array_column(self::flat(), 'requires', 'name');
        $all = [];

        foreach ($names as $name) {
            for ($current = $name; $current !== null && ! isset($all[$current]); $current = $requires[$current] ?? null) {
                $all[$current] = true;
            }
        }

        return array_values(array_intersect(self::names(), array_keys($all)));
    }

    /**
     * Bring the database in step with the catalog: every permission and its
     * category exists, and the full-access roles hold all of them. Safe to run
     * any number of times; it never takes a permission away from a role.
     */
    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // In bulk, not one model at a time: every save of a permission makes
        // the package reload them all, which adds up over a list this long.
        $existing = Permission::where('guard_name', 'web')->pluck('name')->all();
        $now = now();

        foreach (array_values(self::modules()) as $order => $module) {
            $category = PermissionCategory::updateOrCreate(
                ['name' => $module['key']],
                ['label' => $module['label'], 'description' => $module['description'], 'order' => $order + 1],
            );

            $names = array_column(array_merge(...array_column($module['groups'], 'permissions')), 'name');

            Permission::insert(array_map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'category_id' => $category->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values(array_diff($names, $existing))));

            Permission::where('guard_name', 'web')->whereIn('name', $names)
                ->where(fn ($query) => $query->whereNull('category_id')->orWhere('category_id', '!=', $category->id))
                ->update(['category_id' => $category->id]);
        }

        $registrar->forgetCachedPermissions();

        $ids = Permission::where('guard_name', 'web')->whereIn('name', self::names())->pluck('id');

        foreach (User::FULL_ACCESS_ROLES as $name) {
            $role = Role::findOrCreate($name, 'web');

            // Only ever adds: whatever else the role holds is left alone.
            $role->permissions()->attach($ids->diff($role->permissions()->pluck('permissions.id'))->all());
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Give every role from before the catalog the new permissions carved out
     * of the ones it holds, then drop the retired ones. Run once, by the
     * migration, after sync().
     */
    public static function migrateLegacyRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Roles, and the few users holding permissions of their own.
        $holders = Role::with('permissions')
            ->whereNotIn('name', [...User::FULL_ACCESS_ROLES, 'client'])
            ->get()
            ->concat(User::withTrashed()->has('permissions')->with('permissions')->get());

        foreach ($holders as $holder) {
            $held = $holder->permissions->pluck('name')->all();

            // '*' is for staff roles: a user's own permissions say nothing
            // about which kind of account it is.
            $new = array_diff(self::expandLegacy($held, staffRole: $holder instanceof Role), $held);

            if ($new !== []) {
                $holder->givePermissionTo(array_values($new));
            }
        }

        Permission::whereIn('name', self::RETIRED)->get()->each->delete();
        PermissionCategory::whereDoesntHave('permissions')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * What a holder of the old, bundled permissions `$held` holds in the
     * catalog: everything it had that still exists, plus each new permission
     * carved out of a bundle it held.
     *
     * Works from `$held` alone, so one grant never qualifies for another.
     *
     * @param  list<string>  $held
     * @return list<string>
     */
    public static function expandLegacy(array $held, bool $staffRole = true): array
    {
        $result = array_diff($held, self::RETIRED);

        foreach (self::GRANTS as $permission => $ways) {
            foreach ($ways as $sources) {
                if ($sources === ['*'] ? $staffRole : array_diff($sources, $held) === []) {
                    $result[] = $permission;
                    break;
                }
            }
        }

        return array_values(array_unique($result));
    }

    /** @return list<array{name: string, label: string, description: string, requires: ?string}> */
    private static function flat(): array
    {
        $flat = [];

        foreach (self::modules() as $module) {
            foreach ($module['groups'] as $group) {
                array_push($flat, ...$group['permissions']);
            }
        }

        return $flat;
    }
}
