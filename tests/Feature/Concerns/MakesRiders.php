<?php

namespace Tests\Feature\Concerns;

use App\Models\Order;
use App\Models\Rider;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Riders\RiderAuthService;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait MakesRiders
{
    protected function setUpRiderPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view riders', 'manage riders', 'manage rider payments', 'view orders', 'edit orders'] as $name) {
            Permission::findOrCreate($name);
        }
    }

    protected function staff(array $permissions = ['view riders', 'manage riders', 'manage rider payments', 'view orders', 'edit orders']): User
    {
        $role = Role::create(['name' => 'role-' . uniqid()]);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function makeRider(array $attributes = []): Rider
    {
        return Rider::create($attributes + [
            'name' => 'Ahmed Khan',
            'phone' => '+9665' . random_int(10000000, 99999999),
            'status' => Rider::STATUS_ACTIVE,
        ]);
    }

    /**
     * Sign a rider in on a "phone" and return the plain cookie token.
     */
    protected function signedInDevice(Rider $rider): string
    {
        $plain = Str::random(64);

        $rider->devices()->create([
            'token_hash' => hash('sha256', $plain),
            'sign_in_method' => 'pin',
            'last_seen_at' => now(),
        ]);

        return $plain;
    }

    /**
     * The Origin a same-site request carries — test URLs are built from
     * APP_URL, so this follows whatever the local .env points it at.
     */
    protected function appOrigin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Requests as the rider app makes them: device cookie + same-origin.
     */
    protected function asRider(string $plainToken): static
    {
        // withCredentials(): Laravel's JSON test requests otherwise drop cookies.
        return $this->withCredentials()
            ->withCookie(RiderAuthService::COOKIE, $plainToken)
            ->withHeader('Origin', $this->appOrigin())
            ->withHeader('Accept', 'application/json');
    }

    protected function warehouse(bool $default = true): Warehouse
    {
        return Warehouse::create([
            'name' => 'Riyadh Hub',
            'contact_name' => 'KSA',
            'phone' => '0501112229',
            'province' => 'Riyadh',
            'city' => 'Riyadh',
            'address' => 'Al Suwaidi',
            'post_code' => '12345',
            'country_code' => 'KSA',
            'is_default' => $default,
            'is_active' => true,
        ]);
    }

    protected function ksaShipment(array $attributes = [], float $cod = 100.0, string $paymentMethod = 'COD'): Shipment
    {
        $order = Order::create([
            'order_number' => (string) random_int(100000, 999999),
            'customer_name' => 'abdullah - ksadrop',
            'customer_phone' => '0500000000',
            'shipping_name' => 'abdullah - ksadrop',
            'shipping_phone' => '0500000000',
            'shipping_city' => 'Riyadh',
            'shipping_address1' => 'Al Suwaidi District',
            'shipping_country' => 'SA',
            'currency' => 'SAR',
            'total' => $cod ?: 100.0,
            'payment_method' => $paymentMethod,
            'financial_status' => 'pending',
        ]);

        $order->items()->create([
            'lineitem_name' => 'Test item',
            'lineitem_quantity' => 2,
            'lineitem_price' => 50.0,
        ]);

        static $sequence = 0;
        $sequence++;

        return Shipment::create($attributes + [
            'order_id' => $order->id,
            'courier' => KsaDropExpressDriver::KEY,
            'tracking_number' => 'KSD' . now()->format('ym') . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'txlogistic_id' => $order->order_number,
            'status' => ShipmentStatus::INFO_RECEIVED->value,
            'api_response' => [
                'courier' => KsaDropExpressDriver::KEY,
                'receiver' => ['name' => 'Confirmed Receiver', 'phone' => '0555555555', 'city' => 'Riyadh', 'address' => 'King Fahd Rd'],
                'remark' => 'Call before arriving',
                'cod_amount' => $cod,
                'cod_currency' => 'SAR',
            ],
        ]);
    }
}
