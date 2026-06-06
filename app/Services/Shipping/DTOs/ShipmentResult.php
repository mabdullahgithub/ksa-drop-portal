<?php

namespace App\Services\Shipping\DTOs;

class ShipmentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $trackingNumber,
        public readonly ?string $sortingCode,
        public readonly ?string $txlogisticId,
        public readonly ?string $errorMessage,
        public readonly ?string $errorCode,
        public readonly array $rawResponse,
    ) {}

    public static function success(string $trackingNumber, ?string $sortingCode, string $txlogisticId, array $rawResponse): self
    {
        return new self(
            success: true,
            trackingNumber: $trackingNumber,
            sortingCode: $sortingCode,
            txlogisticId: $txlogisticId,
            errorMessage: null,
            errorCode: null,
            rawResponse: $rawResponse,
        );
    }

    public static function failure(string $errorMessage, ?string $errorCode = null, array $rawResponse = []): self
    {
        return new self(
            success: false,
            trackingNumber: null,
            sortingCode: null,
            txlogisticId: null,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            rawResponse: $rawResponse,
        );
    }
}
