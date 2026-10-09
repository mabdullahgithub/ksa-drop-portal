<?php

namespace Tests\Feature\Concerns;

use App\Models\Client;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Team members with exact permissions, and the client-owned records the
 * permission tests look at. The catalog's permissions and the two full-access
 * roles already exist: the migrations create them.
 */
trait MakesStaff
{
    protected function setUpStaff(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('client');
    }

    /**
     * A user whose one role holds exactly these catalog permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function staffWith(array $permissions, ?string $role = null): User
    {
        $role = Role::create(['name' => $role ?? 'role-' . uniqid()]);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** A user with a full-access role. */
    protected function fullAccessUser(string $role = 'superadmin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** Limit a user to these clients. */
    protected function limitTo(User $user, Client ...$clients): User
    {
        $user->forceFill(['client_access' => User::CLIENT_ACCESS_ASSIGNED])->save();
        $user->assignedClients()->sync(collect($clients)->pluck('id'));

        return $user;
    }

    protected function makeClient(string $name = 'Acme Trading'): Client
    {
        return Client::create([
            'user_id' => User::factory()->create()->id,
            'client_types' => ['fulfilment'],
            'company_name' => $name,
            'short_id' => 'C' . strtoupper(substr(uniqid(), -5)),
            'status' => 'active',
        ]);
    }

    protected function makeOrder(?Client $client = null, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'client_id' => $client?->id,
            'order_number' => 'ORD-' . uniqid(),
            'customer_name' => 'Sara',
            'total' => 100,
        ]);
    }

    protected function makeShipment(Order $order, string $status = 'in_transit'): Shipment
    {
        return Shipment::create([
            'order_id' => $order->id,
            'courier' => 'jnt_express',
            'tracking_number' => 'TRACK' . substr(uniqid(), -8),
            'txlogistic_id' => 'TX' . substr(uniqid(), -10),
            'status' => $status,
        ]);
    }
}
