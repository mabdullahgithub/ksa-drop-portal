<?php

namespace App\Services;

use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\Shipment;
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
                'fulfillment_service_id'     => $service['id'],
                // Shopify's own handle, not a slug of our name: where the handle
                // was already taken Shopify appends a suffix, and the product
                // CSV has to carry whatever this particular store ended up with.
                'fulfillment_service_handle' => $service['handle'] ?? null,
                'fulfillment_location_id'    => $service['location']['id'] ?? null,
                'fulfillment_registered_at'  => now(),
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
                    handle
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
                    'handle'   => $service['handle'] ?? null,
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
                    handle
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
            'handle'   => $service['handle'] ?? null,
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

    // ─────────────────────────────────────────────────────────────────────────
    //  Fulfillment requests — App Store requirement 5.5.1
    // ─────────────────────────────────────────────────────────────────────────

    /** The request reached Shopify. */
    public const REQUEST_SENT = 'requested';

    /** Refused on our side: the order has not been paid for. Requirement 5.5.5. */
    public const REQUEST_AWAITING_PAYMENT = 'awaiting_payment';

    /** A request is already open for this order; submitting again is a no-op. */
    public const REQUEST_ALREADY_SENT = 'already_requested';

    /** Shopify has no requestable fulfillment order at our location. */
    public const REQUEST_NO_FULFILLMENT_ORDER = 'no_fulfillment_order';

    /** The store cannot take requests yet — no service, no scopes, switch off. */
    public const REQUEST_UNAVAILABLE = 'unavailable';

    /** Shopify refused the request, or could not be reached. */
    public const REQUEST_FAILED = 'failed';

    /**
     * Financial states that are never fulfilled, COD or not. A voided or
     * refunded order is not "awaiting" anything — shipping it would be sending
     * goods against money that has gone back to the customer.
     */
    private const NEVER_FULFILLED = ['voided', 'refunded'];

    /**
     * Financial states that mean the money is not in yet. Fulfilling these is
     * what requirement 5.5.5 forbids, and COD is the deliberate exception —
     * see awaitingPayment().
     */
    private const UNPAID = ['pending', 'partially_paid'];

    /**
     * Ask Shopify to send us this order for fulfillment — the mutation
     * requirement 5.5.1 is about.
     *
     * Reached from four places: the order webhook for auto-sync stores, the
     * portal queue when a client approves an order, the merchant's button in
     * the embedded app, and Shopify admin's own Request fulfillment action.
     * They all come through here rather than calling the mutation themselves,
     * so the payment gate below cannot be bypassed by choosing a different
     * route in.
     *
     * Returns one of the REQUEST_* constants. Never throws.
     */
    public function requestFulfillment(Order $order): string
    {
        if (! $this->enabled()) {
            return self::REQUEST_UNAVAILABLE;
        }

        // Already submitted. The id is written the moment a request goes out,
        // which makes it the idempotency key for every entry point: an order
        // that is webhook-imported, then approved, then pressed by the merchant
        // must still produce exactly one request.
        if ($order->shopify_fulfillment_order_id) {
            return self::REQUEST_ALREADY_SENT;
        }

        $connection = $this->connectionFor($order);

        if (! $connection || ! $connection->hasFulfillmentScopes() || ! $connection->hasFulfillmentService()) {
            return self::REQUEST_UNAVAILABLE;
        }

        if (! $order->shopify_order_id) {
            return self::REQUEST_UNAVAILABLE;
        }

        $financial = strtolower((string) $order->financial_status);

        if (in_array($financial, self::NEVER_FULFILLED, true)) {
            return self::REQUEST_UNAVAILABLE;
        }

        if ($this->awaitingPayment($order)) {
            // Logged rather than silent: "why has this order not gone to the
            // courier" is a support question, and this is the answer to it.
            Log::channel('shopify')->info('Fulfillment request withheld — order not paid', [
                'order_id'         => $order->id,
                'shopify_order_id' => $order->shopify_order_id,
                'financial_status' => $financial,
                'payment_method'   => $order->payment_method,
            ]);

            return self::REQUEST_AWAITING_PAYMENT;
        }

        try {
            $token = $this->shopify->getValidToken($connection);

            $fulfillmentOrderId = $this->findRequestableFulfillmentOrder($connection, $token, $order);

            if (! $fulfillmentOrderId) {
                // Logged, because this is the outcome a store with an
                // un-routed catalogue produces on every single order — the
                // variants still stock at the merchant's own location, so
                // Shopify never assigned us anything — and without a line here
                // it is indistinguishable from nothing having happened.
                Log::channel('shopify')->warning('Fulfillment request found nothing to request', [
                    'shop'             => $connection->shop_domain,
                    'order_id'         => $order->id,
                    'shopify_order_id' => $order->shopify_order_id,
                    'hint'             => 'No open fulfillment order at the KSADrop location — check the variants were imported with the KSADrop fulfillment service.',
                ]);

                return self::REQUEST_NO_FULFILLMENT_ORDER;
            }

            $mutation = <<<'GQL'
            mutation submitFulfillmentRequest($id: ID!, $message: String) {
                fulfillmentOrderSubmitFulfillmentRequest(id: $id, message: $message) {
                    submittedFulfillmentOrder { id }
                    userErrors { field message }
                }
            }
            GQL;

            $payload = $this->shopify->graphql($connection->shop_domain, $token, $mutation, [
                'id'      => $fulfillmentOrderId,
                'message' => 'Fulfilled by KSA Drop.',
            ])['fulfillmentOrderSubmitFulfillmentRequest'] ?? [];

            if (! empty($payload['userErrors'])) {
                Log::channel('shopify')->error('fulfillmentOrderSubmitFulfillmentRequest rejected', [
                    'shop'             => $connection->shop_domain,
                    'order_id'         => $order->id,
                    'fulfillment_order' => $fulfillmentOrderId,
                    'errors'           => $payload['userErrors'],
                ]);

                return self::REQUEST_FAILED;
            }

            $order->forceFill([
                'shopify_fulfillment_order_id'     => $fulfillmentOrderId,
                'shopify_fulfillment_status'       => 'requested',
                'shopify_fulfillment_requested_at' => now(),
            ])->save();

            Log::channel('shopify')->info('Fulfillment requested', [
                'shop'              => $connection->shop_domain,
                'order_id'          => $order->id,
                'fulfillment_order' => $fulfillmentOrderId,
            ]);

            return self::REQUEST_SENT;
        } catch (\Throwable $e) {
            Log::channel('shopify')->error('Fulfillment request failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return self::REQUEST_FAILED;
        }
    }

    /**
     * Whether requirement 5.5.5 blocks this order.
     *
     * "Stop your app from automatically marking orders in a pending payment
     * state as fulfilled." Taken literally in Saudi Arabia that would stop the
     * business: cash on delivery is most of KSA Drop's volume and Shopify
     * reports every COD order as `pending` until the courier collects, so the
     * money cannot arrive before fulfillment — collecting it *is* the delivery.
     *
     * So COD is exempt and everything else is not. An unpaid card order is
     * exactly the fraud case the requirement exists for, and it is refused.
     *
     * COD is read from the same normalised `payment_method` the sync filters
     * use (ShopifyService::normalizePaymentMethod), so the app has one
     * definition of COD rather than two that can drift apart.
     */
    private function awaitingPayment(Order $order): bool
    {
        if (! in_array(strtolower((string) $order->financial_status), self::UNPAID, true)) {
            return false;
        }

        return $order->payment_method !== 'cod';
    }

    /**
     * The fulfillment order on this Shopify order that is ours and can still be
     * requested.
     *
     * Two filters, both load-bearing. The location check keeps us off other
     * suppliers' fulfillment orders — a merchant may well use more than one
     * dropshipper, and their fulfillment orders sit on the same Shopify order
     * as ours. The status check keeps a second request off one already in
     * flight.
     */
    private function findRequestableFulfillmentOrder(ClientShopifyConnection $connection, string $token, Order $order): ?string
    {
        $query = <<<'GQL'
        query orderFulfillmentOrders($id: ID!) {
            order(id: $id) {
                fulfillmentOrders(first: 20) {
                    nodes {
                        id
                        status
                        requestStatus
                        assignedLocation { location { id } }
                    }
                }
            }
        }
        GQL;

        $nodes = $this->shopify->graphql($connection->shop_domain, $token, $query, [
            'id' => 'gid://shopify/Order/' . $order->shopify_order_id,
        ])['order']['fulfillmentOrders']['nodes'] ?? [];

        foreach ($nodes as $node) {
            $atOurLocation = ($node['assignedLocation']['location']['id'] ?? null) === $connection->fulfillment_location_id;

            // OPEN is the only status that can carry a new request. REJECTED is
            // requestable again — a merchant who fixes an address we rejected
            // should be able to send it back to us.
            $requestable = ($node['status'] ?? null) === 'OPEN'
                && in_array($node['requestStatus'] ?? null, ['UNSUBMITTED', 'REJECTED'], true);

            if ($atOurLocation && $requestable) {
                return $node['id'];
            }
        }

        return null;
    }

    /**
     * Accept a request the merchant has submitted. From here Shopify shows the
     * order as in progress with us, and we can create fulfillments against it.
     */
    public function acceptRequest(ClientShopifyConnection $connection, string $fulfillmentOrderId): bool
    {
        $mutation = <<<'GQL'
        mutation acceptFulfillmentRequest($id: ID!, $message: String) {
            fulfillmentOrderAcceptFulfillmentRequest(id: $id, message: $message) {
                fulfillmentOrder { id status requestStatus }
                userErrors { field message }
            }
        }
        GQL;

        return $this->runFulfillmentOrderMutation(
            $connection,
            $mutation,
            ['id' => $fulfillmentOrderId, 'message' => 'Accepted by KSA Drop.'],
            'fulfillmentOrderAcceptFulfillmentRequest',
            ['fulfillment_order' => $fulfillmentOrderId],
        );
    }

    /**
     * Decline a request, with a reason the merchant reads in their own admin.
     *
     * Worth having even though the portal could simply ignore the order: a
     * dismissed order that stays "requested" in Shopify forever tells the
     * merchant nothing, and they have no way to find out we are not shipping it
     * until a customer asks.
     */
    public function rejectRequest(ClientShopifyConnection $connection, string $fulfillmentOrderId, string $message, string $reason = 'OTHER'): bool
    {
        $mutation = <<<'GQL'
        mutation rejectFulfillmentRequest($id: ID!, $message: String, $reason: FulfillmentOrderRejectionReason) {
            fulfillmentOrderRejectFulfillmentRequest(id: $id, message: $message, reason: $reason) {
                fulfillmentOrder { id status requestStatus }
                userErrors { field message }
            }
        }
        GQL;

        return $this->runFulfillmentOrderMutation(
            $connection,
            $mutation,
            ['id' => $fulfillmentOrderId, 'message' => $message, 'reason' => $reason],
            'fulfillmentOrderRejectFulfillmentRequest',
            ['fulfillment_order' => $fulfillmentOrderId, 'reason' => $reason],
        );
    }

    /**
     * Fulfillment orders Shopify is currently holding for us in a given
     * assignment status — FULFILLMENT_REQUESTED or CANCELLATION_REQUESTED.
     *
     * This is what makes the /fulfillment_order_notification callback a real
     * safety net rather than a formality: it asks Shopify what is outstanding
     * instead of trusting the notification body, so it recovers a request whose
     * webhook was never delivered as well as the one that prompted the call.
     *
     * @return array<int,array{id:string,orderId:?string}>
     */
    public function assignedFulfillmentOrders(ClientShopifyConnection $connection, string $assignmentStatus): array
    {
        if (! $this->enabled() || ! $connection->hasFulfillmentService()) {
            return [];
        }

        $query = <<<'GQL'
        query assigned($status: FulfillmentOrderAssignmentStatus!, $locationIds: [ID!]) {
            assignedFulfillmentOrders(first: 50, assignmentStatus: $status, locationIds: $locationIds) {
                nodes {
                    id
                    order { id }
                }
            }
        }
        GQL;

        try {
            $token = $this->shopify->getValidToken($connection);

            $nodes = $this->shopify->graphql($connection->shop_domain, $token, $query, [
                'status'      => $assignmentStatus,
                'locationIds' => [$connection->fulfillment_location_id],
            ])['assignedFulfillmentOrders']['nodes'] ?? [];

            return array_map(fn ($node) => [
                'id'      => $node['id'],
                'orderId' => $node['order']['id'] ?? null,
            ], $nodes);
        } catch (\Throwable $e) {
            Log::channel('shopify')->error('Listing assigned fulfillment orders failed', [
                'shop'   => $connection->shop_domain,
                'status' => $assignmentStatus,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * The connection that owns this order.
     *
     * Keyed on the shop domain the order arrived from rather than on the
     * client's current connection: a client who disconnects one store and
     * connects another still has the first store's orders in the portal, and
     * acting on those through the new store's token would be operating on the
     * wrong merchant's data.
     */
    private function connectionFor(Order $order): ?ClientShopifyConnection
    {
        if ($order->shopify_shop_domain) {
            return ClientShopifyConnection::where('shop_domain', $order->shopify_shop_domain)
                ->where('status', 'active')
                ->first();
        }

        return $order->client?->shopifyConnection;
    }

    /**
     * Run one fulfillment-order mutation, reporting a Shopify refusal and a
     * transport failure the same way: logged, and false. Callers are webhook
     * handlers and queued jobs, and neither has anything better to do with an
     * exception than park it.
     */
    private function runFulfillmentOrderMutation(
        ClientShopifyConnection $connection,
        string $mutation,
        array $variables,
        string $field,
        array $context = [],
    ): bool {
        if (! $this->enabled() || ! $connection->hasFulfillmentScopes()) {
            return false;
        }

        try {
            $token = $this->shopify->getValidToken($connection);

            $payload = $this->shopify->graphql($connection->shop_domain, $token, $mutation, $variables)[$field] ?? [];

            if (! empty($payload['userErrors'])) {
                Log::channel('shopify')->error("{$field} rejected", array_merge($context, [
                    'shop'   => $connection->shop_domain,
                    'errors' => $payload['userErrors'],
                ]));

                return false;
            }

            Log::channel('shopify')->info("{$field} succeeded", array_merge($context, [
                'shop' => $connection->shop_domain,
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::channel('shopify')->error("{$field} failed", array_merge($context, [
                'shop'  => $connection->shop_domain,
                'error' => $e->getMessage(),
            ]));

            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Fulfilling, and taking it back
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Courier names as Shopify writes them in its own carrier list, where a
     * match lets Shopify build the tracking link itself.
     *
     * None of the three are on that list, which is why trackingUrl() supplies
     * one. The names are still sent, because this is the text the customer sees
     * next to the number in Shopify's shipping email.
     */
    private const COURIER_NAMES = [
        'jnt_express' => 'J&T Express',
        'imile'       => 'iMile',
        'logestechs'  => 'Navix',
    ];

    /**
     * Tell Shopify the parcel is on its way, with the courier's waybill.
     *
     * This is the point the merchant's order finally turns Fulfilled in their
     * admin and Shopify emails their customer the tracking number — the half of
     * the integration that did not exist before, and the reason orders used to
     * sit unfulfilled in Shopify forever while we delivered them.
     *
     * Note that "fulfilled" here means *shipped*, which is Shopify's meaning
     * and not ours: our own fulfillment_status stays unfulfilled until the
     * courier delivers. The two are deliberately allowed to disagree, because
     * each is right in its own system.
     */
    public function fulfillFromShipment(Shipment $shipment): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        // Already done. A shipment that is re-saved, or a job that is retried
        // after a response we never read, must not produce a second
        // fulfillment — Shopify would accept it and the customer would get a
        // second shipping email.
        if ($shipment->shopify_fulfillment_id) {
            return true;
        }

        $order = $shipment->order;

        if (! $order || ! $order->shopify_fulfillment_order_id) {
            return false;
        }

        $connection = $this->connectionFor($order);

        if (! $connection || ! $connection->hasFulfillmentScopes()) {
            return false;
        }

        $mutation = <<<'GQL'
        mutation createFulfillment($fulfillment: FulfillmentInput!) {
            fulfillmentCreate(fulfillment: $fulfillment) {
                fulfillment { id status }
                userErrors { field message }
            }
        }
        GQL;

        try {
            $token = $this->shopify->getValidToken($connection);

            $payload = $this->shopify->graphql($connection->shop_domain, $token, $mutation, [
                'fulfillment' => [
                    // No line items named, which fulfils the whole fulfillment
                    // order. Ours only ever holds the lines we supply — Shopify
                    // groups a fulfillment order by location — so there is
                    // nothing on it to leave behind.
                    'lineItemsByFulfillmentOrder' => [
                        ['fulfillmentOrderId' => $order->shopify_fulfillment_order_id],
                    ],
                    'trackingInfo' => array_filter([
                        'number'  => $shipment->tracking_number,
                        'url'     => $this->trackingUrl($shipment->tracking_number),
                        'company' => self::COURIER_NAMES[$shipment->courier] ?? $shipment->courier,
                    ]),
                    'notifyCustomer' => true,
                ],
            ])['fulfillmentCreate'] ?? [];

            if (! empty($payload['userErrors'])) {
                Log::channel('shopify')->error('fulfillmentCreate rejected', [
                    'shop'        => $connection->shop_domain,
                    'shipment_id' => $shipment->id,
                    'errors'      => $payload['userErrors'],
                ]);

                return false;
            }

            $fulfillmentId = $payload['fulfillment']['id'] ?? null;

            if (! $fulfillmentId) {
                return false;
            }

            $shipment->forceFill(['shopify_fulfillment_id' => $fulfillmentId])->save();
            $order->forceFill(['shopify_fulfillment_status' => 'fulfilled'])->save();

            Log::channel('shopify')->info('Shopify fulfillment created', [
                'shop'        => $connection->shop_domain,
                'order_id'    => $order->id,
                'shipment_id' => $shipment->id,
                'tracking'    => $shipment->tracking_number,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('shopify')->error('fulfillmentCreate failed', [
                'shop'        => $connection->shop_domain,
                'shipment_id' => $shipment->id,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Undo a fulfillment because the parcel came back.
     *
     * Left alone, the merchant's order stays Fulfilled with a tracking number
     * their customer can still follow, and their own returns and reporting are
     * wrong from then on.
     */
    public function cancelFulfillment(Shipment $shipment): bool
    {
        if (! $shipment->shopify_fulfillment_id) {
            return false;
        }

        $order = $shipment->order;

        if (! $order) {
            return false;
        }

        $connection = $this->connectionFor($order);

        if (! $connection) {
            return false;
        }

        $mutation = <<<'GQL'
        mutation cancelFulfillment($id: ID!) {
            fulfillmentCancel(id: $id) {
                fulfillment { id status }
                userErrors { field message }
            }
        }
        GQL;

        $cancelled = $this->runFulfillmentOrderMutation(
            $connection,
            $mutation,
            ['id' => $shipment->shopify_fulfillment_id],
            'fulfillmentCancel',
            ['shipment_id' => $shipment->id],
        );

        if ($cancelled) {
            $order->forceFill(['shopify_fulfillment_status' => 'cancelled'])->save();
        }

        return $cancelled;
    }

    /**
     * Answer a merchant asking for an accepted request back.
     *
     * Accepting is only honest while nothing has shipped. Once the courier
     * holds the parcel we cannot unsend it, and saying yes would leave the
     * merchant believing an order was stopped that is still on its way to their
     * customer — so that case is rejected, with the reason.
     */
    public function respondToCancellation(ClientShopifyConnection $connection, string $fulfillmentOrderId, bool $accept, string $message): bool
    {
        $mutation = $accept
            ? <<<'GQL'
            mutation acceptCancellation($id: ID!, $message: String) {
                fulfillmentOrderAcceptCancellationRequest(id: $id, message: $message) {
                    fulfillmentOrder { id status requestStatus }
                    userErrors { field message }
                }
            }
            GQL
            : <<<'GQL'
            mutation rejectCancellation($id: ID!, $message: String) {
                fulfillmentOrderRejectCancellationRequest(id: $id, message: $message) {
                    fulfillmentOrder { id status requestStatus }
                    userErrors { field message }
                }
            }
            GQL;

        return $this->runFulfillmentOrderMutation(
            $connection,
            $mutation,
            ['id' => $fulfillmentOrderId, 'message' => $message],
            $accept ? 'fulfillmentOrderAcceptCancellationRequest' : 'fulfillmentOrderRejectCancellationRequest',
            ['fulfillment_order' => $fulfillmentOrderId],
        );
    }

    /**
     * Where the customer follows the parcel.
     *
     * Our own tracking page rather than the courier's. It already accepts a
     * waybill as ?q= and renders the history the portal holds, it reads the
     * same for all three couriers, and it cannot go stale the way a
     * hand-written third-party URL would — a wrong link here reaches the
     * merchant's customer directly, in Shopify's own shipping email.
     */
    public function trackingUrl(?string $trackingNumber): ?string
    {
        if (! $trackingNumber) {
            return null;
        }

        return rtrim((string) config('app.url'), '/') . '/track?q=' . urlencode($trackingNumber);
    }
}
