<?php

namespace App\Services\Shipping\DTOs;

use App\Services\Shipping\Enums\ShipmentStatus;

class TrackingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?ShipmentStatus $currentStatus,
        /** @var TrackingEvent[] */
        public readonly array $events,
        public readonly ?string $errorMessage,
        public readonly array $rawResponse,
    ) {}

    public static function success(ShipmentStatus $status, array $events, array $rawResponse): self
    {
        return new self(
            success: true,
            currentStatus: $status,
            events: $events,
            errorMessage: null,
            rawResponse: $rawResponse,
        );
    }

    public static function failure(string $errorMessage, array $rawResponse = []): self
    {
        return new self(
            success: false,
            currentStatus: null,
            events: [],
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }
}
