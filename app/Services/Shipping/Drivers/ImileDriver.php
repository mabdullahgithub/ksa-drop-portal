<?php

namespace App\Services\Shipping\Drivers;

use App\Models\ConnectorSetting;
use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\DTOs\CancelResult;
use App\Services\Shipping\DTOs\ShipmentData;
use App\Services\Shipping\DTOs\ShipmentResult;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\DTOs\TrackingResult;
use App\Services\Shipping\Enums\ImileErrorCode;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * iMile OpenAPI driver (https://imiledeveloperskit.docs.apiary.io).
 *
 * Every request (except the token grant itself) is a signed JSON envelope:
 * customerId / sign / accessToken / signMethod / timestamp / timeZone / param.
 * With signMethod=SimpleKey (the default and the only mode this driver
 * implements) `sign` is simply the raw APIKey — no hashing required.
 *
 * Only order creation (dropship, orderType=100), cancellation and tracking
 * are implemented — the same scope as {@see JntExpressDriver}. iMile's WMS
 * endpoints (fulfilment/SKU/ASN/stock) are a separate warehousing product
 * and out of scope here.
 *
 * Note: the tracking endpoint's exact response shape was not confirmed
 * against a live sample at integration time (iMile's official Postman
 * collection does not include a tracking request) — {@see buildTrackingResult()}
 * parses several plausible shapes defensively (preferring the field names
 * documented in the iMile Developer's Kit blueprint — `billNo`, `latestStatus`,
 * `locus[]` — and falling back to other plausible names) and logs the raw
 * payload when it doesn't recognise one, so the parsing can be tightened once
 * verified against a real account response.
 *
 * iMile has no outbound webhook/push API (confirmed against the full API
 * blueprint) — tracking is pull-only. {@see trackShipments()} batches up to
 * 100 orders per `client/track/list` call (iMile's documented limit) so a
 * full sync of active shipments costs a fraction of the API calls of polling
 * one-by-one, letting {@see \App\Console\Commands\SyncShipmentTracking} run
 * every few minutes instead of every couple of hours.
 */
class ImileDriver implements CourierDriver
{
    protected ?array $credentials = null;

    public function getDriverName(): string
    {
        return 'imile';
    }

    public function createShipment(ShipmentData $data): ShipmentResult
    {
        $senderCountry = $this->normalizeCountryCode($data->sender['countryCode'] ?? null);
        $receiverCountry = $this->normalizeCountryCode($data->receiver['countryCode'] ?? null);

        $missing = [];
        if ($this->isBlank($data->receiver['city'] ?? null)) {
            $missing[] = 'city';
        }
        if ($this->isBlank($data->receiver['address'] ?? null)) {
            $missing[] = 'address';
        }
        if ($this->isBlank($data->receiver['phone'] ?? null)) {
            $missing[] = 'phone';
        }

        if (! empty($missing)) {
            return ShipmentResult::failure(
                errorMessage: 'Missing or invalid receiver ' . implode(', ', $missing)
                    . '. Please complete the receiver address before creating the shipment.',
                errorCode: 'VALIDATION_ERROR',
            );
        }

        $totalVolume = $this->computeVolume($data);

        $totalDeclaredValue = 0.0;
        foreach ($data->items as $item) {
            $totalDeclaredValue += (float) ($item['value'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1));
        }
        if ($totalDeclaredValue <= 0) {
            $totalDeclaredValue = max($data->codAmount, 1.0);
        }

        $skuInfos = ! empty($data->items)
            ? array_values(array_map(fn ($item) => array_filter([
                'skuNo' => '',
                'skuName' => mb_substr((string) ($item['name'] ?? 'Item'), 0, 100),
                'skuDesc' => mb_substr((string) ($item['name'] ?? 'Item'), 0, 100),
                'skuQty' => (string) max(1, (int) ($item['quantity'] ?? 1)),
                'skuDeclaredValue' => (string) number_format((float) ($item['value'] ?? 0), 2, '.', ''),
                'skuWeight' => (string) $data->weight,
            ], fn ($v) => $v !== ''), $data->items))
            : [[
                'skuNo' => '',
                'skuName' => mb_substr($data->itemDescription ?: 'Package', 0, 100),
                'skuDesc' => mb_substr($data->itemDescription ?: 'Package', 0, 100),
                'skuQty' => (string) $data->quantity,
                'skuDeclaredValue' => (string) number_format($totalDeclaredValue, 2, '.', ''),
                'skuWeight' => (string) $data->weight,
            ]];

        $param = [
            'orderNo' => $data->txlogisticId,
            'orderType' => '100',
            'originalWaybillNo' => '',
            'senderInfo' => array_filter([
                'addressType' => 'warehouse',
                'contacts' => $data->sender['name'] ?? '',
                'phone' => $data->sender['phone'] ?? '',
                'country' => $senderCountry,
                'province' => $data->sender['province'] ?? '',
                'city' => $data->sender['city'] ?? '',
                'area' => $data->sender['area'] ?? '',
                'address' => $data->sender['address'] ?? '',
                'zipCode' => $data->sender['postCode'] ?? '',
            ], fn ($v) => $v !== null && $v !== ''),
            'consigneeInfo' => array_filter([
                'addressType' => 'customer',
                'contacts' => $data->receiver['name'] ?? '',
                'phone' => $data->receiver['phone'] ?? '',
                'country' => $receiverCountry,
                'province' => $data->receiver['province'] ?? '',
                'city' => $data->receiver['city'] ?? '',
                'area' => $data->receiver['area'] ?? '',
                'address' => $data->receiver['address'] ?? '',
                'zipCode' => $data->receiver['postCode'] ?? '',
            ], fn ($v) => $v !== null && $v !== ''),
            'packageInfo' => array_filter([
                'goodsType' => 'Normal',
                'paymentMethod' => $data->codAmount > 0 ? 'COD' : 'PPD',
                'collectingMoney' => $data->codAmount > 0 ? number_format($data->codAmount, 2, '.', '') : '0',
                'clientDeclaredValue' => number_format($totalDeclaredValue, 2, '.', ''),
                'clientDeclaredCurrency' => 'Local',
                'productValue' => number_format($totalDeclaredValue, 2, '.', ''),
                'productValueCurrency' => 'Local',
                'length' => $data->length ? (string) $data->length : '',
                'width' => $data->width ? (string) $data->width : '',
                'high' => $data->height ? (string) $data->height : '',
                'totalVolume' => (string) $totalVolume,
                'grossWeight' => (string) $data->weight,
            ], fn ($v) => $v !== ''),
            'skuInfos' => $skuInfos,
        ];

        if ($data->remark) {
            $param['orderDescription'] = mb_substr($data->remark, 0, 200);
        }

        $response = $this->makeRequest('client/order/v2/createOrder', $param);

        if ($response === null) {
            return ShipmentResult::failure('Failed to connect to iMile API');
        }

        $code = (string) ($response['code'] ?? '');

        if ($code === '200') {
            return ShipmentResult::success(
                trackingNumber: $response['data']['expressNo'] ?? '',
                sortingCode: null,
                txlogisticId: $data->txlogisticId,
                rawResponse: $response,
            );
        }

        Log::error('iMile shipment creation failed', [
            'order_no' => $data->txlogisticId,
            'response_code' => $code,
            'response_message' => $response['message'] ?? null,
            'full_response' => $response,
        ]);

        return ShipmentResult::failure(
            errorMessage: ImileErrorCode::resolve($code, $response['message'] ?? null),
            errorCode: $code !== '' ? $code : null,
            rawResponse: $response,
        );
    }

    public function trackShipment(string $trackingNumber): TrackingResult
    {
        $param = ['orderCodes' => [$trackingNumber]];

        $response = $this->makeRequest('client/track/list', $param);

        if ($response === null) {
            return TrackingResult::failure('Failed to connect to iMile API');
        }

        $code = (string) ($response['code'] ?? '');

        if ($code !== '200') {
            return TrackingResult::failure(
                ImileErrorCode::resolve($code, $response['message'] ?? null),
                $response,
            );
        }

        $records = $this->extractTrackRecords($response['data'] ?? null);

        if ($records === null) {
            Log::warning('iMile trackShipment: unrecognised response shape', [
                'tracking_number' => $trackingNumber,
                'response' => $response,
            ]);
            $records = [];
        }

        return $this->buildTrackingResult($records[0] ?? [], $response);
    }

    /**
     * Bulk variant of {@see trackShipment()}: fetches up to 100 tracking
     * numbers per `client/track/list` call (iMile's documented batch limit),
     * chunking transparently for larger inputs. Returns one TrackingResult
     * per input tracking number, keyed by that tracking number, in the same
     * shape as calling trackShipment() individually.
     *
     * A tracking number iMile doesn't return a record for gets an explicit
     * failure (rather than a lenient "no events yet" success) so callers
     * don't accidentally downgrade an already-progressed shipment's status
     * on a partial/misaligned batch response.
     *
     * @param  string[]  $trackingNumbers
     * @return array<string, TrackingResult>
     */
    public function trackShipments(array $trackingNumbers): array
    {
        $trackingNumbers = array_values(array_unique(array_filter($trackingNumbers, fn ($v) => $v !== null && $v !== '')));

        if ($trackingNumbers === []) {
            return [];
        }

        $results = [];

        foreach (array_chunk($trackingNumbers, 100) as $chunk) {
            $response = $this->makeRequest('client/track/list', ['orderCodes' => $chunk]);

            if ($response === null) {
                foreach ($chunk as $trackingNumber) {
                    $results[$trackingNumber] = TrackingResult::failure('Failed to connect to iMile API');
                }
                continue;
            }

            $code = (string) ($response['code'] ?? '');

            if ($code !== '200') {
                $errorMessage = ImileErrorCode::resolve($code, $response['message'] ?? null);
                foreach ($chunk as $trackingNumber) {
                    $results[$trackingNumber] = TrackingResult::failure($errorMessage, $response);
                }
                continue;
            }

            $records = $this->extractTrackRecords($response['data'] ?? null);

            if ($records === null) {
                Log::warning('iMile trackShipments: unrecognised response shape', [
                    'batch_size' => count($chunk),
                    'response' => $response,
                ]);
                $records = [];
            }

            if (count($records) !== count($chunk)) {
                Log::info('iMile trackShipments: record count does not match requested batch size', [
                    'requested' => count($chunk),
                    'returned' => count($records),
                ]);
            }

            $byIdentifier = [];
            foreach ($records as $record) {
                $identifier = $record['billNo'] ?? $record['orderCode'] ?? $record['expressNo'] ?? $record['waybillNo'] ?? null;
                if ($identifier !== null) {
                    $byIdentifier[(string) $identifier] = $record;
                }
            }

            foreach ($chunk as $index => $trackingNumber) {
                // Prefer matching by identifier field; fall back to positional
                // matching only if iMile didn't echo back an identifier we recognise.
                $record = $byIdentifier[$trackingNumber] ?? $records[$index] ?? null;

                $results[$trackingNumber] = $record !== null
                    ? $this->buildTrackingResult($record, $response)
                    : TrackingResult::failure('No tracking data returned for this shipment', $response);
            }
        }

        return $results;
    }

    /**
     * Parse a single iMile tracking record (one entry of a client/track/list
     * response) into a {@see TrackingResult}. Shared by both the single and
     * batched tracking calls.
     */
    protected function buildTrackingResult(array $record, array $response): TrackingResult
    {
        $scans = $record['locus'] ?? $record['details'] ?? $record['scanList'] ?? $record['events'] ?? [];

        $events = [];
        foreach ($scans as $scan) {
            $rawStatus = (string) ($scan['status'] ?? $scan['scanType'] ?? $scan['statusCode'] ?? '');
            $events[] = new TrackingEvent(
                status: $this->normalizeStatus($rawStatus),
                description: $scan['desc'] ?? $scan['description'] ?? $rawStatus,
                location: $scan['city'] ?? $scan['location'] ?? null,
                timestamp: (string) ($scan['scanTime'] ?? $scan['timestamp'] ?? ''),
                rawStatus: $rawStatus,
            );
        }

        $currentRawStatus = $record['latestStatus'] ?? $record['status'] ?? $record['orderStatus'] ?? null;
        $currentStatus = ! empty($events)
            ? $events[0]->status
            : ($currentRawStatus ? $this->normalizeStatus((string) $currentRawStatus) : ShipmentStatus::INFO_RECEIVED);

        return TrackingResult::success($currentStatus, $events, $response);
    }

    /**
     * iMile's `data` payload shape for client/track/list has not been
     * confirmed against a live response — try the plausible variants and
     * fall back to null (caller logs + treats as "no events") rather than
     * guessing wrong and reporting a false status.
     */
    protected function extractTrackRecords(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        foreach (['list', 'trackList', 'orderStatuses', 'trackInfo'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return array_is_list($data) ? $data : null;
    }

    public function cancelShipment(string $trackingNumber, string $reason): CancelResult
    {
        // ShipmentController passes "orderCode|waybillNo" for iMile shipments
        // (see ShipmentController::cancel()) since deleteOrder needs both
        // identifiers; fall back to using the same value for both when only
        // one is available.
        [$orderCode, $waybillNo] = str_contains($trackingNumber, '|')
            ? explode('|', $trackingNumber, 2)
            : [$trackingNumber, $trackingNumber];

        $param = [
            'orderCode' => $orderCode,
            'waybillNo' => $waybillNo,
        ];

        $response = $this->makeRequest('client/order/deleteOrder', $param);

        if ($response === null) {
            return CancelResult::failure('Failed to connect to iMile API');
        }

        $code = (string) ($response['code'] ?? '');

        if ($code === '200') {
            return CancelResult::success($response);
        }

        return CancelResult::failure(
            errorMessage: ImileErrorCode::resolve($code, $response['message'] ?? null),
            errorCode: $code !== '' ? $code : null,
            rawResponse: $response,
        );
    }

    public function testConnection(): bool
    {
        try {
            $this->getCredentials();

            return ! empty($this->getAccessToken(forceRefresh: true));
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Map an iMile tracking status code onto our internal {@see ShipmentStatus}.
     * Codes per the iMile Developer's Kit "Tracking Status Codes" table.
     */
    public function normalizeStatus(string $rawStatus): ShipmentStatus
    {
        return match ($rawStatus) {
            'SubmitOrder', 'SCH', 'AssignPickup', 'CancelAssignPickup', 'PickUpOrder' => ShipmentStatus::INFO_RECEIVED,

            'RecevieOrder', 'Shipping', 'Arrive', 'AssignDA', 'Transfer', 'Unpacking',
            'DeliveryCompleted', 'ReturnShipping', 'ReturnArrive',
            'IntlReceived', 'IntlShipping', 'IntlArrive', 'IntlClearanceScan', 'IntlClearance',
            'operation_change_delivery_method', 'operation_self_pickup_point_inbound' => ShipmentStatus::IN_TRANSIT,

            'OFD' => ShipmentStatus::OUT_FOR_DELIVERY,

            'Delivered' => ShipmentStatus::DELIVERED,

            'BTD', 'ReturnFailed', 'Abandon', 'AbnormalDelivered',
            'cs_schedule_failed_no_answer' => ShipmentStatus::EXCEPTION,

            'ReturnSuccess', 'RefundSuccess', 'RTC' => ShipmentStatus::RETURNED,

            'CancelOrder' => ShipmentStatus::CANCELLED,

            default => ShipmentStatus::IN_TRANSIT,
        };
    }

    protected function computeVolume(ShipmentData $data): float
    {
        if ($data->length && $data->width && $data->height) {
            return round($data->length * $data->width * $data->height, 2);
        }

        return 0;
    }

    protected function isBlank(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-';
    }

    /**
     * iMile uses its own ALPHA-3-like country codes (see the "iMile Supported
     * Country List" in the docs), not standard ISO 3166. Map common
     * representations onto iMile's list; unknown values pass through as-is.
     */
    protected function normalizeCountryCode(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        $map = [
            '' => 'KSA', 'SA' => 'KSA', 'SAU' => 'KSA', 'KSA' => 'KSA', 'SAUDI ARABIA' => 'KSA',
            'AE' => 'UAE', 'ARE' => 'UAE', 'UAE' => 'UAE', 'UNITED ARAB EMIRATES' => 'UAE',
            'CN' => 'CHN', 'CHN' => 'CHN', 'CHINA' => 'CHN',
            'KW' => 'KWT', 'KWT' => 'KWT', 'KUWAIT' => 'KWT',
            'BH' => 'BHR', 'BHR' => 'BHR', 'BAHRAIN' => 'BHR',
            'TR' => 'TUR', 'TUR' => 'TUR', 'TURKEY' => 'TUR',
            'OM' => 'OMN', 'OMN' => 'OMN', 'OMAN' => 'OMN',
            'MX' => 'MEX', 'MEX' => 'MEX', 'MEXICO' => 'MEX',
            'QA' => 'QAT', 'QAT' => 'QAT', 'QATAR' => 'QAT',
            'EG' => 'EGY', 'EGY' => 'EGY', 'EGYPT' => 'EGY',
            'JO' => 'JOR', 'JOR' => 'JOR', 'JORDAN' => 'JOR',
            'BR' => 'BRA', 'BRA' => 'BRA', 'BRAZIL' => 'BRA',
            'AU' => 'AUS', 'AUS' => 'AUS', 'AUSTRALIA' => 'AUS',
            'GB' => 'GBR', 'UK' => 'GBR', 'GBR' => 'GBR', 'UNITED KINGDOM' => 'GBR',
        ];

        return $map[$value] ?? $value;
    }

    protected function getCredentials(): array
    {
        if ($this->credentials) {
            return $this->credentials;
        }

        // DB-backed ConnectorSetting (saved from Apps → iMile Settings) is the
        // source of truth. When a value is missing we fall back to
        // config/services.php (env-driven) so the integration keeps working
        // before anything is configured in the UI.
        $this->credentials = [
            'customer_id' => trim((string) (ConnectorSetting::getForConnector('imile', 'customer_id') ?: config('services.imile.customer_id'))),
            'api_key'     => trim((string) (ConnectorSetting::getForConnector('imile', 'api_key') ?: config('services.imile.api_key'))),
            'base_url'    => rtrim(trim((string) (ConnectorSetting::getForConnector('imile', 'base_url') ?: config('services.imile.base_url', 'https://openapi.imile.com'))), '/'),
            'time_zone'   => trim((string) (ConnectorSetting::getForConnector('imile', 'time_zone') ?: config('services.imile.time_zone', '+3'))),
        ];

        if (! $this->credentials['customer_id'] || ! $this->credentials['api_key']) {
            throw new \RuntimeException('iMile credentials (CustomerID / APIKey) are not configured.');
        }

        return $this->credentials;
    }

    /**
     * Fetch (and cache) an iMile access token via auth/accessToken/grant.
     * Tokens are cached for their reported `expiresIn` (seconds) minus a
     * 60s safety margin, scoped per customerId.
     */
    protected function getAccessToken(bool $forceRefresh = false): ?string
    {
        $credentials = $this->getCredentials();
        $cacheKey = 'imile:access_token:' . $credentials['customer_id'];

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $body = [
            'customerId' => $credentials['customer_id'],
            'sign' => $credentials['api_key'],
            'signMethod' => 'SimpleKey',
            'format' => 'json',
            'version' => '1.0.0',
            // Sent as a JSON number, matching iMile's own sample requests
            // (unquoted {{TimeStamp}} in their Postman collection) — some
            // gateways treat a quoted numeric string differently for signing.
            'timestamp' => (int) round(microtime(true) * 1000),
            'timeZone' => $credentials['time_zone'],
            'param' => ['grantType' => 'clientCredential'],
        ];

        try {
            $response = Http::timeout(30)->post(
                $credentials['base_url'] . '/auth/accessToken/grant',
                $body,
            );
        } catch (\Exception $e) {
            Log::error('iMile token grant request failed', ['error' => $e->getMessage()]);

            return null;
        }

        $json = $response->json();
        $code = (string) ($json['code'] ?? '');

        if ($code !== '200' || empty($json['data']['accessToken'])) {
            Log::error('iMile token grant rejected', [
                'code' => $code,
                'message' => $json['message'] ?? null,
                'customer_id' => $credentials['customer_id'],
                'sign_length' => strlen($credentials['api_key']),
            ]);

            return null;
        }

        $token = $json['data']['accessToken'];
        $ttl = max(60, (int) ($json['data']['expiresIn'] ?? 7200) - 60);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    protected function makeRequest(string $endpoint, array $param, int $maxRetries = 2): ?array
    {
        $rateLimitKey = 'imile:api';

        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            Log::warning('iMile API rate limit reached');

            return null;
        }

        $credentials = $this->getCredentials();
        $accessToken = $this->getAccessToken();

        if (! $accessToken) {
            return null;
        }

        $attempt = 0;
        $tokenRetried = false;

        while ($attempt <= $maxRetries) {
            try {
                $body = [
                    'customerId' => $credentials['customer_id'],
                    'sign' => $credentials['api_key'],
                    'accessToken' => $accessToken,
                    'signMethod' => 'SimpleKey',
                    'format' => 'json',
                    'version' => '1.0.0',
                    'timestamp' => (int) round(microtime(true) * 1000),
                    'timeZone' => $credentials['time_zone'],
                    'param' => $param,
                ];

                $response = Http::timeout(30)->post(
                    $credentials['base_url'] . '/' . ltrim($endpoint, '/'),
                    $body,
                );

                RateLimiter::hit($rateLimitKey, 60);

                Log::info('iMile API call', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'attempt' => $attempt + 1,
                ]);

                if ($response->serverError()) {
                    throw new \RuntimeException('iMile server error: ' . $response->status());
                }

                $json = $response->json();
                $code = (string) ($json['code'] ?? '');

                // Token expired/invalid mid-flight — refresh once and retry
                // immediately without consuming a full retry slot.
                if (! $tokenRetried && ImileErrorCode::isTokenError($code)) {
                    $tokenRetried = true;
                    $accessToken = $this->getAccessToken(forceRefresh: true);

                    if (! $accessToken) {
                        return $json;
                    }

                    continue;
                }

                return $json;
            } catch (\Exception $e) {
                $attempt++;

                Log::warning('iMile API error (attempt ' . $attempt . ')', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt > $maxRetries) {
                    Log::error('iMile API failed after ' . $attempt . ' attempts', [
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }

                usleep(500_000 * (2 ** ($attempt - 1)));
            }
        }

        return null;
    }
}
