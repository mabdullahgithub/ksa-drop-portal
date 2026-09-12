<?php

namespace App\Services;

use App\Models\ClientShopifyConnection;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Log;

/**
 * Everything the app does as a Shopify *fulfillment service*.
 *
 * App Store requirement 5.5.1 asks for `fulfillmentOrderSubmitFulfillmentRequest`,
 * but that mutation cannot be called in isolation: it submits a request to the
 * fulfillment service assigned to a fulfillment order. So the app has to be that
 * service first, which is what this class establishes — register on the store,
 * get a KSADrop location, and from then on Shopify routes fulfillment orders for
 * our products to us.
 *
 * Every call here is gated three ways, because all of them write to a store we
 * do not own:
 *
 *   1. the SHOPIFY_FULFILLMENT_ENABLED kill switch,
 *   2. the connection actually holding the fulfillment scopes, and
 *   3. the connection being active.
 *
 * Nothing here throws. A merchant's store being unreachable, or refusing a call,
 * must never break the flow that called us — OAuth, an order sync, or a courier
 * booking that has already succeeded. Failures are logged to the `shopify`
 * channel and reported as a false return.
 */
class ShopifyFulfillmentService
{
    /**
     * The name merchants see — on the location, in the fulfillment service
     * picker, and in the "Fulfillment service" column of the product CSV. It is
     * also how an existing registration is recognised, so changing it on a live
     * app would orphan every service already registered and create a second
     * location on the next sweep. It does not change.
     */
    public const SERVICE_NAME = 'KSADrop';

    public function __construct(private ShopifyService $shopify) {}

    /**
     * The master switch for every outbound fulfillment call.
     */
    public function enabled(): bool
    {
        return (bool) config('services.shopify.fulfillment');
    }

    /**
     * Prefix Shopify appends its own paths to — /fulfillment_order_notification,
     * /fetch_stock and /fetch_tracking_numbers. Registered once at creation, so
     * it must be a stable public URL; APP_URL is the same source the webhook
     * callback uses.
     */
    public function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/webhooks/shopify/fulfillment';
    }

    /**
     * Make sure the store has a KSADrop fulfillment service, registering one if
     * it doesn't. Safe to call on every install, re-install and app load.
     *
     * Returns true when the connection ends up with a usable service.
     */
    public function ensureRegistered(ClientShopifyConnection $connection): bool
    {
        if (! $this->enabled() || $connection->status !== 'active') {
            return false;
        }

        // Not an error: the fulfillment scopes shipped after this app did, so
        // every store connected before then sits here until managed
        // installation re-prompts the merchant on their next app load.
        if (! $connection->hasFulfillmentScopes()) {
            return false;
        }

        if ($connection->hasFulfillmentService()) {
            return true;
        }

        $shop = $connection->shop_domain;

        try {
            $token = $this->shopify->getValidToken($connection);

            // Look before creating. fulfillmentServiceCreate is NOT idempotent:
            // called twice it makes a second service with a second location of
            // the same name, and the merchant's stock for our products then
            // splits across two "KSADrop" locations — with fulfillment orders
            // routed to whichever one a given variant happens to stock at. That
            // is very hard to unpick by hand, and a re-install or a repaired
            // token is enough to reach this method again, so the lookup is not
            // an optimisation.
            $service = $this->findExistingService($shop, $token)
                ?? $this->createService($shop, $token);

            if (! $service) {
                return false;
            }

            $connection->update([
                'fulfillment_service_id'    => $service['id'],
                'fulfillment_location_id'   => $service['location']['id'] ?? null,
                'fulfillment_registered_at' => now(),
            ]);

            if ($connection->hasFulfillmentService()) {
                $this->describeLocation($shop, $token, $connection->fulfillment_location_id);
            }

            Log::channel('shopify')->info('Shopify fulfillment service ready', [
                'shop'        => $shop,
                'service_id'  => $service['id'],
                'location_id' => $service['location']['id'] ?? null,
                'adopted'     => $service['adopted'] ?? false,
            ]);

            return $connection->hasFulfillmentService();
        } catch (\Throwable $e) {
            Log::channel('shopify')->error('Shopify fulfillment service registration failed', [
                'shop'  => $shop,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Our service on this store, if it is already there.
     *
     * `fulfillmentServices` lists every service installed on the shop, other
     * apps' included, so the match has to identify ours specifically. The
     * callback URL is the reliable signal — it is our domain and no other app
     * can hold it. The name is accepted as a fallback for a service registered
     * before an APP_URL change, which would otherwise be invisible here and
     * duplicated on the spot.
     *
     * @return array{id:string,location:?array,adopted:bool}|null
     */
    private function findExistingService(string $shop, string $token): ?array
    {
        $query = <<<'GQL'
        query {
            shop {
                fulfillmentServices {
                    id
                    serviceName
                    callbackUrl
                    location { id }
                }
            }
        }
        GQL;

        $services = $this->shopify->graphql($shop, $token, $query)['shop']['fulfillmentServices'] ?? [];

        $ours = rtrim($this->callbackUrl(), '/');

        foreach ($services as $service) {
            $callback = rtrim((string) ($service['callbackUrl'] ?? ''), '/');

            if ($callback === $ours || ($service['serviceName'] ?? null) === self::SERVICE_NAME) {
                return [
                    'id'       => $service['id'],
                    'location' => $service['location'] ?? null,
                    'adopted'  => true,
                ];
            }
        }

        return null;
    }

    /**
     * Register a new service. Shopify creates the matching location itself.
     *
     * `inventoryManagement: false` — the portal is the stock system of record
     * and merchants keep managing their own Shopify inventory. Turning it on
     * would make Shopify treat us as the source of truth for these variants and
     * poll /fetch_stock hourly per store, committing us to publishing live
     * per-store stock we have no reason to publish.
     *
     * `trackingSupport: true` — we do supply tracking, but we push it with
     * fulfillmentCreate the moment the waybill exists rather than waiting to be
     * polled, so /fetch_tracking_numbers stays empty.
     *
     * @return array{id:string,location:?array,adopted:bool}|null
     */
    private function createService(string $shop, string $token): ?array
    {
        $mutation = <<<'GQL'
        mutation createFulfillmentService($name: String!, $callbackUrl: URL!) {
            fulfillmentServiceCreate(
                name: $name
                callbackUrl: $callbackUrl
                trackingSupport: true
                inventoryManagement: false
                requiresShippingMethod: true
            ) {
                fulfillmentService {
                    id
                    serviceName
                    location { id }
                }
                userErrors { field message }
            }
        }
        GQL;

        $payload = $this->shopify->graphql($shop, $token, $mutation, [
            'name'        => self::SERVICE_NAME,
            'callbackUrl' => $this->callbackUrl(),
        ])['fulfillmentServiceCreate'] ?? [];

        if (! empty($payload['userErrors'])) {
            Log::channel('shopify')->error('Shopify fulfillmentServiceCreate rejected', [
                'shop'   => $shop,
                'errors' => $payload['userErrors'],
            ]);

            return null;
        }

        $service = $payload['fulfillmentService'] ?? null;

        return $service ? [
            'id'       => $service['id'],
            'location' => $service['location'] ?? null,
            'adopted'  => false,
        ] : null;
    }

    /**
     * Give the new location the warehouse's own address.
     *
     * Shopify creates it inheriting the *merchant's* address, so without this
     * the merchant sees a KSADrop location sitting at their own premises, and
     * shipping rates and tax are calculated from the wrong origin.
     *
     * Best-effort by design: the address is cosmetic next to the routing the
     * location exists for, and a warehouse row with an unexpected province or
     * country code must not cost us the registration. write_fulfillments is
     * enough here — an app may edit the location of a service it created.
     */
    private function describeLocation(string $shop, string $token, string $locationId): void
    {
        $warehouse = Warehouse::query()->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if (! $warehouse) {
            return;
        }

        // The whole input is passed as one LocationEditInput variable so no
        // nested input type has to be named here; Shopify coerces the address
        // object by position. Deliberately minimal — province codes in
        // particular are an enum Shopify validates, and our warehouse rows hold
        // free-text province names.
        $mutation = <<<'GQL'
        mutation editLocation($id: ID!, $input: LocationEditInput!) {
            locationEdit(id: $id, input: $input) {
                location { id name }
                userErrors { field message }
            }
        }
        GQL;

        try {
            $payload = $this->shopify->graphql($shop, $token, $mutation, [
                'id'    => $locationId,
                'input' => [
                    'name'    => self::SERVICE_NAME,
                    'address' => array_filter([
                        'address1'    => $warehouse->address,
                        'city'        => $warehouse->city,
                        'zip'         => $warehouse->post_code,
                        'countryCode' => $warehouse->country_code ?: 'SA',
                    ]),
                ],
            ])['locationEdit'] ?? [];

            if (! empty($payload['userErrors'])) {
                Log::channel('shopify')->warning('Shopify locationEdit rejected', [
                    'shop'   => $shop,
                    'errors' => $payload['userErrors'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('shopify')->warning('Shopify locationEdit failed', [
                'shop'  => $shop,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
