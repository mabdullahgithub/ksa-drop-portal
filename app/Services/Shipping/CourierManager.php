<?php

namespace App\Services\Shipping;

use App\Services\Shipping\Contracts\CourierDriver;
use App\Services\Shipping\Drivers\ImileDriver;
use App\Services\Shipping\Drivers\JntExpressDriver;
use InvalidArgumentException;

class CourierManager
{
    protected array $drivers = [];

    public function driver(?string $name = null): CourierDriver
    {
        $name = $name ?? $this->getDefaultDriver();

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    protected function createDriver(string $name): CourierDriver
    {
        return match ($name) {
            'jnt_express' => new JntExpressDriver(),
            'imile' => new ImileDriver(),
            default => throw new InvalidArgumentException("Courier driver [{$name}] is not supported."),
        };
    }

    protected function getDefaultDriver(): string
    {
        return 'jnt_express';
    }

    public function getAvailableDrivers(): array
    {
        return ['jnt_express', 'imile'];
    }
}
