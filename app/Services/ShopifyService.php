<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientShopifyConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * All Shopify API logic lives here. Nothing else should talk to Shopify directly.
 *
 * Uses the GraphQL Admin API (2025+ standard) for webhook registration and order
 * fetching, and OAuth 2.0 authorization code grant with EXPIRING offline tokens
 * (access token ~1h, refresh token ~90d, rotated on every refresh).
 */
class ShopifyService
{
    /** Latest stable Admin API version. Bump on each Shopify quarterly release. */
    public const API_VERSION = '2026-04';

    private string $apiKey;
    private string $apiSecret;
    private string $scopes;
    private string $redirectUri;

    public function __construct()
    {
        $this->apiKey      = (string) config('services.shopify.key');
        $this->apiSecret   = (string) config('services.shopify.secret');
        $this->scopes      = (string) config('services.shopify.scopes');
        $this->redirectUri = (string) config('services.shopify.redirect_uri');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  OAuth & token lifecycle
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the Shopify permission-grant URL the client is redirected to.
     */
    public function buildAuthUrl(string $shop, string $nonce): string
    {
        $params = http_build_query([
            'client_id'    => $this->apiKey,
            'scope'        => $this->scopes,
            'redirect_uri' => $this->redirectUri,
            'state'        => $nonce,
            // 'grant_options[]' omitted => offline (permanent-style) token,
            // which combined with expiring=1 at exchange yields a refreshable token.
        ]);

        return "https://{$shop}/admin/oauth/authorize?{$params}";
    }

    /**
     * Exchange a temporary OAuth code for an EXPIRING offline access token.
     *
     * @return array{access_token:string,refresh_token:?string,expires_in:?int,refresh_token_expires_in:?int,scope:?string}
     */
    public function exchangeCodeForToken(string $shop, string $code): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id'     => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'code'          => $code,
                'expiring'      => '1', // request expiring token + refresh_token
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Shopify OAuth code exchange failed: ' . $response->body());
        }

        return [
            'access_token'             => $response->json('access_token'),
            'refresh_token'            => $response->json('refresh_token'),
            'expires_in'               => $response->json('expires_in'),
            'refresh_token_expires_in' => $response->json('refresh_token_expires_in'),
            'scope'                    => $response->json('scope'),
        ];
    }

    /**
     * Refresh an expiring access token using its refresh token. The refresh token
     * is rotated — both tokens and both expiry timestamps are updated on the model.
     * Returns the new (decrypted) access token.
     */
    public function refreshAccessToken(ClientShopifyConnection $connection): string
    {
        if (! $connection->refresh_token) {
            throw new RuntimeException('No refresh token available; client must reconnect.');
        }

        if ($connection->isRefreshTokenExpired()) {
            $connection->update(['status' => 'error']);
            throw new RuntimeException('Refresh token expired; client must reconnect.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->post("https://{$connection->shop_domain}/admin/oauth/access_token", [
                'client_id'     => $this->apiKey,
                'client_secret' => $this->apiSecret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        if (! $response->successful()) {
            $connection->update(['status' => 'error']);
            throw new RuntimeException('Shopify token refresh failed: ' . $response->body());
        }

        $accessToken = $response->json('access_token');

        $connection->update([
            'access_token'             => $accessToken,
            'refresh_token'            => $response->json('refresh_token') ?? $connection->refresh_token,
            'token_expires_at'         => $this->expiryFromSeconds($response->json('expires_in')),
            'refresh_token_expires_at' => $this->expiryFromSeconds($response->json('refresh_token_expires_in')),
            'status'                   => 'active',
        ]);

        return $accessToken;
    }

    /**
     * Return a valid access token, refreshing first if it is expired / near expiry.
     */
    public function getValidToken(ClientShopifyConnection $connection): string
    {
        if ($connection->isTokenExpired()) {
            return $this->refreshAccessToken($connection);
        }

        return $connection->access_token;
    }

    /**
     * Convert an `expires_in` (seconds) value to an absolute timestamp.
     */
    public function expiryFromSeconds($seconds): ?Carbon
    {
        return $seconds ? now()->addSeconds((int) $seconds) : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HMAC verification
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify the HMAC on an OAuth callback (query params) to block forged redirects.
     *
     * Shopify signs the query string as it appears on the wire, so the message has
     * to keep its original percent-encoding. `host` is base64 of
     * "admin.shopify.com/store/{handle}" and carries "=" padding whenever the
     * handle's length is not a multiple of 3 — that reaches us as "%3D". Rebuilding
     * the message from Laravel's decoded params turns it back into "=", which
     * changes the signed bytes and fails the check for ~2 of every 3 stores.
     *
     * Pass $rawQuery (the untouched server QUERY_STRING) to sign what Shopify
     * actually sent. Both the raw and decoded forms are accepted, since they are
     * identical for unpadded hosts and we do not want to depend on which one
     * Shopify used.
     */
    public function verifyOauthHmac(array $params, ?string $rawQuery = null): bool
    {
        $hmac = (string) ($params['hmac'] ?? '');

        if ($hmac === '') {
            return false;
        }

        foreach ($this->oauthHmacMessages($params, $rawQuery) as $message) {
            if (hash_equals(hash_hmac('sha256', $message, $this->apiSecret), $hmac)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The candidate signed messages for an OAuth callback, preferred form first.
     *
     * @return list<string>
     */
    private function oauthHmacMessages(array $params, ?string $rawQuery): array
    {
        $messages = [];

        if (is_string($rawQuery) && $rawQuery !== '') {
            $pairs = [];

            foreach (explode('&', $rawQuery) as $pair) {
                if ($pair === '') {
                    continue;
                }

                $key = rawurldecode(explode('=', $pair, 2)[0]);

                if ($key === 'hmac' || $key === 'signature') {
                    continue;
                }

                // Key it for the sort, but sign the pair byte-for-byte as received.
                $pairs[$key] = $pair;
            }

            ksort($pairs);
            $messages[] = implode('&', $pairs);
        }

        $messages[] = collect($params)
            ->except(['hmac', 'signature'])
            ->sortKeys()
            ->map(fn ($v, $k) => "{$k}={$v}")
            ->implode('&');

        return $messages;
    }

    /**
     * Verify the HMAC on an incoming webhook POST (raw body + header).
     */
    public function verifyWebhookHmac(string $rawBody, string $hmacHeader): bool
    {
        $computed = base64_encode(hash_hmac('sha256', $rawBody, $this->apiSecret, true));

        return hash_equals($computed, $hmacHeader);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GraphQL transport
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run a GraphQL query/mutation against the Admin API.
     *
     * @return array the `data` payload
     */
    public function graphql(string $shop, string $token, string $query, array $variables = []): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type'           => 'application/json',
        ])->post(
            "https://{$shop}/admin/api/" . self::API_VERSION . '/graphql.json',
            // Cast to object so empty variables serialize as {} (a JSON object),
            // not [] (a JSON array) — Shopify rejects the latter.
            ['query' => $query, 'variables' => (object) $variables]
        );

        if (! $response->successful()) {
            throw new RuntimeException('Shopify GraphQL request failed: ' . $response->body());
        }

        $json = $response->json();

        if (! empty($json['errors'])) {
            throw new RuntimeException('Shopify GraphQL errors: ' . json_encode($json['errors']));
        }

        return $json['data'] ?? [];
    }

    /**
     * Register order webhooks via the GraphQL webhookSubscriptionCreate mutation.
     * Idempotent on Shopify's side for identical topic+endpoint pairs.
     *
     * @return array<string,bool> topic => success
     */
    public function registerWebhooks(string $shop, string $token): array
    {
        $callbackUrl = rtrim((string) config('app.url'), '/') . '/webhooks/shopify';

        $topics = [
            'ORDERS_CREATE',
            'ORDERS_UPDATED',
            'ORDERS_PAID',
            'ORDERS_CANCELLED',
        ];

        $mutation = <<<'GQL'
        mutation webhookSubscriptionCreate($topic: WebhookSubscriptionTopic!, $sub: WebhookSubscriptionInput!) {
          webhookSubscriptionCreate(topic: $topic, webhookSubscription: $sub) {
            webhookSubscription { id }
            userErrors { field message }
          }
        }
        GQL;

        $results = [];

        foreach ($topics as $topic) {
            try {
                $data = $this->graphql($shop, $token, $mutation, [
                    'topic' => $topic,
                    'sub'   => [
                        'callbackUrl' => $callbackUrl,
                        'format'      => 'JSON',
                    ],
                ]);

                $errors = $data['webhookSubscriptionCreate']['userErrors'] ?? [];

                // A duplicate subscription is reported as a userError — treat as success.
                $isDuplicate = collect($errors)->contains(
                    fn ($e) => str_contains(strtolower($e['message'] ?? ''), 'taken')
                        || str_contains(strtolower($e['message'] ?? ''), 'already')
                );

                $results[$topic] = empty($errors) || $isDuplicate;

                if (! empty($errors) && ! $isDuplicate) {
                    Log::warning('Shopify webhook registration userError', [
                        'shop' => $shop, 'topic' => $topic, 'errors' => $errors,
                    ]);
                }
            } catch (\Throwable $e) {
                $results[$topic] = false;
                Log::warning('Shopify webhook registration failed', [
                    'shop' => $shop, 'topic' => $topic, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Fetch a page of recent orders (last 60 days) via GraphQL cursor pagination.
     *
     * @return array{orders:array,hasNextPage:bool,endCursor:?string}
     */
    public function fetchRecentOrders(string $shop, string $token, ?string $cursor = null): array
    {
        $since = now()->subDays(60)->toIso8601String();

        $query = <<<'GQL'
        query recentOrders($cursor: String, $q: String!) {
          orders(first: 50, after: $cursor, query: $q, sortKey: CREATED_AT) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                name
                createdAt
                processedAt
                cancelledAt
                note
                tags
                currencyCode
                displayFinancialStatus
                displayFulfillmentStatus
                customer { firstName lastName email phone }
                email
                phone
                subtotalPriceSet { shopMoney { amount } }
                totalPriceSet { shopMoney { amount } }
                totalTaxSet { shopMoney { amount } }
                totalShippingPriceSet { shopMoney { amount } }
                discountCode
                paymentGatewayNames
                billingAddress { firstName lastName address1 address2 company city zip province countryCodeV2 phone }
                shippingAddress { firstName lastName address1 address2 company city zip province countryCodeV2 phone }
                lineItems(first: 100) {
                  edges {
                    node {
                      name
                      quantity
                      sku
                      requiresShipping
                      taxable
                      variantTitle
                      originalUnitPriceSet { shopMoney { amount } }
                    }
                  }
                }
              }
            }
          }
        }
        GQL;

        $data = $this->graphql($shop, $token, $query, [
            'cursor' => $cursor,
            'q'      => "created_at:>={$since}",
        ]);

        $ordersConn = $data['orders'] ?? [];
        $orders     = collect($ordersConn['edges'] ?? [])->pluck('node')->all();

        return [
            'orders'      => $orders,
            'hasNextPage' => (bool) ($ordersConn['pageInfo']['hasNextPage'] ?? false),
            'endCursor'   => $ordersConn['pageInfo']['endCursor'] ?? null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Order mappers — both return the same orders-table column array
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map a webhook payload (legacy REST-shaped JSON) to our orders columns.
     */
    public function mapWebhookOrder(array $o, Client $client): array
    {
        $billing  = $o['billing_address']  ?? [];
        $shipping = $o['shipping_address'] ?? [];
        $customer = $o['customer']         ?? [];
        $shipLine = $o['shipping_lines'][0] ?? [];

        $shopifyOrderId = (string) ($o['id'] ?? '');
        $shopifyNumber  = $o['order_number'] ?? ($o['name'] ?? $shopifyOrderId);

        return array_merge(
            $this->baseOrderRow($client, $shopifyOrderId, (string) $shopifyNumber),
            [
                'customer_name'      => $this->fullName($customer['first_name'] ?? null, $customer['last_name'] ?? null)
                    ?: ($this->fullName($shipping['first_name'] ?? null, $shipping['last_name'] ?? null) ?: 'Guest'),
                'customer_email'     => $customer['email'] ?? ($o['email'] ?? null),
                'customer_phone'     => $customer['phone'] ?? ($o['phone'] ?? ($shipping['phone'] ?? null)),
                'financial_status'   => strtolower($o['financial_status'] ?? 'pending'),
                'fulfillment_status' => $this->normalizeFulfillment($o['fulfillment_status'] ?? null),
                'currency'           => $o['currency'] ?? 'SAR',
                'subtotal'           => $o['subtotal_price'] ?? 0,
                'total'              => $o['total_price'] ?? 0,
                'taxes'              => $o['total_tax'] ?? 0,
                'shipping_cost'      => $o['total_shipping_price_set']['shop_money']['amount'] ?? ($shipLine['price'] ?? 0),
                'discount_code'      => $o['discount_codes'][0]['code'] ?? null,
                'discount_amount'    => $o['discount_codes'][0]['amount'] ?? ($o['total_discounts'] ?? 0),
                'shipping_method'    => $shipLine['title'] ?? null,
                'notes'              => $o['note'] ?? null,
                'tags'               => $this->filterShopifyTags($o['tags'] ?? null),
                'shopify_raw_tags'   => $this->splitTags($o['tags'] ?? null),
                'payment_method'     => $this->normalizePaymentMethod($o['payment_gateway_names'] ?? []),
                'accepts_marketing'  => (bool) ($customer['accepts_marketing'] ?? false),
                'paid_at'            => $o['processed_at'] ?? null,
                'cancelled_at'       => $o['cancelled_at'] ?? null,
                // Billing
                'billing_name'       => $this->fullName($billing['first_name'] ?? null, $billing['last_name'] ?? null),
                'billing_address1'   => $billing['address1'] ?? null,
                'billing_address2'   => $billing['address2'] ?? null,
                'billing_company'    => $billing['company'] ?? null,
                'billing_city'       => $billing['city'] ?? null,
                'billing_zip'        => $billing['zip'] ?? null,
                'billing_province'   => $billing['province'] ?? null,
                'billing_country'    => $this->country2($billing['country_code'] ?? ($billing['country'] ?? null)),
                'billing_phone'      => $billing['phone'] ?? null,
                // Shipping
                'shipping_name'      => $this->fullName($shipping['first_name'] ?? null, $shipping['last_name'] ?? null),
                'shipping_address1'  => $shipping['address1'] ?? null,
                'shipping_address2'  => $shipping['address2'] ?? null,
                'shipping_company'   => $shipping['company'] ?? null,
                'shipping_city'      => $shipping['city'] ?? null,
                'shipping_zip'       => $shipping['zip'] ?? null,
                'shipping_province'  => $shipping['province'] ?? null,
                'shipping_country'   => $this->country2($shipping['country_code'] ?? ($shipping['country'] ?? null)),
                'shipping_phone'     => $shipping['phone'] ?? null,
            ]
        );
    }

    /**
     * Map a GraphQL order node (camelCase + MoneyV2) to our orders columns.
     */
    public function mapGraphqlOrder(array $n, Client $client): array
    {
        $billing  = $n['billingAddress']  ?? [];
        $shipping = $n['shippingAddress'] ?? [];
        $customer = $n['customer']        ?? [];

        $shopifyOrderId = $this->gidToId($n['id'] ?? '');
        $shopifyNumber  = ltrim((string) ($n['name'] ?? $shopifyOrderId), '#');

        return array_merge(
            $this->baseOrderRow($client, $shopifyOrderId, $shopifyNumber),
            [
                'customer_name'      => $this->fullName($customer['firstName'] ?? null, $customer['lastName'] ?? null)
                    ?: ($this->fullName($shipping['firstName'] ?? null, $shipping['lastName'] ?? null) ?: 'Guest'),
                'customer_email'     => $customer['email'] ?? ($n['email'] ?? null),
                'customer_phone'     => $customer['phone'] ?? ($n['phone'] ?? ($shipping['phone'] ?? null)),
                'financial_status'   => strtolower($n['displayFinancialStatus'] ?? 'pending'),
                'fulfillment_status' => $this->normalizeFulfillment($n['displayFulfillmentStatus'] ?? null),
                'currency'           => $n['currencyCode'] ?? 'SAR',
                'subtotal'           => $n['subtotalPriceSet']['shopMoney']['amount'] ?? 0,
                'total'              => $n['totalPriceSet']['shopMoney']['amount'] ?? 0,
                'taxes'              => $n['totalTaxSet']['shopMoney']['amount'] ?? 0,
                'shipping_cost'      => $n['totalShippingPriceSet']['shopMoney']['amount'] ?? 0,
                'discount_code'      => $n['discountCode'] ?? null,
                'notes'              => $n['note'] ?? null,
                'tags'               => $this->filterShopifyTags($n['tags'] ?? null),
                'shopify_raw_tags'   => $this->splitTags($n['tags'] ?? null),
                'payment_method'     => $this->normalizePaymentMethod($n['paymentGatewayNames'] ?? []),
                'paid_at'            => $n['processedAt'] ?? null,
                'cancelled_at'       => $n['cancelledAt'] ?? null,
                // Billing
                'billing_name'       => $this->fullName($billing['firstName'] ?? null, $billing['lastName'] ?? null),
                'billing_address1'   => $billing['address1'] ?? null,
                'billing_address2'   => $billing['address2'] ?? null,
                'billing_company'    => $billing['company'] ?? null,
                'billing_city'       => $billing['city'] ?? null,
                'billing_zip'        => $billing['zip'] ?? null,
                'billing_province'   => $billing['province'] ?? null,
                'billing_country'    => $this->country2($billing['countryCodeV2'] ?? null),
                'billing_phone'      => $billing['phone'] ?? null,
                // Shipping
                'shipping_name'      => $this->fullName($shipping['firstName'] ?? null, $shipping['lastName'] ?? null),
                'shipping_address1'  => $shipping['address1'] ?? null,
                'shipping_address2'  => $shipping['address2'] ?? null,
                'shipping_company'   => $shipping['company'] ?? null,
                'shipping_city'      => $shipping['city'] ?? null,
                'shipping_zip'       => $shipping['zip'] ?? null,
                'shipping_province'  => $shipping['province'] ?? null,
                'shipping_country'   => $this->country2($shipping['countryCodeV2'] ?? null),
                'shipping_phone'     => $shipping['phone'] ?? null,
            ]
        );
    }

    /**
     * Decide the sync_status a newly-mapped order should receive after applying
     * the connection's merchant-configured sync filters. Only called for NEW
     * orders — existing orders keep their current status (same rule the jobs
     * already apply for sync_mode).
     *
     * The filter check runs first: an order that fails any filter dimension is
     * 'skipped_filtered' regardless of sync_mode. Orders that pass fall through
     * to the sync_mode decision (manual_approval → pending_review, else null).
     */
    public function evaluateSyncFilters(array $mappedOrder, ClientShopifyConnection $connection): ?string
    {
        $filters = $connection->sync_filters ?? [];

        if (! $this->passesStatusFilter($filters, $mappedOrder)
            || ! $this->passesTagFilter($filters, $mappedOrder)
            || ! $this->passesPaymentMethodFilter($filters, $mappedOrder)) {
            return 'skipped_filtered';
        }

        return $connection->sync_mode === 'manual_approval' ? 'pending_review' : null;
    }

    /**
     * Empty/absent status lists mean "allow all" — filters are opt-in restrictions.
     */
    private function passesStatusFilter(array $filters, array $order): bool
    {
        $financial = $filters['financial_statuses'] ?? [];

        if (! empty($financial) && ! in_array($order['financial_status'] ?? null, $financial, true)) {
            return false;
        }

        $fulfillment = $filters['fulfillment_statuses'] ?? [];

        if (! empty($fulfillment) && ! in_array($order['fulfillment_status'] ?? null, $fulfillment, true)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluated against the FULL raw tag list (shopify_raw_tags), not the
     * narrowed `tags` column. Include list = order must carry at least one of
     * them; exclude list = order must carry none.
     */
    private function passesTagFilter(array $filters, array $order): bool
    {
        $orderTags = array_map('strtolower', $order['shopify_raw_tags'] ?? []);

        $include = array_map('strtolower', $filters['tags_include'] ?? []);

        if (! empty($include) && empty(array_intersect($include, $orderTags))) {
            return false;
        }

        $exclude = array_map('strtolower', $filters['tags_exclude'] ?? []);

        if (! empty($exclude) && ! empty(array_intersect($exclude, $orderTags))) {
            return false;
        }

        return true;
    }

    private function passesPaymentMethodFilter(array $filters, array $order): bool
    {
        $mode = $filters['payment_method'] ?? 'all';

        if ($mode === 'all') {
            return true;
        }

        return ($order['payment_method'] ?? 'unknown') === $mode;
    }

    /**
     * Normalize line items (from either webhook or GraphQL shape) into
     * order_items column rows.
     */
    public function mapLineItems(array $order, string $shape): array
    {
        if ($shape === 'graphql') {
            $items = collect($order['lineItems']['edges'] ?? [])->pluck('node');

            return $items->map(fn ($i) => [
                'lineitem_name'               => $i['name'] ?? 'Item',
                'lineitem_quantity'           => (int) ($i['quantity'] ?? 1),
                'lineitem_price'              => $i['originalUnitPriceSet']['shopMoney']['amount'] ?? 0,
                'lineitem_sku'                => $i['sku'] ?? null,
                'lineitem_requires_shipping'  => (bool) ($i['requiresShipping'] ?? true),
                'lineitem_taxable'            => (bool) ($i['taxable'] ?? false),
                'lineitem_fulfillment_status' => 'unfulfilled',
                'variant_name'                => $i['variantTitle'] ?? null,
            ])->all();
        }

        // webhook (REST-shaped)
        return collect($order['line_items'] ?? [])->map(fn ($i) => [
            'lineitem_name'               => $i['name'] ?? ($i['title'] ?? 'Item'),
            'lineitem_quantity'           => (int) ($i['quantity'] ?? 1),
            'lineitem_price'              => $i['price'] ?? 0,
            'lineitem_sku'                => $i['sku'] ?? null,
            'lineitem_requires_shipping'  => (bool) ($i['requires_shipping'] ?? true),
            'lineitem_taxable'            => (bool) ($i['taxable'] ?? false),
            'lineitem_fulfillment_status' => $i['fulfillment_status'] ?? 'unfulfilled',
            'variant_name'                => $i['variant_title'] ?? null,
        ])->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Shared base columns for a mapped order. The order_number is prefixed with
     * the client's short_id so Shopify's per-store numbering stays globally unique
     * in our shared orders table (mirrors the manual-order convention).
     */
    private function baseOrderRow(Client $client, string $shopifyOrderId, string $shopifyNumber): array
    {
        $prefix = $client->short_id ?: ('CL' . $client->id);

        return [
            'client_id'        => $client->id,
            'shopify_order_id' => $shopifyOrderId,
            'order_number'     => $prefix . $shopifyNumber,
            'source'           => 'shopify',
        ];
    }

    private function fullName(?string $first, ?string $last): string
    {
        return trim(($first ?? '') . ' ' . ($last ?? ''));
    }

    private function splitTags($tags): array
    {
        if (is_array($tags)) {
            return $tags;
        }

        return $tags ? array_values(array_filter(array_map('trim', explode(',', (string) $tags)))) : [];
    }

    private function filterShopifyTags($tags): array
    {
        $all = $this->splitTags($tags);

        $buyease = array_values(array_filter($all, fn($t) => stripos($t, 'buyease') !== false));

        return $buyease ?: [];
    }

    /**
     * Bucket Shopify's gateway names into cod / prepaid / unknown. Shopify has
     * no first-class "is COD" flag, so this is a name heuristic: any gateway
     * containing "cash on delivery" or "cod" counts as COD.
     */
    private function normalizePaymentMethod(array $gatewayNames): string
    {
        $names = array_map('strtolower', $gatewayNames);

        foreach ($names as $name) {
            if (str_contains($name, 'cash on delivery') || str_contains($name, 'cod')) {
                return 'cod';
            }
        }

        return $names === [] ? 'unknown' : 'prepaid';
    }

    /**
     * Shopify fulfillment status can be null/UNFULFILLED/FULFILLED/PARTIALLY_FULFILLED.
     */
    private function normalizeFulfillment(?string $status): string
    {
        $status = strtolower((string) $status);

        return match ($status) {
            'fulfilled'             => 'fulfilled',
            'partially_fulfilled'   => 'unfulfilled',
            'restocked', 'cancelled' => 'cancelled',
            default                 => 'unfulfilled',
        };
    }

    /**
     * Our billing/shipping_country columns are 2 chars — clamp longer values.
     */
    private function country2(?string $country): ?string
    {
        if (! $country) {
            return null;
        }

        return strlen($country) === 2 ? strtoupper($country) : substr(strtoupper($country), 0, 2);
    }

    /**
     * Extract the numeric id from a Shopify GID (gid://shopify/Order/123 → 123).
     */
    private function gidToId(string $gid): string
    {
        if (str_contains($gid, '/')) {
            return (string) substr($gid, strrpos($gid, '/') + 1);
        }

        return $gid;
    }

    /**
     * Normalize raw user input to a valid *.myshopify.com domain.
     */
    public function normalizeShopDomain(string $input): string
    {
        $domain = preg_replace('#^https?://#', '', trim($input));
        $domain = rtrim((string) $domain, '/');
        $domain = explode('/', $domain)[0];

        if (! str_contains($domain, '.')) {
            $domain .= '.myshopify.com';
        }

        return strtolower($domain);
    }

    /**
     * Basic guard that a domain looks like a Shopify store host.
     */
    public function isValidShopDomain(string $domain): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $domain);
    }
}
