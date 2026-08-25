<?php

namespace App\Services\Shipping\Drivers;

use App\Models\ConnectorSetting;
use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\DTOs\CancelResult;
use App\Services\Shipping\DTOs\ShipmentData;
use App\Services\Shipping\DTOs\ShipmentResult;
use App\Services\Shipping\DTOs\TrackingEvent;
use App\Services\Shipping\DTOs\TrackingResult;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * LogesTechs (brand: Navix) driver — https://apisv2.logestechs.com/api.
 *
 * Requests are plain JSON with a `company-id` header. Unlike {@see JntExpressDriver}
 * (signed digest) and {@see ImileDriver} (token grant), LogesTechs authenticates
 * write calls by putting the account email/password directly in the request body
 * on every request. LogesTechs confirmed (2026-08-25) this is the intended model
 * for server-to-server use; their `/auth/customer/login` endpoint exists but is
 * built for their mobile app and isn't used for API traffic. It is still the
 * cheapest way to *verify* credentials, so {@see testConnection()} uses it.
 *
 * Two identifiers, not one. LogesTechs confirmed the create response returns
 * `id` and `barcode` as separate fields ("please store both values separately"),
 * and they are not interchangeable:
 *
 *   - `barcode` (e.g. KSA100508919557) — the long tracking string, used by
 *     Get Package Status and the tracking endpoint. Stored on `tracking_number`.
 *   - `id` (e.g. 50840680) — a short numeric id, used by Cancel Shipment and
 *     Print AWB. Stored on `sorting_code` (unused by the other couriers).
 *
 * Both PDF documentation and a Postman collection were supplied, and they
 * disagree in several places (address scheme, Print AWB body key, receiver
 * field names). LogesTechs' guidance was to "follow the request structure and
 * fields used in the Postman example", and this driver does — with one
 * correction found by exercising the live API: `destinationAddress.addressLine1`
 * is rejected as a required field ("Invalid Parameter 'model.addressLine1' null")
 * even though their reference example omits it. See
 * docs/LogesTechs_Integration_Guide.md for the full source-by-source reconciliation.
 *
 * Addressing is the biggest departure from the other couriers. A destination
 * needs three things, all required despite their example omitting two of them:
 * a street line, a district ("village", looked up via {@see getVillages()}),
 * and a Saudi National Address short code. There is no city/province field to
 * fill — those are derived from the district. District names are *not* unique
 * (two distinct "Riyadh" entries exist), so `villageId` is sent whenever one
 * was resolved. `originAddress`, package dimensions, `vehicleTypeId`,
 * `parcelTypeId`, `declaredValue` and `toCollectFromReceiver` are all confirmed
 * unnecessary and deliberately not sent.
 *
 * Error messages come back in Arabic in at least some cases (a missing national
 * address returns "العنوان الوطني إجباري"), and are surfaced to the operator as-is
 * since LogesTechs publishes no error-code catalogue to map them against.
 */
class LogesTechsDriver implements CourierDriver
{
    protected ?array $credentials = null;

    /**
     * Why the last {@see makeRequest()} returned null, so callers can report
     * something more useful than a blanket "couldn't connect" — a 500 from
     * LogesTechs and an unreachable host need very different responses from
     * whoever is looking at the screen.
     */
    protected ?string $lastTransportError = null;

    public function getDriverName(): string
    {
        return 'logestechs';
    }

    public function createShipment(ShipmentData $data): ShipmentResult
    {
        $missing = [];
        if ($this->isBlank($data->receiver['name'] ?? null)) {
            $missing[] = 'name';
        }
        if ($this->isBlank($data->receiver['phone'] ?? null)) {
            $missing[] = 'phone';
        }

        // LogesTechs resolves the destination from a district ("village")
        // rather than free-text city, so a missing district can't be papered
        // over with the order's shipping_city the way it can for J&T/iMile.
        //
        // The *id* is what counts. Verified against the live API: sending only
        // a district name — even an exactly-matching one like "Riyadh" — is
        // rejected with "Invalid Parameter 'model.cityId' null", because the
        // name is never resolved server-side. Names aren't unique anyway (two
        // distinct "Riyadh" districts exist), so the name is sent purely for
        // their records and the id does the actual work.
        $village = trim((string) ($data->receiver['village'] ?? ''));
        $villageId = trim((string) ($data->receiver['villageId'] ?? ''));
        $nationalAddress = trim((string) ($data->receiver['shortAddress'] ?? ''));

        if ($villageId === '') {
            $missing[] = $village !== ''
                ? 'district selected from the lookup (a typed district name alone is not accepted)'
                : 'district';
        }

        // Both verified against the live API, and both contradict LogesTechs'
        // own reference example, which omits them:
        //   - no addressLine1 → "Invalid Parameter 'model.addressLine1' null"
        //   - no nationalAddress → "العنوان الوطني إجباري" ("National Address is required")
        // No format is enforced on the national address — any non-empty string
        // is accepted — so this only checks for presence.
        $addressLine1 = trim((string) ($data->receiver['address'] ?? ''));

        if ($addressLine1 === '') {
            $missing[] = 'street address';
        }

        if ($nationalAddress === '') {
            $missing[] = 'national address';
        }

        if (! empty($missing)) {
            return ShipmentResult::failure(
                errorMessage: 'Missing receiver ' . implode(', ', $missing)
                    . '. LogesTechs requires a street address, a destination district'
                    . ' and a Saudi National Address — please complete them before'
                    . ' creating the shipment.',
                errorCode: 'VALIDATION_ERROR',
            );
        }

        $credentials = $this->getCredentials();

        // Product titles are the most emoji-prone field in the whole payload,
        // and an all-emoji title would otherwise strip to an empty name.
        $items = array_values(array_map(fn ($item) => [
            'name' => $this->stripUnsupportedCharacters(
                mb_substr((string) ($item['name'] ?? ''), 0, 200),
                fallback: 'Item',
            ),
            'cod' => round((float) ($item['value'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1)), 2),
        ], $data->items));

        $pkg = array_filter([
            'receiverName' => $this->stripUnsupportedCharacters(
                mb_substr((string) $data->receiver['name'], 0, 200),
                fallback: 'Recipient',
            ),
            'receiverPhone' => (string) $data->receiver['phone'],
            'receiverPhone2' => (string) ($data->receiver['phone2'] ?? ''),
            'cod' => round($data->codAmount, 2),
            'notes' => $data->remark ? mb_substr($data->remark, 0, 500) : '',
            'supplierInvoice' => $data->txlogisticId,
            'senderName' => (string) ($data->sender['name'] ?? ''),
            'businessSenderName' => (string) ($data->sender['businessName'] ?? ''),
            'senderPhone' => (string) ($data->sender['phone'] ?? ''),
            'serviceType' => $this->resolveServiceType($data->serviceType),
            'shipmentType' => $data->codAmount > 0 ? 'COD' : 'REGULAR',
            'quantity' => max(1, $data->quantity),
            'description' => $this->stripUnsupportedCharacters(
                mb_substr($data->itemDescription ?: 'Package', 0, 500),
                fallback: 'Package',
            ),
            'integrationSource' => $credentials['integration_source'],
            // Not required by LogesTechs, but omitting them makes every parcel
            // show up in their portal weighing 0 kg with no dimensions, which
            // misrepresents the shipment to their staff and drivers. Verified
            // accepted and echoed back correctly. Dimensions are only sent when
            // the operator actually supplied them.
            'weight' => $data->weight > 0 ? round($data->weight, 2) : '',
            'length' => $data->length > 0 ? round($data->length, 2) : '',
            'width' => $data->width > 0 ? round($data->width, 2) : '',
            'height' => $data->height > 0 ? round($data->height, 2) : '',
        ], fn ($value) => $value !== '' && $value !== null);

        // Always send the COD-per-item breakdown when we have line items —
        // LogesTechs' working example includes it and their webhook echoes it
        // back enriched (sku/status/price), so it's clearly consumed, not decorative.
        if ($items !== []) {
            $pkg['packageItemsToDeliverList'] = $items;
        }

        $destination = array_filter([
            'addressLine1' => $addressLine1,
            'village' => $village,
            // Sent as an int when known: LogesTechs resolves cityId/regionId
            // from it, which a bare name can't do unambiguously.
            'villageId' => $villageId !== '' ? (int) $villageId : '',
            'nationalAddress' => $nationalAddress,
        ], fn ($value) => $value !== '' && $value !== null);

        $payload = [
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            // Everything user-facing goes through the 4-byte strip; see
            // stripUnsupportedCharacters(). Credentials are deliberately left
            // untouched so a password is never silently altered.
            'pkg' => $this->stripUnsupportedCharacters($pkg),
            'destinationAddress' => $this->stripUnsupportedCharacters($destination),
            'pkgUnitType' => 'METRIC',
        ];

        // maxRetries: 0 — creating a shipment is not idempotent. If LogesTechs
        // 500s *after* registering the package (their 5xx bodies are empty, so
        // we can't tell), retrying would book the same parcel two or three
        // times over. A failed create is safe to repeat by hand; a duplicated
        // one means a duplicate driver dispatch.
        $response = $this->makeRequest('POST', 'ship/request/by-email', $payload, maxRetries: 0);

        if ($response === null) {
            return ShipmentResult::failure($this->transportFailureMessage());
        }

        [$ok, $body] = $response;

        // LogesTechs never documented an error catalogue and every sample they
        // supplied is a happy-path 200, so success is inferred from the HTTP
        // status plus the presence of the two identifiers they promised.
        $barcode = $this->stringOrNull($body['barcode'] ?? null);
        $id = $this->stringOrNull($body['id'] ?? null);

        if ($ok && $barcode !== null) {
            return ShipmentResult::success(
                trackingNumber: $barcode,
                sortingCode: $id,
                txlogisticId: $data->txlogisticId,
                rawResponse: $body,
            );
        }

        Log::channel('logestechs')->error('LogesTechs shipment creation failed', [
            'supplier_invoice' => $data->txlogisticId,
            'http_ok' => $ok,
            'response' => $body,
        ]);

        return ShipmentResult::failure(
            errorMessage: $this->extractErrorMessage($body, 'LogesTechs rejected the shipment.'),
            errorCode: $this->stringOrNull($body['errorCode'] ?? $body['code'] ?? null),
            rawResponse: $body,
        );
    }

    /**
     * Fetch the current state of a shipment.
     *
     * Verified against the live API: `/guests/packages/status` and
     * `/guests/{companyId}/packages/tracking` return the *same* full package
     * object, so this uses the former — it needs no companyId in the path, and
     * it is the endpoint LogesTechs themselves point at for confirming a
     * webhook ("you can use 'Get package status' API, after receiving webhook
     * call, to double check"). Their PDF describes a much smaller
     * `{cost, cod, status, notes, id}` body; the real one is the whole package.
     *
     * Only one event is emitted, built from the top-level `status`. The object
     * does carry a `deliveryRoute[]` timeline, but its entries are display
     * labels from a different vocabulary ("Pending", "Canceled") than the
     * SCREAMING_SNAKE codes `status` and the webhook's `newStatus` share — so
     * it is mined for an accurate timestamp rather than for statuses, keeping
     * tracking_history in a single consistent vocabulary.
     *
     * This path is load-bearing for LogesTechs specifically: their webhooks
     * fire once and are never retried, so polling is the only way a missed
     * push is ever recovered. See {@see \App\Console\Commands\SyncShipmentTracking}.
     */
    public function trackShipment(string $trackingNumber): TrackingResult
    {
        $response = $this->makeRequest('GET', 'guests/packages/status', ['barcode' => $trackingNumber]);

        if ($response === null) {
            return TrackingResult::failure($this->transportFailureMessage());
        }

        [$ok, $body] = $response;

        $rawStatus = $this->stringOrNull($body['status'] ?? null);

        if (! $ok || $rawStatus === null) {
            return TrackingResult::failure(
                $this->extractErrorMessage($body, 'No tracking data returned for this shipment.'),
                $body,
            );
        }

        $description = $this->stringOrNull($body['notes'] ?? null) ?: $this->statusLabel($rawStatus);

        $postponedDate = $this->stringOrNull($body['postponedDeliveryDate'] ?? null);
        if ($postponedDate !== null) {
            $description .= ' (rescheduled for ' . $postponedDate . ')';
        }

        $event = new TrackingEvent(
            status: $this->normalizeStatus($rawStatus),
            description: $description,
            location: $this->stringOrNull($body['hubName'] ?? $body['nextDestination'] ?? null),
            timestamp: $this->resolveStatusTimestamp($body),
            rawStatus: $rawStatus,
            staffName: $this->stringOrNull($body['driverName'] ?? null),
            staffContact: $this->stringOrNull($body['driverPhone'] ?? null),
        );

        return TrackingResult::success($event->status, [$event], $body);
    }

    /**
     * Best available timestamp for the package's current status.
     *
     * Preferring a real courier timestamp over "now" matters because
     * tracking_history is deduplicated and sorted on this value — stamping
     * every poll with the current time would append a fresh entry each run and
     * let a polled event outrank a newer webhook push.
     */
    protected function resolveStatusTimestamp(array $body): string
    {
        $latestRouteDate = null;

        foreach ($body['deliveryRoute'] ?? [] as $leg) {
            if (! is_array($leg) || ($leg['isArrived'] ?? false) !== true) {
                continue;
            }

            $date = $this->stringOrNull($leg['deliveryDate'] ?? null);

            if ($date !== null && ($latestRouteDate === null || strcmp($date, $latestRouteDate) > 0)) {
                $latestRouteDate = $date;
            }
        }

        $timestamp = $latestRouteDate
            ?? $this->stringOrNull($body['lastStatusDate'] ?? null)
            ?? $this->stringOrNull($body['creationTime'] ?? null)
            ?? $this->stringOrNull($body['createdDate'] ?? null);

        return $this->normalizeTimestamp($timestamp);
    }

    /**
     * @param  string  $trackingNumber  LogesTechs' short numeric package id
     *                                  (stored on `shipments.sorting_code`),
     *                                  not the long barcode — see class docblock
     *                                  and {@see \App\Http\Controllers\Api\ShipmentController::cancel()}.
     */
    public function cancelShipment(string $trackingNumber, string $reason): CancelResult
    {
        $credentials = $this->getCredentials();

        $response = $this->makeRequest(
            'PUT',
            "guests/{$credentials['company_id']}/packages/{$trackingNumber}/cancel",
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ],
        );

        if ($response === null) {
            return CancelResult::failure($this->transportFailureMessage());
        }

        [$ok, $body] = $response;

        if ($ok) {
            return CancelResult::success($body);
        }

        return CancelResult::failure(
            errorMessage: $this->extractErrorMessage($body, 'LogesTechs rejected the cancellation.'),
            errorCode: $this->stringOrNull($body['errorCode'] ?? $body['code'] ?? null),
            rawResponse: $body,
        );
    }

    /**
     * Retrieve a printable AWB label PDF for one or more shipments.
     *
     * Takes LogesTechs' short numeric package ids (`shipments.sorting_code`),
     * not barcodes — the PDF documents this body key as `barcodes`, but the
     * working Postman request sends `ids`, and LogesTechs' guidance is to
     * follow the collection.
     *
     * @param  string[]  $packageIds
     * @return string|null  URL to the generated PDF, or null on failure
     */
    public function printLabels(array $packageIds): ?string
    {
        $packageIds = array_values(array_filter($packageIds, fn ($v) => $v !== null && $v !== ''));

        if ($packageIds === []) {
            return null;
        }

        $credentials = $this->getCredentials();

        $response = $this->makeRequest(
            'POST',
            "guests/{$credentials['company_id']}/packages/pdf",
            ['ids' => array_map('strval', $packageIds)],
        );

        if ($response === null) {
            return null;
        }

        [$ok, $body] = $response;

        if (! $ok) {
            Log::channel('logestechs')->warning('LogesTechs label generation failed', [
                'package_ids' => $packageIds,
                'response' => $body,
            ]);

            return null;
        }

        return $this->stringOrNull($body['url'] ?? null);
    }

    /**
     * District ("village") lookup used to populate the destination on
     * {@see createShipment()}. LogesTechs' PDF documents an `/addresses/cities`
     * endpoint instead, but it does not appear in their working collection and
     * the create payload takes a village, so this is the one we build against.
     *
     * @return array<int, array{id: int|string|null, name: string}>
     */
    public function getVillages(?string $search = null): array
    {
        $query = $search !== null && trim($search) !== '' ? ['search' => trim($search)] : [];

        $response = $this->makeRequest('GET', 'addresses/villages', $query);

        if ($response === null) {
            return [];
        }

        [$ok, $body] = $response;

        if (! $ok) {
            return [];
        }

        $rows = $body['data'] ?? $body;

        if (! is_array($rows)) {
            return [];
        }

        $villages = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Village names come back Arabic-first; the create payload expects
            // the same string LogesTechs indexes them under.
            $name = $this->stringOrNull($row['name'] ?? $row['arabicName'] ?? $row['englishName'] ?? null);

            if ($name === null) {
                continue;
            }

            $villages[] = [
                'id' => $row['id'] ?? null,
                'name' => $name,
                'english_name' => $this->stringOrNull($row['englishName'] ?? null),
                'city' => $this->stringOrNull($row['cityName'] ?? $row['city'] ?? null),
            ];
        }

        return $villages;
    }

    public function testConnection(): bool
    {
        try {
            $credentials = $this->getCredentials();
        } catch (\RuntimeException $e) {
            return false;
        }

        // The write endpoints take email/password in the body but only surface
        // a generic failure on bad credentials. The login endpoint validates
        // the same triple (email + password + companyId) and reports cleanly,
        // so it's used purely as a credential check — no token is kept.
        $response = $this->makeRequest('POST', 'auth/customer/login', [
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'companyId' => (int) $credentials['company_id'],
            'device' => [
                'operatingSystem' => 'Linux',
                'UUID' => 'ksadrop-portal',
            ],
        ]);

        if ($response === null) {
            return false;
        }

        [$ok] = $response;

        return $ok;
    }

    /**
     * Map a LogesTechs package status onto our internal {@see ShipmentStatus}.
     *
     * LogesTechs never supplied a recommended mapping, so this is our own —
     * mirroring how {@see ImileDriver::normalizeStatus()} reasons about the
     * ambiguous cases. Two judgement calls worth revisiting against real
     * traffic are marked inline.
     *
     * Note their own documentation lists SCANNED_BY_DRIVER_AND_IN_CAR twice
     * under conflicting labels ("Picked" and "Resolved Failure"); it is read
     * here as "Picked" — either reading lands in IN_TRANSIT anyway.
     */
    public function normalizeStatus(string $rawStatus): ShipmentStatus
    {
        return match (strtoupper(trim($rawStatus))) {
            'DRAFT',
            'PENDING_CUSTOMER_CARE_APPROVAL',
            'APPROVED_BY_CUSTOMER_CARE_AND_WAITING_FOR_DISPATCHER',
            'ASSIGNED_TO_DRIVER_AND_PENDING_APPROVAL',
            'REJECTED_BY_DRIVER_AND_PENDING_MANGEMENT',
            'ACCEPTED_BY_DRIVER_AND_PENDING_PICKUP' => ShipmentStatus::INFO_RECEIVED,

            'SCANNED_BY_DRIVER_AND_IN_CAR',
            'SCANNED_BY_HANDLER_AND_UNLOADED',
            'MOVED_TO_SHELF_AND_OUT_OF_HANDLER_CUSTODY',
            'TRANSFERRED_OUT' => ShipmentStatus::IN_TRANSIT,

            'OUT_FOR_DELIVERY' => ShipmentStatus::OUT_FOR_DELIVERY,

            'DELIVERED_TO_RECIPIENT',
            'COMPLETED',
            'SWAPPED',
            'BROUGHT' => ShipmentStatus::DELIVERED,

            // Non-terminal: the driver can still reattempt, so these must not
            // close the shipment out the way a terminal status would.
            'POSTPONED_DELIVERY',
            'FAILED' => ShipmentStatus::ATTEMPT_FAIL,

            'DELIVERED_TO_SENDER',
            'RETURNED_BY_RECIPIENT' => ShipmentStatus::RETURNED,

            // PARTIALLY_DELIVERED and EXPORTED_TO_THIRD_PARTY are judgement
            // calls — both leave the parcel in a state needing a human look,
            // which is what EXCEPTION is for. Revisit once real traffic shows
            // how often they occur and what follows them.
            'OPENED_ISSUE_AND_WAITING_FOR_MANAGEMENT',
            'DAMAGED',
            'LOST',
            'PARTIALLY_DELIVERED',
            'EXPORTED_TO_THIRD_PARTY' => ShipmentStatus::EXCEPTION,

            'CANCELLED' => ShipmentStatus::CANCELLED,

            default => ShipmentStatus::IN_TRANSIT,
        };
    }

    /**
     * Human-readable label for a LogesTechs status code, used when a payload
     * carries no `notes` of its own.
     */
    public function statusLabel(string $rawStatus): string
    {
        return match (strtoupper(trim($rawStatus))) {
            'PENDING_CUSTOMER_CARE_APPROVAL' => 'Submitted',
            'APPROVED_BY_CUSTOMER_CARE_AND_WAITING_FOR_DISPATCHER' => 'Ready for dispatching',
            'ASSIGNED_TO_DRIVER_AND_PENDING_APPROVAL' => 'Assigned to driver',
            'REJECTED_BY_DRIVER_AND_PENDING_MANGEMENT' => 'Rejected by driver',
            'ACCEPTED_BY_DRIVER_AND_PENDING_PICKUP' => 'Pending pickup',
            'SCANNED_BY_DRIVER_AND_IN_CAR' => 'Picked',
            'SCANNED_BY_HANDLER_AND_UNLOADED' => 'Received at sorting center',
            'MOVED_TO_SHELF_AND_OUT_OF_HANDLER_CUSTODY' => 'Sorted on shelves',
            'OPENED_ISSUE_AND_WAITING_FOR_MANAGEMENT' => 'Reported to management',
            'OUT_FOR_DELIVERY' => 'Out for delivery',
            'DELIVERED_TO_RECIPIENT' => 'Delivered',
            'DELIVERED_TO_SENDER' => 'Delivered to sender',
            'PARTIALLY_DELIVERED' => 'Partially delivered',
            'POSTPONED_DELIVERY' => 'Postponed delivery',
            'FAILED' => 'Delivery failed',
            'RETURNED_BY_RECIPIENT' => 'Returned by recipient',
            'TRANSFERRED_OUT' => 'Transferred out',
            'EXPORTED_TO_THIRD_PARTY' => 'Exported to a third party',
            'SWAPPED' => 'Swapped',
            'BROUGHT' => 'Brought',
            'DAMAGED' => 'Damaged',
            'LOST' => 'Lost',
            'DRAFT' => 'Draft',
            'COMPLETED' => 'Completed',
            'CANCELLED' => 'Cancelled',
            default => $rawStatus,
        };
    }

    /**
     * Service types enabled on the account, read from the `serviceTypes` claim
     * on a LogesTechs auth token. Undocumented — LogesTechs' written material
     * mentions only a numeric `serviceTypeId`, which the live API doesn't use.
     */
    public const SERVICE_TYPES = ['STANDARD', 'THREE_TO_FIVE_DAYS', 'EXPRESS', 'SEA', 'AIR'];

    /**
     * ShipmentData carries J&T's numeric service codes ('01'/'02') by default,
     * which mean nothing to LogesTechs — they take a plain string. Pass through
     * anything already in their vocabulary, otherwise fall back to STANDARD.
     */
    protected function resolveServiceType(string $serviceType): string
    {
        $value = strtoupper(trim($serviceType));

        return in_array($value, self::SERVICE_TYPES, true) ? $value : 'STANDARD';
    }

    /**
     * LogesTechs timestamps arrive as epoch milliseconds (their webhook `time`
     * field) or as an ISO-ish date string depending on endpoint. Normalise to
     * ISO-8601 so tracking_history stays lexicographically sortable, which is
     * what {@see \App\Models\Shipment::addTrackingEvents()} relies on.
     */
    public function normalizeTimestamp(mixed $value): string
    {
        if ($value === null || $value === '') {
            return now()->toIso8601String();
        }

        if (is_numeric($value)) {
            $milliseconds = (int) $value;

            try {
                return \Illuminate\Support\Carbon::createFromTimestampMs($milliseconds)->toIso8601String();
            } catch (\Throwable $e) {
                return now()->toIso8601String();
            }
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable $e) {
            return now()->toIso8601String();
        }
    }

    /**
     * LogesTechs documented no error catalogue and supplied no failure samples,
     * so pull whatever human-readable text the response happens to carry.
     */
    protected function extractErrorMessage(mixed $body, string $fallback): string
    {
        if (! is_array($body)) {
            return $fallback;
        }

        foreach (['error', 'message', 'msg', 'errorMessage', 'detail'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    /**
     * Remove 4-byte UTF-8 characters (emoji, and some rarer CJK) from every
     * string in an outbound payload.
     *
     * LogesTechs' `packages` table is not utf8mb4: sending an emoji makes their
     * insert blow up with `HTTP 500 {"error":"PreparedStatementCallback;
     * uncategorized SQLException for SQL [INSERT INTO packages ...]"}`, with no
     * indication of which field was at fault. Verified directly — the same
     * shipment succeeds once the emoji are removed, while accented Latin and
     * Arabic (both ≤3 bytes) are fine.
     *
     * This matters in practice because dropshipping product titles are full of
     * emoji ("🛸Latest 8K Video HD Camera Drone🚀"), and those titles flow
     * straight into `description` and the item list.
     *
     * A string that is *only* emoji would strip to empty, which LogesTechs
     * rejects for required fields, so callers pass a fallback for those.
     */
    protected function stripUnsupportedCharacters(mixed $value, string $fallback = ''): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->stripUnsupportedCharacters($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $stripped = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $value);

        // preg_replace returns null on malformed UTF-8 — keep the original
        // rather than silently blanking the field.
        if ($stripped === null) {
            return $value;
        }

        $stripped = trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);

        return $stripped !== '' ? $stripped : $fallback;
    }

    /**
     * Operator-facing explanation for a null {@see makeRequest()} result.
     * A LogesTechs-side 500 is not something retrying or checking the network
     * will fix, so it must not be reported as a connection problem.
     */
    protected function transportFailureMessage(): string
    {
        if ($this->lastTransportError !== null && str_contains($this->lastTransportError, 'server error')) {
            return 'LogesTechs returned a server error (' . $this->lastTransportError . '). '
                . 'The shipment was not created. This is a fault on their side — check '
                . 'storage/logs for the exact request that triggered it, and retry or '
                . 'contact LogesTechs support if it persists.';
        }

        return 'Could not reach the LogesTechs API'
            . ($this->lastTransportError !== null ? ' (' . $this->lastTransportError . ')' : '') . '.';
    }

    /**
     * Strip the account password before a request payload reaches the log —
     * LogesTechs' auth model puts it in the body of every write call.
     */
    protected function redactCredentials(array $payload): array
    {
        if (array_key_exists('password', $payload)) {
            $payload['password'] = '[redacted]';
        }

        return $payload;
    }

    protected function isBlank(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-';
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function getCredentials(): array
    {
        if ($this->credentials) {
            return $this->credentials;
        }

        // DB-backed ConnectorSetting (saved from Apps → LogesTechs Settings) is
        // the source of truth; config/services.php (env-driven) fills the gaps
        // so the integration works before anything is configured in the UI —
        // same precedence as the iMile driver.
        $this->credentials = [
            'company_id' => trim((string) (ConnectorSetting::getForConnector('logestechs', 'company_id') ?: config('services.logestechs.company_id'))),
            'email' => trim((string) (ConnectorSetting::getForConnector('logestechs', 'email') ?: config('services.logestechs.email'))),
            'password' => (string) (ConnectorSetting::getForConnector('logestechs', 'password') ?: config('services.logestechs.password')),
            'base_url' => rtrim(trim((string) (ConnectorSetting::getForConnector('logestechs', 'base_url') ?: config('services.logestechs.base_url', 'https://apisv2.logestechs.com/api'))), '/'),
            // Shown as "Package Source" in LogesTechs' portal. Their UI renders
            // it as a translation key (INTEGRATION_SOURCE.<value>), so a value
            // they haven't registered displays raw — ask LogesTechs to add ours
            // to their list if you want a friendly label there.
            'integration_source' => trim((string) (ConnectorSetting::getForConnector('logestechs', 'integration_source') ?: config('services.logestechs.integration_source', 'ksadrop_portal'))),
        ];

        if (! $this->credentials['company_id'] || ! $this->credentials['email'] || ! $this->credentials['password']) {
            throw new \RuntimeException('LogesTechs credentials (company ID / email / password) are not configured.');
        }

        return $this->credentials;
    }

    /**
     * @param  'GET'|'POST'|'PUT'  $method
     * @return array{0: bool, 1: array}|null  [http-ok, decoded body], or null when unreachable
     */
    protected function makeRequest(string $method, string $endpoint, array $payload = [], int $maxRetries = 2): ?array
    {
        $rateLimitKey = 'logestechs:api';
        $this->lastTransportError = null;

        // LogesTechs publishes no rate limit; this mirrors the conservative
        // ceiling already applied to iMile rather than assuming none exists.
        if (RateLimiter::tooManyAttempts($rateLimitKey, 30)) {
            Log::channel('logestechs')->warning('LogesTechs API rate limit reached');
            $this->lastTransportError = 'local rate limit reached — too many requests in the last minute';

            return null;
        }

        $credentials = $this->getCredentials();
        $url = $credentials['base_url'] . '/' . ltrim($endpoint, '/');

        $attempt = 0;

        while ($attempt <= $maxRetries) {
            try {
                $request = Http::timeout(30)
                    ->acceptJson()
                    ->withHeaders(['company-id' => $credentials['company_id']]);

                $response = match ($method) {
                    'GET' => $request->get($url, $payload),
                    'PUT' => $request->put($url, $payload),
                    default => $request->post($url, $payload),
                };

                RateLimiter::hit($rateLimitKey, 60);

                Log::channel('logestechs')->info('LogesTechs API call', [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'status' => $response->status(),
                    'attempt' => $attempt + 1,
                ]);

                if ($response->serverError()) {
                    // LogesTechs publishes no error catalogue and a 5xx body is
                    // usually empty, so the request we sent is the only clue as
                    // to what upset them — log it (minus the credentials) or
                    // there is nothing to debug from.
                    Log::channel('logestechs')->warning('LogesTechs server error — request payload', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'body' => mb_substr($response->body(), 0, 1000),
                        'payload' => $this->redactCredentials($payload),
                    ]);

                    throw new \RuntimeException('LogesTechs server error: ' . $response->status());
                }

                $body = $response->json();

                return [$response->successful(), is_array($body) ? $body : []];
            } catch (\Exception $e) {
                $attempt++;
                $this->lastTransportError = $e->getMessage();

                Log::channel('logestechs')->warning('LogesTechs API error (attempt ' . $attempt . ')', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt > $maxRetries) {
                    Log::channel('logestechs')->error('LogesTechs API failed after ' . $attempt . ' attempts', [
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
