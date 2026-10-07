<?php

namespace App\Services\Riders;

use App\Models\Rider;
use App\Models\Shipment;
use App\Services\Shipping\Enums\RiderAction;
use App\Services\Shipping\ShipmentEventRecorder;

/**
 * A parcel as the rider app shows it: who to deliver to, what to collect,
 * and which updates the rider may make right now.
 */
class RiderParcelPresenter
{
    public function __construct(private ShipmentEventRecorder $recorder) {}

    /**
     * Lists should eager-load with self::RELATIONS and withCount(self::attemptsCount()).
     */
    public const RELATIONS = ['order.items', 'rider', 'latestEvent'];

    public static function attemptsCount(): array
    {
        return ['events as attempts' => fn ($q) => $q->where('action', RiderAction::ATTEMPT_FAILED->value)];
    }

    public function present(Shipment $shipment, Rider $rider): array
    {
        $shipment->loadMissing(self::RELATIONS);
        $order = $shipment->order;
        $options = $this->recorder->optionsFor($shipment, $rider);

        $attempts = array_key_exists('attempts', $shipment->getAttributes())
            ? $shipment->attempts
            : $shipment->events()->where('action', RiderAction::ATTEMPT_FAILED->value)->count();

        $lastEvent = $shipment->latestEvent;

        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'order_number' => $order?->order_number,
            'status' => $shipment->status,
            'status_label' => $shipment->status_label,
            'receiver' => $shipment->receiverDetails(),
            'cod_amount' => $shipment->expectedCodAmount(),
            'currency' => $shipment->api_response['cod_currency'] ?? $order?->currency ?? 'SAR',
            'items' => $order
                ? $order->items->take(10)->map(fn ($item) => [
                    'name' => trim($item->lineitem_name . ($item->variant_name ? ' - ' . $item->variant_name : '')),
                    'quantity' => (int) $item->lineitem_quantity,
                ])->values()->all()
                : [],
            'pieces' => $order ? (int) $order->items->sum('lineitem_quantity') : 0,
            'remark' => $shipment->api_response['remark'] ?? null,
            'attempts' => (int) $attempts,
            'last_event' => $lastEvent ? [
                'action' => $lastEvent->action,
                'reason' => $lastEvent->reason,
                'reschedule_date' => $lastEvent->reschedule_date?->toDateString(),
                'note' => $lastEvent->note,
                'occurred_at' => $lastEvent->occurred_at?->toIso8601String(),
            ] : null,
            'held_by_me' => (int) $shipment->rider_id === $rider->id,
            // Returned or cancelled and still with this rider: nothing to
            // deliver or collect, only to hand back at the hub.
            'to_return' => (int) $shipment->rider_id === $rider->id && $shipment->awaitsHandBack(),
            'allowed_actions' => array_map(fn (RiderAction $action) => $action->value, $options['actions']),
            'blocked' => $options['blocked'],
            'held_by' => $options['held_by'],
        ];
    }
}
