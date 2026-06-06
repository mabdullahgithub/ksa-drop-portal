<?php

namespace App\Services\Shipping\DTOs;

use App\Services\Shipping\Enums\ShipmentStatus;

class TrackingEvent
{
    public function __construct(
        public readonly ShipmentStatus $status,
        public readonly string $description,
        public readonly ?string $location,
        public readonly string $timestamp,
        public readonly ?string $rawStatus,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'description' => $this->description,
            'location' => $this->location,
            'timestamp' => $this->timestamp,
            'raw_status' => $this->rawStatus,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: ShipmentStatus::from($data['status']),
            description: $data['description'],
            location: $data['location'] ?? null,
            timestamp: $data['timestamp'],
            rawStatus: $data['raw_status'] ?? null,
        );
    }
}
