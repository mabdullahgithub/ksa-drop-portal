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
        // Rich J&T scan attributes (all optional — populated from the push
        // `details[]` payload when present, null for events built elsewhere).
        public readonly ?string $scanTypeCode = null,
        public readonly ?string $networkName = null,
        public readonly ?string $networkId = null,
        public readonly ?string $staffName = null,
        public readonly ?string $staffContact = null,
        public readonly ?string $nextStopName = null,
        public readonly ?string $signaturePicUrl = null,
        public readonly ?string $problemPicUrl = null,
        public readonly ?string $latitude = null,
        public readonly ?string $longitude = null,
        public readonly ?string $otp = null,
    ) {}

    public function toArray(): array
    {
        // Core fields are always present; the rich scan attributes are only
        // included when set, keeping the stored history JSON compact and
        // backward-compatible with previously recorded events.
        $core = [
            'status' => $this->status->value,
            'description' => $this->description,
            'location' => $this->location,
            'timestamp' => $this->timestamp,
            'raw_status' => $this->rawStatus,
        ];

        $extras = array_filter([
            'scan_type_code' => $this->scanTypeCode,
            'network_name' => $this->networkName,
            'network_id' => $this->networkId,
            'staff_name' => $this->staffName,
            'staff_contact' => $this->staffContact,
            'next_stop_name' => $this->nextStopName,
            'signature_pic_url' => $this->signaturePicUrl,
            'problem_pic_url' => $this->problemPicUrl,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'otp' => $this->otp,
        ], fn ($value) => $value !== null && $value !== '');

        return $core + $extras;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            status: ShipmentStatus::from($data['status']),
            description: $data['description'],
            location: $data['location'] ?? null,
            timestamp: $data['timestamp'],
            rawStatus: $data['raw_status'] ?? null,
            scanTypeCode: $data['scan_type_code'] ?? null,
            networkName: $data['network_name'] ?? null,
            networkId: $data['network_id'] ?? null,
            staffName: $data['staff_name'] ?? null,
            staffContact: $data['staff_contact'] ?? null,
            nextStopName: $data['next_stop_name'] ?? null,
            signaturePicUrl: $data['signature_pic_url'] ?? null,
            problemPicUrl: $data['problem_pic_url'] ?? null,
            latitude: $data['latitude'] ?? null,
            longitude: $data['longitude'] ?? null,
            otp: $data['otp'] ?? null,
        );
    }
}
