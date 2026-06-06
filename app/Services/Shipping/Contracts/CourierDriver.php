<?php

namespace App\Services\Shipping\Contracts;

use App\Services\Shipping\DTOs\CancelResult;
use App\Services\Shipping\DTOs\ShipmentData;
use App\Services\Shipping\DTOs\ShipmentResult;
use App\Services\Shipping\DTOs\TrackingResult;
use App\Services\Shipping\Enums\ShipmentStatus;

interface CourierDriver
{
    public function createShipment(ShipmentData $data): ShipmentResult;

    public function trackShipment(string $trackingNumber): TrackingResult;

    public function cancelShipment(string $trackingNumber, string $reason): CancelResult;

    public function testConnection(): bool;

    public function getDriverName(): string;

    public function normalizeStatus(string $rawStatus): ShipmentStatus;
}
