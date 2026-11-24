<?php

namespace App\Logging\Events;

use App\Logging\Concerns\IsLogEvent;
use App\Logging\Contracts\LogEvent;
use App\Logging\Enums\Ctx;
use App\Logging\Enums\Retention;
use App\Logging\EventSpec;

/**
 * Shopify events, IDs 40001-40999: OAuth and install, webhooks, GDPR topics,
 * order sync, the fulfillment service and the embedded app. `shd` is the shop
 * domain, `con` the client_shopify_connections row when we have one.
 * Ranges: 001-199 lifecycle/info, 200-399 inbound, 400-599 outbound,
 * 600-799 validation/refusals, 800-999 failures.
 */
enum ShopifyEvent: int implements LogEvent
{
    use IsLogEvent;

    /** IDs that were removed and must never be reused. */
    public const RETIRED = [];

    // Lifecycle (history)
    case INSTALL_COMPLETED = 40001;
    case GRANT_REFRESHED = 40002;
    case CONNECTION_CLAIMED = 40003;
    case CONNECTION_UNLINKED_FOR_NEW_CLAIM = 40004;
    case STORE_REASSIGNED = 40005;
    case STORE_RELINKED = 40006;
    case INSTALL_UNLINKED_STORE = 40007;
    case UNINSTALLED = 40008;
    case CONNECTION_CLOSED_UNINSTALLED = 40009;
    case GDPR_DATA_REQUESTED = 40010;
    case GDPR_CUSTOMER_REDACTED = 40011;
    case GDPR_SHOP_REDACTED = 40012;
    case ORDER_SYNCED = 40013;
    case ORDER_RECOVERED_BY_RECONCILIATION = 40014;
    case FULFILLMENT_REQUESTED = 40015;
    case FULFILLMENT_CREATED = 40016;
    case FULFILLMENT_SERVICE_READY = 40017;
    case FULFILLMENT_MUTATION_SUCCEEDED = 40018;
    case WEBHOOKS_RESTORED = 40019;
    case FULFILLMENT_REQUEST_WITHHELD_UNPAID = 40020;

    // Inbound
    case FULFILLMENT_NOTIFICATION_RECEIVED = 40201;
    case FETCH_STOCK_SERVED = 40202;
    case FETCH_STOCK_IGNORED = 40203;
    case WEBHOOK_IGNORED_NOT_CONNECTED = 40204;
    case FULFILLMENT_REQUESTS_SWEPT = 40205;
    case SYNC_RETRY_SWEEP = 40206;

    // Refusals
    case OAUTH_CALLBACK_REJECTED = 40601;
    case WEBHOOK_REJECTED_HMAC = 40602;
    case WEBHOOK_REJECTED_SHOP_MISMATCH = 40603;
    case FULFILLMENT_NOTIFICATION_REJECTED_HMAC = 40604;
    case FETCH_STOCK_REJECTED_HMAC = 40605;
    case FULFILLMENT_REQUEST_NOTHING_OPEN = 40606;
    case INSTALL_CHECK_INCONCLUSIVE = 40607;

    // Failures
    case FULFILLMENT_SERVICE_REGISTER_FAILED = 40801;
    case FULFILLMENT_SERVICE_CREATE_REJECTED = 40802;
    case LOCATION_EDIT_REJECTED = 40803;
    case LOCATION_EDIT_FAILED = 40804;
    case FULFILLMENT_REQUEST_REJECTED = 40805;
    case FULFILLMENT_REQUEST_FAILED = 40806;
    case ASSIGNED_FULFILLMENT_ORDERS_FAILED = 40807;
    case FULFILLMENT_MUTATION_REJECTED = 40808;
    case FULFILLMENT_MUTATION_FAILED = 40809;
    case FULFILLMENT_CREATE_REJECTED = 40810;
    case FULFILLMENT_CREATE_FAILED = 40811;
    case TOKEN_EXCHANGE_FAILED = 40812;
    case WEBHOOK_REGISTRATION_FAILED = 40813;
    case WEBHOOK_REGISTRATION_USER_ERROR = 40814;
    case GRANT_REFRESH_FAILED = 40815;
    case INSTALL_CHECK_UNREACHABLE = 40816;
    case WEBHOOK_SYNC_PARKED = 40817;
    case RECONCILIATION_FETCH_FAILED = 40818;
    case STALE_WEBHOOK_SUBSCRIPTIONS = 40819;
    case MERCHANT_EMAIL_FAILED = 40820;
    case CONNECTION_TEST_FAILED = 40821;

    public function spec(): EventSpec
    {
        $history = Retention::HISTORY;

        return match ($this) {
            self::INSTALL_COMPLETED => EventSpec::info('Shopify app installed; access token stored via token exchange', required: [Ctx::SHOP_CONNECTION], optional: [Ctx::CLIENT], keep: $history),
            self::GRANT_REFRESHED => EventSpec::info('Shopify grant refreshed after a scope change', required: [Ctx::SHOP_CONNECTION], keep: $history),
            self::CONNECTION_CLAIMED => EventSpec::info('Shopify store connected to a client', required: [Ctx::SHOP_DOMAIN, Ctx::CLIENT], keep: $history),
            self::CONNECTION_UNLINKED_FOR_NEW_CLAIM => EventSpec::warning('Client\'s previous Shopify store unlinked because the client connected another store', required: [Ctx::SHOP_DOMAIN, Ctx::CLIENT], keep: $history),
            self::STORE_REASSIGNED => EventSpec::warning('Shopify store moved from one client to another', required: [Ctx::SHOP_DOMAIN, Ctx::CLIENT, Ctx::PREVIOUS_CLIENT], keep: $history),
            self::STORE_RELINKED => EventSpec::info('Shopify store relinked via a re-grant from the Shopify admin', required: [Ctx::SHOP_DOMAIN], optional: [Ctx::CLIENT], keep: $history),
            self::INSTALL_UNLINKED_STORE => EventSpec::info('Shopify install completed for a store no client has claimed yet; token stored', required: [Ctx::SHOP_DOMAIN], keep: $history),
            self::UNINSTALLED => EventSpec::info('Shopify app/uninstalled processed (count = connections closed)', required: [Ctx::SHOP_DOMAIN, Ctx::COUNT], keep: $history),
            self::CONNECTION_CLOSED_UNINSTALLED => EventSpec::info('Shopify connection closed because the store had uninstalled the app', required: [Ctx::SHOP_CONNECTION, Ctx::SHOP_DOMAIN], optional: [Ctx::CLIENT], keep: $history),
            self::GDPR_DATA_REQUESTED => EventSpec::info('Shopify GDPR customers/data_request received (count = orders requested)', required: [Ctx::SHOP_DOMAIN, Ctx::COUNT], optional: [Ctx::EXTERNAL_CUSTOMER], keep: $history),
            self::GDPR_CUSTOMER_REDACTED => EventSpec::info('Shopify GDPR customers/redact processed (count = orders redacted)', required: [Ctx::SHOP_DOMAIN, Ctx::COUNT], keep: $history),
            self::GDPR_SHOP_REDACTED => EventSpec::info('Shopify GDPR shop/redact processed (count = orders redacted)', required: [Ctx::SHOP_DOMAIN, Ctx::COUNT], keep: $history),
            self::ORDER_SYNCED => EventSpec::info('Order created or updated from a Shopify webhook', required: [Ctx::SHOP_CONNECTION, Ctx::ORDER], optional: [Ctx::WEBHOOK_TOPIC, Ctx::VENDOR_CODE], keep: $history),
            self::ORDER_RECOVERED_BY_RECONCILIATION => EventSpec::warning('Order missed by webhooks was recovered by the reconciliation poll', required: [Ctx::SHOP_DOMAIN, Ctx::EXTERNAL_ORDER_ID], keep: $history),
            self::FULFILLMENT_REQUESTED => EventSpec::info('Fulfillment requested from Shopify for the order', required: [Ctx::SHOP_CONNECTION, Ctx::ORDER], optional: [Ctx::FULFILLMENT_ORDER], keep: $history),
            self::FULFILLMENT_CREATED => EventSpec::info('Shopify fulfillment created with our tracking number', required: [Ctx::SHOP_CONNECTION, Ctx::ORDER, Ctx::SHIPMENT], optional: [Ctx::TRACKING_NO], keep: $history),
            self::FULFILLMENT_SERVICE_READY => EventSpec::info('KSA Drop fulfillment service registered (or adopted) on the store', required: [Ctx::SHOP_DOMAIN], keep: $history),
            self::FULFILLMENT_MUTATION_SUCCEEDED => EventSpec::info('Shopify fulfillment mutation succeeded (enp = mutation name)', required: [Ctx::SHOP_CONNECTION, Ctx::ENDPOINT], optional: [Ctx::FULFILLMENT_ORDER, Ctx::SHIPMENT, Ctx::VENDOR_CODE], keep: $history),
            self::WEBHOOKS_RESTORED => EventSpec::info('Shopify webhook subscriptions re-registered', required: [Ctx::SHOP_DOMAIN], keep: $history),
            self::FULFILLMENT_REQUEST_WITHHELD_UNPAID => EventSpec::info('Fulfillment request withheld: the order is not paid (vcd = financial status)', required: [Ctx::ORDER], optional: [Ctx::EXTERNAL_ORDER_ID, Ctx::VENDOR_CODE]),

            self::FULFILLMENT_NOTIFICATION_RECEIVED => EventSpec::info('Shopify fulfillment order notification received (vcd = kind)', optional: [Ctx::SHOP_DOMAIN, Ctx::VENDOR_CODE]),
            self::FETCH_STOCK_SERVED => EventSpec::info('Shopify fetch_stock served (count = SKUs sent)', required: [Ctx::COUNT], optional: [Ctx::SHOP_DOMAIN]),
            self::FETCH_STOCK_IGNORED => EventSpec::info('Shopify fetch_stock ignored: not a connected KSA Drop store', optional: [Ctx::SHOP_DOMAIN]),
            self::WEBHOOK_IGNORED_NOT_CONNECTED => EventSpec::info('Shopify webhook ignored: store not connected to a client yet', required: [Ctx::WEBHOOK_TOPIC], optional: [Ctx::SHOP_DOMAIN]),
            self::FULFILLMENT_REQUESTS_SWEPT => EventSpec::info('Outstanding Shopify fulfillment requests swept', required: [Ctx::COUNT], optional: [Ctx::SHOP_DOMAIN]),
            self::SYNC_RETRY_SWEEP => EventSpec::info('Shopify sync failure retry sweep (count = replayed, failed = abandoned)', required: [Ctx::COUNT, Ctx::FAILED_COUNT]),

            self::OAUTH_CALLBACK_REJECTED => EventSpec::warning('Shopify OAuth callback rejected; connection refused', required: [Ctx::REASON], optional: [Ctx::SHOP_DOMAIN]),
            self::WEBHOOK_REJECTED_HMAC => EventSpec::warning('Shopify webhook rejected: HMAC signature invalid', optional: [Ctx::SHOP_DOMAIN, Ctx::WEBHOOK_TOPIC]),
            self::WEBHOOK_REJECTED_SHOP_MISMATCH => EventSpec::warning('Shopify webhook rejected: header shop and body shop differ', optional: [Ctx::SHOP_DOMAIN, Ctx::WEBHOOK_TOPIC]),
            self::FULFILLMENT_NOTIFICATION_REJECTED_HMAC => EventSpec::warning('Shopify fulfillment notification rejected: HMAC signature invalid', optional: [Ctx::SHOP_DOMAIN]),
            self::FETCH_STOCK_REJECTED_HMAC => EventSpec::warning('Shopify fetch_stock rejected: HMAC signature invalid', optional: [Ctx::SHOP_DOMAIN]),
            self::FULFILLMENT_REQUEST_NOTHING_OPEN => EventSpec::warning('Fulfillment request found no open fulfillment order at the KSA Drop location (check the variants use the KSA Drop fulfillment service)', required: [Ctx::SHOP_CONNECTION, Ctx::ORDER], optional: [Ctx::EXTERNAL_ORDER_ID]),
            self::INSTALL_CHECK_INCONCLUSIVE => EventSpec::warning('Shopify install check inconclusive', required: [Ctx::SHOP_DOMAIN], optional: [Ctx::HTTP_STATUS]),

            self::FULFILLMENT_SERVICE_REGISTER_FAILED => EventSpec::error('Shopify fulfillment service registration failed', required: [Ctx::SHOP_DOMAIN]),
            self::FULFILLMENT_SERVICE_CREATE_REJECTED => EventSpec::error('Shopify rejected fulfillmentServiceCreate; userErrors in the payloads log', required: [Ctx::SHOP_DOMAIN]),
            self::LOCATION_EDIT_REJECTED => EventSpec::warning('Shopify rejected locationEdit; userErrors in the payloads log', required: [Ctx::SHOP_DOMAIN]),
            self::LOCATION_EDIT_FAILED => EventSpec::warning('Shopify locationEdit failed', required: [Ctx::SHOP_DOMAIN]),
            self::FULFILLMENT_REQUEST_REJECTED => EventSpec::error('Shopify rejected fulfillmentOrderSubmitFulfillmentRequest; userErrors in the payloads log', required: [Ctx::SHOP_CONNECTION, Ctx::ORDER], optional: [Ctx::FULFILLMENT_ORDER]),
            self::FULFILLMENT_REQUEST_FAILED => EventSpec::error('Fulfillment request to Shopify failed', required: [Ctx::ORDER]),
            self::ASSIGNED_FULFILLMENT_ORDERS_FAILED => EventSpec::error('Listing assigned fulfillment orders failed (vcd = assignment status)', required: [Ctx::SHOP_CONNECTION], optional: [Ctx::VENDOR_CODE]),
            self::FULFILLMENT_MUTATION_REJECTED => EventSpec::error('Shopify rejected a fulfillment mutation (enp = mutation name); userErrors in the payloads log', required: [Ctx::SHOP_CONNECTION, Ctx::ENDPOINT], optional: [Ctx::FULFILLMENT_ORDER, Ctx::SHIPMENT, Ctx::VENDOR_CODE]),
            self::FULFILLMENT_MUTATION_FAILED => EventSpec::error('Shopify fulfillment mutation failed (enp = mutation name)', required: [Ctx::SHOP_CONNECTION, Ctx::ENDPOINT], optional: [Ctx::FULFILLMENT_ORDER, Ctx::SHIPMENT, Ctx::VENDOR_CODE]),
            self::FULFILLMENT_CREATE_REJECTED => EventSpec::error('Shopify rejected fulfillmentCreate; userErrors in the payloads log', required: [Ctx::SHOP_CONNECTION, Ctx::SHIPMENT]),
            self::FULFILLMENT_CREATE_FAILED => EventSpec::error('Shopify fulfillmentCreate failed', required: [Ctx::SHOP_CONNECTION, Ctx::SHIPMENT]),
            self::TOKEN_EXCHANGE_FAILED => EventSpec::error('Shopify token exchange failed', optional: [Ctx::SHOP_DOMAIN]),
            self::WEBHOOK_REGISTRATION_FAILED => EventSpec::warning('Shopify webhook registration failed', optional: [Ctx::SHOP_DOMAIN, Ctx::WEBHOOK_TOPIC]),
            self::WEBHOOK_REGISTRATION_USER_ERROR => EventSpec::warning('Shopify refused a webhook subscription; userErrors in the payloads log', required: [Ctx::SHOP_DOMAIN, Ctx::WEBHOOK_TOPIC]),
            self::GRANT_REFRESH_FAILED => EventSpec::warning('Shopify grant refresh failed; the existing token is kept', required: [Ctx::SHOP_DOMAIN]),
            self::INSTALL_CHECK_UNREACHABLE => EventSpec::warning('Shopify install check could not reach Shopify', required: [Ctx::SHOP_DOMAIN]),
            self::WEBHOOK_SYNC_PARKED => EventSpec::error('Shopify webhook sync failed; parked in shopify_sync_failures for retry', required: [Ctx::WEBHOOK_TOPIC], optional: [Ctx::SHOP_DOMAIN]),
            self::RECONCILIATION_FETCH_FAILED => EventSpec::error('Shopify reconciliation could not fetch orders', required: [Ctx::SHOP_DOMAIN]),
            self::STALE_WEBHOOK_SUBSCRIPTIONS => EventSpec::error('Shopify webhook subscriptions point at an old URL (count = stale subscriptions)', required: [Ctx::SHOP_DOMAIN, Ctx::COUNT]),
            self::CONNECTION_TEST_FAILED => EventSpec::warning('Shopify connection test from Connector settings failed', required: [Ctx::SHOP_CONNECTION]),
            self::MERCHANT_EMAIL_FAILED => EventSpec::error('Email to the merchant about their Shopify store failed', required: [Ctx::CLIENT], optional: [Ctx::SHOP_DOMAIN]),
        };
    }
}
