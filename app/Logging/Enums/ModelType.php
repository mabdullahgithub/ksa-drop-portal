<?php

namespace App\Logging\Enums;

use App\Models;

/**
 * Business models whose lifecycle (created, updated, deleted, restored,
 * purged) is written to the history log by ModelHistoryObserver. Context key
 * `mdl`; the row's primary key goes in `rid`. Append-only: never renumber.
 * Adding a model here is all it takes to give it a history.
 */
enum ModelType: int
{
    case USER = 1;
    case CLIENT = 2;
    case ORDER = 3;
    case SHIPMENT = 4;
    case PRODUCT = 5;
    case CLIENT_PRODUCT = 6;
    case WAREHOUSE = 7;
    case INVENTORY_REQUEST = 8;
    case STOCK_SCAN = 9;
    case RIDER = 10;
    case RIDER_PAYMENT = 11;
    case RIDER_EARNING = 12;
    case CLIENT_PAYMENT = 13;
    case INVOICE = 14;
    case ROLE = 15;
    case TEAM = 16;
    case TAG = 17;
    case CONNECTOR = 18;
    case CONNECTOR_SETTING = 19;
    case EMAIL_SETTING = 20;
    case SHOP_CONNECTION = 21;
    case SHOPIFY_SYNC_FAILURE = 22;
    case RIDER_DEVICE = 23;
    case RIDER_ACTIVATION = 24;
    case DROPSHIP_SALE = 25;

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::USER => Models\User::class,
            self::CLIENT => Models\Client::class,
            self::ORDER => Models\Order::class,
            self::SHIPMENT => Models\Shipment::class,
            self::PRODUCT => Models\Product::class,
            self::CLIENT_PRODUCT => Models\ClientProduct::class,
            self::WAREHOUSE => Models\Warehouse::class,
            self::INVENTORY_REQUEST => Models\InventoryRequest::class,
            self::STOCK_SCAN => Models\StockScan::class,
            self::RIDER => Models\Rider::class,
            self::RIDER_PAYMENT => Models\RiderPayment::class,
            self::RIDER_EARNING => Models\RiderEarning::class,
            self::CLIENT_PAYMENT => Models\ClientPayment::class,
            self::INVOICE => Models\Invoice::class,
            self::ROLE => config('permission.models.role', \Spatie\Permission\Models\Role::class),
            self::TEAM => Models\Team::class,
            self::TAG => Models\Tag::class,
            self::CONNECTOR => Models\Connector::class,
            self::CONNECTOR_SETTING => Models\ConnectorSetting::class,
            self::EMAIL_SETTING => Models\EmailSetting::class,
            self::SHOP_CONNECTION => Models\ClientShopifyConnection::class,
            self::SHOPIFY_SYNC_FAILURE => Models\ShopifySyncFailure::class,
            self::RIDER_DEVICE => Models\RiderDevice::class,
            self::RIDER_ACTIVATION => Models\RiderActivation::class,
            self::DROPSHIP_SALE => Models\DropshipSale::class,
        };
    }

    public static function of(object $model): ?self
    {
        foreach (self::cases() as $case) {
            if ($model instanceof ($case->modelClass())) {
                return $case;
            }
        }

        return null;
    }

    public function description(): string
    {
        return match ($this) {
            self::USER => 'users',
            self::CLIENT => 'clients',
            self::ORDER => 'orders',
            self::SHIPMENT => 'shipments',
            self::PRODUCT => 'products',
            self::CLIENT_PRODUCT => 'client_products',
            self::WAREHOUSE => 'warehouses',
            self::INVENTORY_REQUEST => 'inventory_requests',
            self::STOCK_SCAN => 'stock_scans',
            self::RIDER => 'riders',
            self::RIDER_PAYMENT => 'rider_payments',
            self::RIDER_EARNING => 'rider_earnings',
            self::CLIENT_PAYMENT => 'client_payments',
            self::INVOICE => 'invoices',
            self::ROLE => 'roles',
            self::TEAM => 'teams',
            self::TAG => 'tags',
            self::CONNECTOR => 'connectors',
            self::CONNECTOR_SETTING => 'connector_settings',
            self::EMAIL_SETTING => 'email_settings',
            self::SHOP_CONNECTION => 'client_shopify_connections',
            self::SHOPIFY_SYNC_FAILURE => 'shopify_sync_failures',
            self::RIDER_DEVICE => 'rider_devices',
            self::RIDER_ACTIVATION => 'rider_activations',
            self::DROPSHIP_SALE => 'dropship_sales',
        };
    }
}
