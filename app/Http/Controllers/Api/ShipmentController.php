<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Warehouse;
use App\Services\Shipping\CourierManager;
use App\Services\Shipping\Drivers\LogesTechsDriver;
use App\Services\Shipping\DTOs\ShipmentData;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    protected CourierManager $courierManager;

    public function __construct(CourierManager $courierManager)
    {
        $this->courierManager = $courierManager;
    }

    public function index(Request $request)
    {
        $query = Shipment::with('order:id,order_number,customer_name')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('courier')) {
            $query->byCourier($request->input('courier'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('txlogistic_id', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($q) use ($search) {
                        $q->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        $shipments = $query->paginate($request->input('per_page', 20));

        return response()->json($shipments);
    }

    public function show(Shipment $shipment)
    {
        $shipment->load('order.items');

        return response()->json(['shipment' => $shipment]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id'               => 'required|exists:orders,id',
            'warehouse_id'           => 'required|exists:warehouses,id',
            'courier'                => 'nullable|in:jnt_express,imile,logestechs',
            'weight'                 => 'nullable|numeric|min:0.1',
            'length'                 => 'nullable|numeric|min:0',
            'width'                  => 'nullable|numeric|min:0',
            'height'                 => 'nullable|numeric|min:0',
            // '01'/'02' are J&T's codes (also used as iMile's default);
            // LogesTechs takes a plain string instead.
            'service_type'           => 'nullable|in:01,02,STANDARD,EXPRESS',
            'goods_type'             => 'nullable|in:ITN1,ITN2,ITN3,ITN4,ITN5,ITN6,ITN7',
            'remark'                 => 'nullable|string|max:200',
            'receiver_name'          => 'nullable|string|max:255',
            'receiver_phone'         => 'nullable|string|max:20',
            'receiver_phone2'        => 'nullable|string|max:20',
            'receiver_province'      => 'nullable|string|max:255',
            'receiver_city'          => 'nullable|string|max:255',
            'receiver_area'          => 'nullable|string|max:255',
            'receiver_address'       => 'nullable|string|max:500',
            'receiver_post_code'     => 'nullable|string|max:20',
            'receiver_short_address' => 'nullable|string|max:50',
            // LogesTechs only — the destination district, resolved against
            // their /addresses/villages lookup rather than free-text city.
            // District names aren't unique, so the id is what actually
            // disambiguates; the name is kept for display and as a fallback.
            'receiver_village'       => 'nullable|string|max:255',
            'receiver_village_id'    => 'nullable|integer',
        ]);

        $courier = $validated['courier'] ?? 'jnt_express';
        $validated['service_type'] = $this->defaultServiceType($courier, $validated['service_type'] ?? null);

        $order = Order::with('items')->findOrFail($validated['order_id']);

        // Check for existing active shipment
        $existingShipment = $order->shipments()->active()->first();
        if ($existingShipment) {
            return response()->json([
                'message' => 'This order already has an active shipment.',
                'shipment' => $existingShipment,
            ], 422);
        }

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $shipmentData = ShipmentData::fromOrder($order, $warehouse, $validated);

        $driver = $this->courierManager->driver($courier);
        $result = $driver->createShipment($shipmentData);

        if (! $result->success) {
            return response()->json([
                'message' => "Failed to create shipment with {$this->courierLabel($courier)}.",
                'error' => $result->errorMessage,
                'error_code' => $result->errorCode,
            ], 422);
        }

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'courier' => $courier,
            'tracking_number' => $result->trackingNumber,
            'txlogistic_id' => $shipmentData->txlogisticId,
            'sorting_code' => $result->sortingCode,
            'status' => ShipmentStatus::INFO_RECEIVED->value,
            'api_response' => $result->rawResponse,
            'weight' => $shipmentData->weight,
            'length' => $shipmentData->length,
            'width' => $shipmentData->width,
            'height' => $shipmentData->height,
            'service_type' => $shipmentData->serviceType,
            'shipped_at' => now(),
        ]);

        return response()->json([
            'message' => 'Shipment created successfully.',
            'shipment' => $shipment,
        ], 201);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'order_ids'    => 'required|array|min:1',
            'order_ids.*'  => 'exists:orders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'courier'      => 'nullable|in:jnt_express,imile,logestechs',
            'weight'       => 'nullable|numeric|min:0.1',
            'service_type' => 'nullable|in:01,02,STANDARD,EXPRESS',
            'goods_type'   => 'nullable|in:ITN1,ITN2,ITN3,ITN4,ITN5,ITN6,ITN7',
            'remark'       => 'nullable|string|max:200',
        ]);

        $courier = $validated['courier'] ?? 'jnt_express';
        $validated['service_type'] = $this->defaultServiceType($courier, $validated['service_type'] ?? null);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $driver = $this->courierManager->driver($courier);

        // LogesTechs rejects a shipment without a destination district and a
        // Saudi National Address (see LogesTechsDriver::createShipment()), and
        // neither has an order field to pre-fill it from — which is what made
        // bulk create unusable for this courier. Per product decision both are
        // assigned automatically here: the district is drawn at random from
        // LogesTechs' own lookup, the national address is a generated code.
        // The pool is fetched once per batch rather than once per order.
        $districtPool = [];

        if ($courier === 'logestechs' && $driver instanceof LogesTechsDriver) {
            // A district name alone is never resolved server-side, so entries
            // without an id are useless to us — drop them here rather than
            // letting every order fail one by one at the courier.
            try {
                $districtPool = array_values(array_filter(
                    $driver->getVillages(),
                    fn ($village) => ! empty($village['id']) && ! empty($village['name']),
                ));
            } catch (\RuntimeException $e) {
                // Credentials not configured yet — same clean 422 the district
                // lookup endpoint returns, rather than a 500.
                return response()->json([
                    'message' => 'LogesTechs is not configured yet. Add your credentials in Apps → LogesTechs Settings.',
                    'created' => [],
                    'failed'  => [],
                ], 422);
            }

            if ($districtPool === []) {
                return response()->json([
                    'message' => 'Could not load districts from LogesTechs, so no shipments were created.',
                    'created' => [],
                    'failed'  => [],
                ], 422);
            }
        }

        $created = [];
        $failed = [];

        foreach ($validated['order_ids'] as $orderId) {
            $order = Order::with('items')->find($orderId);

            if (! $order) {
                $failed[] = ['order_id' => $orderId, 'error' => 'Order not found.'];
                continue;
            }

            if ($order->shipments()->active()->exists()) {
                $failed[] = ['order_id' => $orderId, 'error' => 'Already has an active shipment.'];
                continue;
            }

            $options = array_filter([
                'weight'       => $validated['weight'] ?? null,
                'service_type' => $validated['service_type'] ?? null,
                'goods_type'   => $validated['goods_type'] ?? null,
                'remark'       => $validated['remark'] ?? null,
            ]);

            if ($districtPool !== []) {
                // Picked per order, not per batch, so a bulk run spreads across
                // districts the way real orders would.
                $district = $districtPool[array_rand($districtPool)];

                $options['receiver_village']       = $district['name'];
                $options['receiver_village_id']    = $district['id'];
                $options['receiver_short_address'] = $this->randomNationalAddress();
            }

            $shipmentData = ShipmentData::fromOrder($order, $warehouse, $options);
            $result = $driver->createShipment($shipmentData);

            if (! $result->success) {
                $failed[] = ['order_id' => $orderId, 'error' => $result->errorMessage];
                continue;
            }

            $shipment = Shipment::create([
                'order_id' => $order->id,
                'courier' => $courier,
                'tracking_number' => $result->trackingNumber,
                'txlogistic_id' => $shipmentData->txlogisticId,
                'sorting_code' => $result->sortingCode,
                'status' => ShipmentStatus::INFO_RECEIVED->value,
                'api_response' => $result->rawResponse,
                'weight' => $shipmentData->weight,
                'service_type' => $shipmentData->serviceType,
                'shipped_at' => now(),
            ]);

            $created[] = $shipment;
        }

        return response()->json([
            'message' => count($created) . ' shipment(s) created, ' . count($failed) . ' failed.',
            'created' => $created,
            'failed' => $failed,
        ]);
    }

    public function update(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'warehouse_id'           => 'required|exists:warehouses,id',
            'weight'                 => 'nullable|numeric|min:0.1',
            'length'                 => 'nullable|numeric|min:0',
            'width'                  => 'nullable|numeric|min:0',
            'height'                 => 'nullable|numeric|min:0',
            'service_type'           => 'nullable|in:01,02',
            'goods_type'             => 'nullable|in:ITN1,ITN2,ITN3,ITN4,ITN5,ITN6,ITN7',
            'remark'                 => 'nullable|string|max:200',
            'receiver_name'          => 'nullable|string|max:255',
            'receiver_phone'         => 'nullable|string|max:20',
            'receiver_province'      => 'nullable|string|max:255',
            'receiver_city'          => 'nullable|string|max:255',
            'receiver_area'          => 'nullable|string|max:255',
            'receiver_address'       => 'nullable|string|max:500',
            'receiver_post_code'     => 'nullable|string|max:20',
            'receiver_short_address' => 'nullable|string|max:50',
        ]);

        if ($shipment->status_enum->isTerminal()) {
            return response()->json(['message' => 'Cannot modify a shipment with status: ' . $shipment->status_label], 422);
        }

        $order     = $shipment->order()->with('items')->firstOrFail();
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $options = array_merge($validated, [
            'operate_type'  => 2,
            'bill_code'     => $shipment->tracking_number,
            'txlogistic_id' => $shipment->txlogistic_id,
        ]);

        $shipmentData = ShipmentData::fromOrder($order, $warehouse, $options);

        $driver = $this->courierManager->driver($shipment->courier);
        $result = $driver->createShipment($shipmentData);

        if (! $result->success) {
            return response()->json([
                'message'    => "Failed to modify shipment at {$this->courierLabel($shipment->courier)}.",
                'error'      => $result->errorMessage,
                'error_code' => $result->errorCode,
            ], 422);
        }

        $shipment->update(array_filter([
            'tracking_number' => $result->trackingNumber ?: $shipment->tracking_number,
            'sorting_code'    => $result->sortingCode,
            'weight'          => $shipmentData->weight,
            'length'          => $shipmentData->length,
            'width'           => $shipmentData->width,
            'height'          => $shipmentData->height,
            'service_type'    => $shipmentData->serviceType,
            'api_response'    => $result->rawResponse,
        ], fn ($v) => $v !== null));

        return response()->json([
            'message'  => 'Shipment updated successfully.',
            'shipment' => $shipment->fresh(),
        ]);
    }

    public function track(Shipment $shipment)
    {
        if (! $shipment->tracking_number) {
            return response()->json(['message' => 'No tracking number available.'], 422);
        }

        $driver = $this->courierManager->driver($shipment->courier);
        $result = $driver->trackShipment($shipment->tracking_number);

        if (! $result->success) {
            return response()->json([
                'message' => 'Failed to fetch tracking info.',
                'error' => $result->errorMessage,
            ], 422);
        }

        // An on-demand refresh may still return events out of order — only
        // move the coarse status forward (see Shipment::resolveTrackingStatus()).
        [$appliedStatus, $extra] = $shipment->resolveTrackingStatus($result->currentStatus);

        $shipment->update([
            'status' => $appliedStatus->value,
            'tracking_history' => array_map(fn ($e) => $e->toArray(), $result->events),
            ...$extra,
        ]);

        if ($result->currentStatus === ShipmentStatus::DELIVERED) {
            $shipment->markDelivered();
        }

        return response()->json([
            'message' => 'Tracking updated.',
            'shipment' => $shipment->fresh(),
        ]);
    }

    public function escalate(Request $request, Shipment $shipment)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $shipment->escalateException($request->input('note', ''));

        return response()->json([
            'message'  => 'Exception escalated and admins notified.',
            'shipment' => $shipment->fresh(),
        ]);
    }

    public function health(Request $request)
    {
        $courier = $request->route('courier') ?? $request->query('courier', 'jnt_express');

        try {
            $driver = $this->courierManager->driver($courier);
            $ok = $driver->testConnection();

            return response()->json([
                'status'    => $ok ? 'healthy' : 'degraded',
                'courier'   => $courier,
                'checked_at' => now()->toIso8601String(),
            ], $ok ? 200 : 503);
        } catch (\Exception $e) {
            return response()->json([
                'status'    => 'error',
                'courier'   => $courier,
                'message'   => $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ], 503);
        }
    }

    public function cancel(Request $request, Shipment $shipment)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($shipment->status_enum->isTerminal()) {
            return response()->json(['message' => 'Cannot cancel a shipment with status: ' . $shipment->status_label], 422);
        }

        $driver = $this->courierManager->driver($shipment->courier);

        // Each courier keys cancellation off a different identifier:
        //  - iMile needs both its order code and the waybill number, encoded
        //    together so ImileDriver::cancelShipment can split them back out.
        //  - LogesTechs needs its own short numeric package id, which is stored
        //    on sorting_code (its long barcode goes to tracking_number and is
        //    *not* accepted here) — see LogesTechsDriver's class docblock.
        //  - J&T's cancelOrder only needs txlogistic_id, so its path is untouched.
        $identifier = match (true) {
            $shipment->courier === 'imile' && (bool) $shipment->tracking_number
                => $shipment->txlogistic_id . '|' . $shipment->tracking_number,
            $shipment->courier === 'logestechs'
                => (string) $shipment->sorting_code,
            default => $shipment->txlogistic_id,
        };

        if ($shipment->courier === 'logestechs' && $identifier === '') {
            return response()->json([
                'message' => 'Cannot cancel this shipment.',
                'error' => 'LogesTechs package id is missing on this shipment, so it cannot be cancelled through the API. Cancel it directly in the LogesTechs portal.',
            ], 422);
        }

        $result = $driver->cancelShipment($identifier, $request->input('reason'));

        if (! $result->success) {
            return response()->json([
                'message' => 'Failed to cancel shipment.',
                'error' => $result->errorMessage,
            ], 422);
        }

        $shipment->markCancelled($request->input('reason'));

        return response()->json([
            'message' => 'Shipment cancelled successfully.',
            'shipment' => $shipment->fresh(),
        ]);
    }

    /**
     * A Saudi National Address short code: four uppercase letters followed by
     * four digits (e.g. RDLC4305). LogesTechs enforces no format — any
     * non-empty string is accepted — so this only has to look like the real
     * thing for their staff reading the waybill.
     */
    protected function randomNationalAddress(): string
    {
        $letters = '';

        for ($i = 0; $i < 4; $i++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    protected function courierLabel(string $courier): string
    {
        return match ($courier) {
            'jnt_express' => 'J&T Express',
            'imile' => 'iMile',
            'logestechs' => 'LogesTechs',
            default => $courier,
        };
    }

    /**
     * Service types aren't a shared vocabulary: J&T (and iMile, which inherits
     * ShipmentData's default) use the numeric codes '01'/'02', while LogesTechs
     * expects a plain string like STANDARD. Picking the default here — rather
     * than letting ShipmentData's blanket '02' through — keeps the value stored
     * on `shipments.service_type` meaningful for whichever courier shipped it.
     */
    protected function defaultServiceType(string $courier, ?string $serviceType): string
    {
        if ($serviceType !== null && $serviceType !== '') {
            return $serviceType;
        }

        return $courier === 'logestechs' ? 'STANDARD' : '02';
    }
}
