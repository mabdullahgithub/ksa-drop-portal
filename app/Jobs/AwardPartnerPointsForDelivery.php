<?php

namespace App\Jobs;

use App\Models\Shipment;
use App\Services\Partners\PartnerPoints;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued from Shipment::markDelivered(). Safe to run twice: PartnerPoints
 * awards each event once.
 */
class AwardPartnerPointsForDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Shipment $shipment) {}

    public function handle(PartnerPoints $points): void
    {
        $this->shipment->loadMissing('order.client.partner');

        if ($this->shipment->status !== 'delivered') {
            return;
        }

        $points->forDelivery($this->shipment);
    }
}
