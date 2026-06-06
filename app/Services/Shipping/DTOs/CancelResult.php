<?php

namespace App\Services\Shipping\DTOs;

class CancelResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage,
        public readonly ?string $errorCode,
        public readonly array $rawResponse,
    ) {}

    public static function success(array $rawResponse = []): self
    {
        return new self(
            success: true,
            errorMessage: null,
            errorCode: null,
            rawResponse: $rawResponse,
        );
    }

    public static function failure(string $errorMessage, ?string $errorCode = null, array $rawResponse = []): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            rawResponse: $rawResponse,
        );
    }
}
