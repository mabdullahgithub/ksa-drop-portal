<?php

namespace App\Logging\Enums;

use App\Logging\Events;

/**
 * The first two digits of every event ID. A domain names the part of the app
 * the event belongs to, the enum holding its events and the log channel (file
 * and retention) it is written to by default.
 */
enum LogDomain: int
{
    case SYSTEM = 10;
    case AUTH = 11;
    case ACCESS = 12;
    case CLIENT = 13;
    case ORDER = 20;
    case INVENTORY = 21;
    case FINANCE = 22;
    case SHIPPING = 30;
    case JNT = 31;
    case IMILE = 32;
    case LOGESTECHS = 33;
    case RIDER = 34;
    case SHOPIFY = 40;
    case WHATSAPP = 50;
    case MAIL = 51;
    case REPORTING = 52;
    case SETTINGS = 60;
    case AUDIT = 61;

    /** @return class-string<\App\Logging\Contracts\LogEvent> */
    public function eventEnum(): string
    {
        return match ($this) {
            self::SYSTEM => Events\SystemEvent::class,
            self::AUTH => Events\AuthEvent::class,
            self::ACCESS => Events\AccessEvent::class,
            self::CLIENT => Events\ClientEvent::class,
            self::ORDER => Events\OrderEvent::class,
            self::INVENTORY => Events\InventoryEvent::class,
            self::FINANCE => Events\FinanceEvent::class,
            self::SHIPPING => Events\ShippingEvent::class,
            self::JNT => Events\JntEvent::class,
            self::IMILE => Events\ImileEvent::class,
            self::LOGESTECHS => Events\LogesTechsEvent::class,
            self::RIDER => Events\RiderEvent::class,
            self::SHOPIFY => Events\ShopifyEvent::class,
            self::WHATSAPP => Events\WhatsAppEvent::class,
            self::MAIL => Events\MailEvent::class,
            self::REPORTING => Events\ReportingEvent::class,
            self::SETTINGS => Events\SettingsEvent::class,
            self::AUDIT => Events\AuditEvent::class,
        };
    }

    /** Default channel from config/logging.php; null is the app's default channel. */
    public function channel(): ?string
    {
        return match ($this) {
            self::JNT => 'jnt_webhooks',
            self::IMILE => 'imile_webhooks',
            self::LOGESTECHS => 'logestechs',
            self::SHOPIFY => 'shopify',
            self::WHATSAPP => 'whatsapp',
            self::MAIL => 'mail',
            default => null,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SYSTEM => 'Exceptions, boot, scheduler, queue and the logging system itself',
            self::AUTH => 'Sign-in, 2FA, impersonation, screen lock, presence',
            self::ACCESS => 'Roles, permissions, teams, client access',
            self::CLIENT => 'Merchants and the client portal',
            self::ORDER => 'Orders, exports, order observers',
            self::INVENTORY => 'Products, warehouses, stock scans, inventory requests',
            self::FINANCE => 'Client payments, invoices, rider earnings and payments',
            self::SHIPPING => 'CourierManager, tracking sync, shipment events',
            self::JNT => 'J&T Express driver and webhooks',
            self::IMILE => 'iMile driver and webhook',
            self::LOGESTECHS => 'LogesTechs driver and webhook',
            self::RIDER => 'Rider app, KSA Drop Express driver, rider auth and cash',
            self::SHOPIFY => 'Shopify OAuth, webhooks, fulfillment, order sync, embedded app',
            self::WHATSAPP => 'WhatsApp outbound sends and the inbound webhook',
            self::MAIL => 'Outbound email, bounces, graveyard, transport',
            self::REPORTING => 'Slack ops notifications and scheduled reports',
            self::SETTINGS => 'Settings and connectors',
            self::AUDIT => 'Recycle bin and deletion audit',
        };
    }
}
