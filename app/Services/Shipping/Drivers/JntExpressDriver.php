<?php

namespace App\Services\Shipping\Drivers;

use App\Models\ConnectorSetting;
use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\DTOs\CancelResult;
use App\Services\Shipping\DTOs\ShipmentData;
use App\Services\Shipping\DTOs\ShipmentResult;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\DTOs\TrackingResult;
use App\Services\Shipping\Enums\JntErrorCode;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class JntExpressDriver implements CourierDriver
{
    protected ?array $credentials = null;

    public function getDriverName(): string
    {
        return 'jnt_express';
    }

    public function createShipment(ShipmentData $data): ShipmentResult
    {
        $credentials = $this->getCredentials();

        // J&T validates "prov" against its own canonical list of KSA regions and
        // rejects empty/unknown values (error 1450033315). Normalise both parties'
        // provinces and fail fast with a clear message rather than sending a request
        // that is guaranteed to be rejected with a cryptic error.
        $senderProvince = $this->normalizeProvince($data->sender['province'] ?? '');
        $receiverProvince = $this->normalizeProvince($data->receiver['province'] ?? '');

        $missing = [];
        if ($receiverProvince === '') {
            $missing[] = 'province';
        }
        if ($this->isBlank($data->receiver['city'] ?? null)) {
            $missing[] = 'city';
        }
        if ($this->isBlank($data->receiver['address'] ?? null)) {
            $missing[] = 'address';
        }

        if (! empty($missing)) {
            return ShipmentResult::failure(
                errorMessage: 'Missing or invalid receiver ' . implode(', ', $missing)
                    . '. Please complete the receiver address (with a valid KSA province) before creating the shipment.',
                errorCode: 'VALIDATION_ERROR',
            );
        }

        $bizContent = [
            'customerCode' => $credentials['customer_code'],
            'digest'       => $this->buildInnerDigest(),
            'txlogisticId' => $data->txlogisticId,
            'orderType' => '1',
            'serviceType' => $data->serviceType,
            'deliveryType' => '03',
            'operateType' => '1',
            'expressType' => 'EZKSA',
            'goodsType' => 'ITN1',
            'weight' => (string) $data->weight,
            'itemName' => $data->itemDescription,
            'totalQuantity' => (string) $data->quantity,
            'sender' => [
                'name' => $data->sender['name'],
                'phone' => $data->sender['phone'],
                'mobile' => $data->sender['phone'],
                'countryCode' => $this->normalizeCountryCode($data->sender['countryCode'] ?? null),
                'prov' => $senderProvince,
                'city' => $data->sender['city'],
                'area' => $data->sender['area'] ?? '',
                'address' => $data->sender['address'],
                'postCode' => $data->sender['postCode'] ?? '',
            ],
            'receiver' => [
                'name' => $data->receiver['name'],
                'phone' => $data->receiver['phone'],
                'mobile' => $data->receiver['phone'],
                'countryCode' => $this->normalizeCountryCode($data->receiver['countryCode'] ?? null),
                'prov' => $receiverProvince,
                'city' => $data->receiver['city'],
                'area' => $data->receiver['area'] ?? '',
                'address' => $data->receiver['address'],
                'postCode' => $data->receiver['postCode'] ?? '',
            ],
        ];

        if ($data->length) {
            $bizContent['length'] = (string) $data->length;
        }
        if ($data->width) {
            $bizContent['width'] = (string) $data->width;
        }
        if ($data->height) {
            $bizContent['height'] = (string) $data->height;
        }

        $response = $this->makeRequest('/webopenplatformapi/api/order/addOrder', $bizContent);

        if (! $response) {
            return ShipmentResult::failure('Failed to connect to J&T Express API');
        }

        // J&T is inconsistent about whether `code` is a string or an integer, so
        // always compare as a string. Success is code "1".
        $code = (string) ($response['code'] ?? '');

        if ($code === '1') {
            return ShipmentResult::success(
                trackingNumber: $response['data']['billCode'] ?? '',
                sortingCode: $response['data']['sortingCode'] ?? null,
                txlogisticId: $data->txlogisticId,
                rawResponse: $response,
            );
        }

        Log::error('J&T Express shipment creation failed', [
            'txlogistic_id' => $data->txlogisticId,
            'response_code' => $response['code'] ?? null,
            'response_msg' => $response['msg'] ?? null,
            'full_response' => $response,
        ]);

        return ShipmentResult::failure(
            errorMessage: $this->friendlyError($code, $response['msg'] ?? null),
            errorCode: $code !== '' ? $code : null,
            rawResponse: $response,
        );
    }

    public function trackShipment(string $trackingNumber): TrackingResult
    {
        $bizContent = [
            'billCodes' => $trackingNumber,
            'lang' => 'en',
        ];

        $response = $this->makeRequest('/webopenplatformapi/api/logistics/trace', $bizContent);

        if (! $response) {
            return TrackingResult::failure('Failed to connect to J&T Express API');
        }

        if ((string) ($response['code'] ?? '') !== '1') {
            return TrackingResult::failure(
                $response['msg'] ?? 'Failed to get tracking info',
                $response,
            );
        }

        $details = $response['data'][0]['details'] ?? [];
        $events = [];

        foreach ($details as $detail) {
            $location = $detail['scanNetworkCity'] ?? $detail['scanNetworkProvince'] ?? null;
            $events[] = new TrackingEvent(
                status: $this->normalizeStatus($detail['scanType'] ?? ''),
                description: $detail['desc'] ?? '',
                location: $location,
                timestamp: $detail['scanTime'] ?? '',
                rawStatus: $detail['scanType'] ?? '',
            );
        }

        $currentStatus = ! empty($events)
            ? $events[0]->status
            : ShipmentStatus::INFO_RECEIVED;

        return TrackingResult::success($currentStatus, $events, $response);
    }

    public function cancelShipment(string $trackingNumber, string $reason): CancelResult
    {
        $credentials = $this->getCredentials();

        $bizContent = [
            'customerCode' => $credentials['customer_code'],
            'digest'       => $this->buildInnerDigest(),
            'txlogisticId' => $trackingNumber,
            'orderType'    => '2',
            'reason'       => $reason,
        ];

        $response = $this->makeRequest('/webopenplatformapi/api/order/cancelOrder', $bizContent);

        if (! $response) {
            return CancelResult::failure('Failed to connect to J&T Express API');
        }

        $code = (string) ($response['code'] ?? '');

        if ($code === '1') {
            return CancelResult::success($response);
        }

        return CancelResult::failure(
            errorMessage: $this->friendlyError($code, $response['msg'] ?? null),
            errorCode: $code !== '' ? $code : null,
            rawResponse: $response,
        );
    }

    public function testConnection(): bool
    {
        try {
            $this->getCredentials();

            $bizContent = [
                'billCodes' => ['TEST000000000'],
                'lang' => 'en',
            ];

            $response = $this->makeRequest('/webopenplatformapi/api/logistics/trace', $bizContent);

            // If we get any response (even "not found"), credentials are valid.
            // A signature failure means the credentials themselves are wrong.
            return $response !== null
                && (string) ($response['code'] ?? '') !== JntErrorCode::HEADER_SIGNATURE_INVALID->value;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function normalizeStatus(string $rawStatus): ShipmentStatus
    {
        return match (strtolower(trim($rawStatus))) {
            // Uppercase codes (some API versions / webhooks)
            'got', 'pick_up', 'collected', 'pickup scan' => ShipmentStatus::INFO_RECEIVED,
            'departure', 'send_scan', 'in_transit', 'sending scan',
            'arrival', 'arrival_scan', 'station arrival' => ShipmentStatus::IN_TRANSIT,
            'delivering', 'out_for_delivery', 'delivery scan' => ShipmentStatus::OUT_FOR_DELIVERY,
            'signed', 'delivered', 'pod', 'sign scan' => ShipmentStatus::DELIVERED,
            'failed', 'attempt_fail', 'undelivered' => ShipmentStatus::ATTEMPT_FAIL,
            'return', 'returned' => ShipmentStatus::RETURNED,
            'cancel', 'cancelled' => ShipmentStatus::CANCELLED,
            'lost', 'damaged', 'exception' => ShipmentStatus::EXCEPTION,
            default => ShipmentStatus::IN_TRANSIT,
        };
    }

    /**
     * Turn a J&T error code/message into a clear, actionable English message that
     * always carries the code for traceability. J&T sometimes returns non-English
     * (e.g. Chinese) messages, so known codes are mapped via {@see JntErrorCode}
     * and the raw message is used as a fallback.
     */
    protected function friendlyError(string $code, ?string $msg): string
    {
        return JntErrorCode::resolve($code, $msg);
    }

    /**
     * The 13 canonical KSA regions accepted by J&T Express as "prov".
     *
     * @return array<int, string>
     */
    public static function provinces(): array
    {
        return [
            'Riyadh',
            'Makkah',
            'Madinah',
            'Eastern Province',
            'Qassim',
            'Asir',
            'Tabuk',
            'Hail',
            'Northern Borders',
            'Jazan',
            'Najran',
            'Al Bahah',
            'Al Jawf',
        ];
    }

    /**
     * Map a free-text province (English/Arabic variants, or a city name that maps
     * to a region) onto J&T's canonical region name. Returns '' when it cannot be
     * resolved, so callers can reject the shipment before hitting the API.
     */
    public function normalizeProvince(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return '';
        }

        // Collapse punctuation/whitespace; keep latin + arabic letters and digits.
        $key = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $key = trim(preg_replace('/\s+/', ' ', mb_strtolower($key)));

        $map = [
            // Riyadh
            'riyadh' => 'Riyadh', 'al riyadh' => 'Riyadh', 'ar riyadh' => 'Riyadh', 'الرياض' => 'Riyadh',
            // Makkah
            'makkah' => 'Makkah', 'mecca' => 'Makkah', 'makkah al mukarramah' => 'Makkah',
            'jeddah' => 'Makkah', 'taif' => 'Makkah', 'مكة المكرمة' => 'Makkah', 'مكة' => 'Makkah',
            // Madinah
            'madinah' => 'Madinah', 'medina' => 'Madinah', 'al madinah' => 'Madinah',
            'al madinah al munawwarah' => 'Madinah', 'المدينة المنورة' => 'Madinah', 'المدينة' => 'Madinah',
            // Eastern Province
            'eastern province' => 'Eastern Province', 'eastern' => 'Eastern Province',
            'eastern region' => 'Eastern Province', 'ash sharqiyah' => 'Eastern Province',
            'al sharqiyah' => 'Eastern Province', 'dammam' => 'Eastern Province', 'khobar' => 'Eastern Province',
            'al khobar' => 'Eastern Province', 'dhahran' => 'Eastern Province',
            'المنطقة الشرقية' => 'Eastern Province', 'الشرقية' => 'Eastern Province',
            // Qassim
            'qassim' => 'Qassim', 'al qassim' => 'Qassim', 'qaseem' => 'Qassim',
            'buraidah' => 'Qassim', 'القصيم' => 'Qassim',
            // Asir
            'asir' => 'Asir', 'aseer' => 'Asir', 'abha' => 'Asir', 'عسير' => 'Asir',
            // Tabuk
            'tabuk' => 'Tabuk', 'tabouk' => 'Tabuk', 'تبوك' => 'Tabuk',
            // Hail
            'hail' => 'Hail', 'ha il' => 'Hail', 'حائل' => 'Hail',
            // Northern Borders
            'northern borders' => 'Northern Borders', 'northern border' => 'Northern Borders',
            'northern borders region' => 'Northern Borders', 'al hudud ash shamaliyah' => 'Northern Borders',
            'arar' => 'Northern Borders', 'الحدود الشمالية' => 'Northern Borders',
            // Jazan
            'jazan' => 'Jazan', 'jizan' => 'Jazan', 'gizan' => 'Jazan', 'جازان' => 'Jazan', 'جيزان' => 'Jazan',
            // Najran
            'najran' => 'Najran', 'نجران' => 'Najran',
            // Al Bahah
            'al bahah' => 'Al Bahah', 'al baha' => 'Al Bahah', 'bahah' => 'Al Bahah',
            'baha' => 'Al Bahah', 'الباحة' => 'Al Bahah',
            // Al Jawf
            'al jawf' => 'Al Jawf', 'al jouf' => 'Al Jawf', 'jawf' => 'Al Jawf',
            'sakaka' => 'Al Jawf', 'الجوف' => 'Al Jawf',
        ];

        // Also try a variant with trailing qualifier words removed, so values like
        // "Riyadh Region" or "Makkah Province" still resolve.
        $stripped = trim(preg_replace('/\s+/', ' ',
            preg_replace('/\b(region|province|governorate|emirate|district|of)\b/u', ' ', $key)
        ));

        foreach ([$key, $stripped] as $candidate) {
            if ($candidate !== '' && isset($map[$candidate])) {
                return $map[$candidate];
            }

            // Already a canonical value (case-insensitive)?
            foreach (self::provinces() as $province) {
                if (mb_strtolower($province) === $candidate) {
                    return $province;
                }
            }
        }

        // Unknown — let the caller decide. Returning '' forces a clear validation error.
        return '';
    }

    protected function isBlank(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-';
    }

    /**
     * J&T-SA's address tree is keyed on "KSA", not the ISO-3166 "SA"/"SAU".
     * Sending the ISO code makes prov/city lookups miss → 999002000 "Data not found".
     * Map any Saudi representation to "KSA"; pass through anything genuinely foreign.
     */
    protected function normalizeCountryCode(?string $value): string
    {
        $value = strtoupper(trim((string) $value));

        $saudi = ['', 'SA', 'SAU', 'KSA', 'SAUDI ARABIA', 'SAUDIARABIA', 'KINGDOM OF SAUDI ARABIA'];

        return in_array($value, $saudi, true) ? 'KSA' : $value;
    }

    protected function getCredentials(): array
    {
        if ($this->credentials) {
            return $this->credentials;
        }

        // DB-backed ConnectorSetting (saved from Apps → J&T Settings) is the source
        // of truth. When a value is missing we fall back to config/services.php
        // (env-driven) so the integration keeps working before anything is
        // configured in the UI.
        $this->credentials = [
            'api_account'       => ConnectorSetting::getForConnector('jnt_express', 'api_account') ?: config('services.jnt_express.api_account'),
            'private_key'       => ConnectorSetting::getForConnector('jnt_express', 'private_key') ?: config('services.jnt_express.private_key'),
            'customer_code'     => ConnectorSetting::getForConnector('jnt_express', 'customer_code') ?: config('services.jnt_express.customer_code'),
            'customer_password' => ConnectorSetting::getForConnector('jnt_express', 'customer_password') ?: config('services.jnt_express.customer_password'),
            'sandbox_uuid'      => ConnectorSetting::getForConnector('jnt_express', 'sandbox_uuid') ?: config('services.jnt_express.sandbox_uuid'),
            'base_url'          => ConnectorSetting::getForConnector('jnt_express', 'base_url') ?: config('services.jnt_express.base_url', 'https://openapi.jtjms-sa.com'),
        ];

        if (! $this->credentials['api_account'] || ! $this->credentials['private_key']) {
            throw new \RuntimeException('J&T Express credentials are not configured.');
        }

        if (! $this->credentials['customer_code'] || ! $this->credentials['customer_password']) {
            throw new \RuntimeException('J&T Express customer code / password are not configured.');
        }

        return $this->credentials;
    }

    /**
     * Inner bizContent digest — required by J&T on every order request.
     * Formula (from official docs):
     *   ciphertext   = strtoupper( md5( plainPassword + "jadada236t2" ) )
     *   inner_digest = base64( md5( customerCode + ciphertext + privateKey ) )
     */
    protected function buildInnerDigest(): string
    {
        $creds      = $this->getCredentials();
        $ciphertext = strtoupper(md5($creds['customer_password'] . 'jadada236t2'));

        return base64_encode(md5($creds['customer_code'] . $ciphertext . $creds['private_key'], true));
    }

    protected function buildDigest(string $bizContent): string
    {
        $credentials = $this->getCredentials();

        // J&T header signature: base64(md5(bizContent + privateKey))
        return base64_encode(md5($bizContent . $credentials['private_key'], true));
    }

    protected function buildEndpointUrl(string $endpoint): string
    {
        $creds = $this->getCredentials();
        $url   = $creds['base_url'] . $endpoint;

        // Sandbox environments route through a per-account UUID.
        if ($creds['sandbox_uuid']) {
            $url .= '?uuid=' . $creds['sandbox_uuid'];
        }

        return $url;
    }

    protected function makeRequest(string $endpoint, array $data): ?array
    {
        $rateLimitKey = 'jnt_express:api';

        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            Log::warning('J&T Express API rate limit reached');
            return null;
        }

        try {
            $credentials = $this->getCredentials();
            // The exact bizContent string posted is what the header digest is computed over.
            $bizContent = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $digest = $this->buildDigest($bizContent);
            // J&T expects a millisecond timestamp (13 digits).
            $timestamp = (string) round(microtime(true) * 1000);

            Log::debug('J&T Express request details', [
                'endpoint' => $endpoint,
                'bizContent' => $bizContent,
                'computed_digest' => $digest,
                'timestamp' => $timestamp,
                'api_account' => $credentials['api_account'],
            ]);

            $response = Http::asForm()
                ->withHeaders([
                    'apiAccount' => $credentials['api_account'],
                    'digest' => $digest,
                    'timestamp' => $timestamp,
                ])
                ->timeout(30)
                ->post($this->buildEndpointUrl($endpoint), [
                    'bizContent' => $bizContent,
                ]);

            RateLimiter::hit($rateLimitKey, 60);

            Log::info('J&T Express API call', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('J&T Express API error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
