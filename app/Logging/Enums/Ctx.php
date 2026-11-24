<?php

namespace App\Logging\Enums;

use App\Services\Shipping\Enums\ShipmentStatus;

/**
 * The only keys an event may carry in its context, as the short keys written
 * to the log. Adding a key means adding a case here, with its type and a
 * description. Keys are append-only; never rename one.
 */
enum Ctx: string
{
    // Row IDs (positive integer primary keys). Never names, phones or emails.
    case ORDER = 'ord';
    case SHIPMENT = 'shp';
    case CLIENT = 'cli';
    case TARGET_USER = 'usr';
    case RIDER = 'rdr';
    case SHOP_CONNECTION = 'con';
    case PRODUCT = 'prd';
    case WHATSAPP_MESSAGE = 'wam';
    case EMAIL_LOG = 'eml';
    case SYNC_FAILURE = 'syf';
    case PREVIOUS_CLIENT = 'pcl';
    case GRAVEYARD_ENTRY = 'egv';
    case EMAIL_SETTING = 'est';
    case CONNECTOR = 'cnr';
    case RECORD = 'rid';
    case ROLE = 'rol';
    case PERMISSION = 'prm';

    // Enums
    case REASON = 'rsn';
    case SHIPMENT_STATUS = 'sts';
    case COURIER = 'crr';
    case MODEL_TYPE = 'mdl';

    // Vendor identifiers (validated, never prose)
    case VENDOR_CODE = 'vcd';
    case TRACKING_NO = 'trk';
    case EXTERNAL_ORDER_ID = 'xid';
    case WEBHOOK_TOPIC = 'tpc';
    case SHOP_DOMAIN = 'shd';
    case EXCEPTION_FINGERPRINT = 'exf';
    case ENDPOINT = 'enp';
    case VENDOR_BATCH = 'bat';
    case EXTERNAL_ACCOUNT = 'acc';
    case EXTERNAL_CUSTOMER = 'xcu';
    case FULFILLMENT_ORDER = 'xfo';
    case SOURCE = 'src';
    case MAIL_TYPE = 'mtp';
    case SETTING_KEY = 'stk';
    case FIELDS = 'fld';

    // Numbers
    case COUNT = 'cnt';
    case DURATION_MS = 'dur';
    case AMOUNT_HALALAS = 'amt';
    case ATTEMPT = 'att';
    case HTTP_STATUS = 'hst';
    case SUBJECT_EVENT = 'sev';
    case FAILED_COUNT = 'fct';

    // Flags
    case ENABLED = 'on';
    case SUCCEEDED = 'ok';

    public function type(): CtxType
    {
        return match ($this) {
            self::ORDER, self::SHIPMENT, self::CLIENT, self::TARGET_USER, self::RIDER,
            self::SHOP_CONNECTION, self::PRODUCT, self::WHATSAPP_MESSAGE, self::EMAIL_LOG,
            self::SYNC_FAILURE, self::PREVIOUS_CLIENT, self::GRAVEYARD_ENTRY, self::EMAIL_SETTING,
            self::CONNECTOR, self::RECORD, self::ROLE, self::PERMISSION => CtxType::INT_ID,
            self::REASON, self::SHIPMENT_STATUS, self::COURIER, self::MODEL_TYPE => CtxType::ENUM,
            self::VENDOR_CODE, self::TRACKING_NO, self::EXTERNAL_ORDER_ID, self::WEBHOOK_TOPIC,
            self::SHOP_DOMAIN, self::EXCEPTION_FINGERPRINT, self::ENDPOINT, self::VENDOR_BATCH,
            self::EXTERNAL_ACCOUNT, self::EXTERNAL_CUSTOMER, self::FULFILLMENT_ORDER, self::SOURCE,
            self::MAIL_TYPE, self::SETTING_KEY, self::FIELDS => CtxType::EXTERNAL_ID,
            self::COUNT, self::SUBJECT_EVENT, self::FAILED_COUNT => CtxType::COUNT,
            self::DURATION_MS => CtxType::DURATION_MS,
            self::AMOUNT_HALALAS => CtxType::AMOUNT,
            self::ATTEMPT => CtxType::ATTEMPT,
            self::HTTP_STATUS => CtxType::HTTP_STATUS,
            self::ENABLED, self::SUCCEEDED => CtxType::BOOL,
        };
    }

    /** @return class-string<\BackedEnum>|null */
    public function enumClass(): ?string
    {
        return match ($this) {
            self::REASON => Reason::class,
            self::SHIPMENT_STATUS => ShipmentStatus::class,
            self::COURIER => Courier::class,
            self::MODEL_TYPE => ModelType::class,
            default => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ORDER => 'orders.id',
            self::SHIPMENT => 'shipments.id',
            self::CLIENT => 'clients.id (merchant)',
            self::TARGET_USER => 'users.id of the person the event is about (not the actor)',
            self::RIDER => 'riders.id',
            self::SHOP_CONNECTION => 'client_shopify_connections.id',
            self::PRODUCT => 'products.id',
            self::WHATSAPP_MESSAGE => 'whatsapp_messages.id',
            self::EMAIL_LOG => 'email_logs.id',
            self::SYNC_FAILURE => 'shopify_sync_failures.id',
            self::PREVIOUS_CLIENT => 'clients.id the record belonged to before this change',
            self::GRAVEYARD_ENTRY => 'email_graveyard.id',
            self::EMAIL_SETTING => 'email_settings.id',
            self::CONNECTOR => 'connectors.id',
            self::RECORD => 'Primary key of the row named by `mdl`',
            self::ROLE => 'roles.id',
            self::PERMISSION => 'permissions.id',
            self::REASON => 'Why it failed or was refused (Reason enum)',
            self::SHIPMENT_STATUS => 'Shipment status (ShipmentStatus enum)',
            self::COURIER => 'Courier integration (Courier enum)',
            self::MODEL_TYPE => 'Which table a history record is about (ModelType enum)',
            self::VENDOR_CODE => 'Error or status code the vendor returned, verbatim',
            self::TRACKING_NO => 'Courier tracking / waybill number',
            self::EXTERNAL_ORDER_ID => 'Order identifier on the vendor side (e.g. Shopify order ID)',
            self::WEBHOOK_TOPIC => 'Webhook topic, e.g. orders/create',
            self::SHOP_DOMAIN => 'Shopify shop domain',
            self::EXCEPTION_FINGERPRINT => '12-hex fingerprint of an exception; full detail is in the exceptions log',
            self::ENDPOINT => 'Vendor API endpoint path we called',
            self::VENDOR_BATCH => 'Batch / settlement identifier on the vendor side',
            self::EXTERNAL_ACCOUNT => 'Account identifier the vendor sent or we used (never a secret)',
            self::EXTERNAL_CUSTOMER => 'Customer identifier on the vendor side (e.g. Shopify customer ID), never an email',
            self::FULFILLMENT_ORDER => 'Shopify fulfillment order GID',
            self::SOURCE => 'Where something came from, as a short code (e.g. smtp, bounce)',
            self::MAIL_TYPE => 'Mailable class basename or email type code',
            self::SETTING_KEY => 'Name of a setting (never its value), e.g. private_key',
            self::FIELDS => 'Names of the columns that changed, joined by "."; "#N" = N more not listed. Never values',
            self::COUNT => 'A count of items (the event description says of what)',
            self::FAILED_COUNT => 'How many items failed',
            self::ENABLED => 'Switched on (1) or off (0)',
            self::SUCCEEDED => 'Outcome: succeeded (1) or failed (0)',
            self::DURATION_MS => 'Duration in milliseconds',
            self::AMOUNT_HALALAS => 'Money amount in halalas (1/100 SAR)',
            self::ATTEMPT => 'Attempt number',
            self::HTTP_STATUS => 'HTTP status code',
            self::SUBJECT_EVENT => 'Event ID this diagnostic is about',
        };
    }
}
