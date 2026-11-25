<?php

namespace App\Services\Inventory;

use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\InventoryRequest;
use App\Models\User;

/**
 * How much is waiting in each queue of the Requests page, for the tab counts
 * and the number beside "Requests" in the sidebar.
 *
 * A queue the person can't open is left out (null), and the models' own
 * client restriction keeps the rest to the clients they handle.
 */
class RequestQueues
{
    /** Tab => the permission that opens it. */
    public const TABS = [
        'products' => 'view product requests',
        InventoryRequest::STOCK => 'view stock requests',
        'signups' => 'view signup requests',
    ];

    /**
     * @return array{products: ?int, stock: ?int, signups: ?int}
     */
    public static function counts(User $user): array
    {
        $waiting = fn (string $type) => InventoryRequest::pending()->where('type', $type)->actionable()->count();

        return [
            // A client in the recycle bin keeps its products, so the client
            // is checked on its own.
            'products' => $user->can(self::TABS['products'])
                ? ClientProduct::pending()->whereHas('client')->count()
                : null,
            InventoryRequest::STOCK => $user->can(self::TABS[InventoryRequest::STOCK]) ? $waiting(InventoryRequest::STOCK) : null,
            'signups' => $user->can(self::TABS['signups'])
                ? Client::whereNotNull('signed_up_at')->where('status', Client::STATUS_PENDING)->count()
                : null,
        ];
    }

    public static function total(User $user): int
    {
        return array_sum(array_map('intval', self::counts($user)));
    }
}
