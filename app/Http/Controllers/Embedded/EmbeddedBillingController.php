<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one-time plan, billed through the Shopify Billing API.
 *
 * Status is read back from Shopify rather than trusted from the return URL, so
 * there is no webhook dependency: after approval Shopify sends the merchant back
 * to the app, the app calls show(), and show() asks Shopify what happened.
 */
class EmbeddedBillingController extends Controller
{
    public function __construct(private ShopifyService $shopify) {}

    public function show(Request $request)
    {
        $connection = $this->connection($request);

        // Only a purchase still in flight can change; a settled one is final.
        if ($connection->plan_purchase_id && $connection->plan_status === 'pending') {
            $this->refresh($connection);
        }

        return response()->json($this->state($connection));
    }

    public function purchase(Request $request)
    {
        $connection = $this->connection($request);

        if ($connection->hasPaidPlan()) {
            return response()->json($this->state($connection));
        }

        try {
            $purchase = $this->shopify->createPlanPurchase($connection);
        } catch (Throwable $e) {
            Log::error('Shopify plan purchase could not be created', [
                'shop'  => $connection->shop_domain,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Could not start the purchase. Please try again.'], 502);
        }

        $connection->update([
            'plan_purchase_id' => $purchase['id'],
            'plan_status'      => 'pending',
        ]);

        return response()->json([
            ...$this->state($connection),
            'confirmation_url' => $purchase['confirmation_url'],
        ]);
    }

    private function refresh(ClientShopifyConnection $connection): void
    {
        try {
            $status = $this->shopify->fetchPurchaseStatus($connection, $connection->plan_purchase_id);
        } catch (Throwable $e) {
            Log::warning('Shopify plan status check failed', [
                'shop'  => $connection->shop_domain,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($status === null || $status === $connection->plan_status) {
            return;
        }

        $connection->update([
            'plan_status'       => $status,
            'plan_purchased_at' => $status === 'active' ? now() : null,
        ]);
    }

    private function state(ClientShopifyConnection $connection): array
    {
        $plan = config('services.shopify.plan');

        return [
            'plan' => [
                'name'     => $plan['name'],
                'price'    => $plan['price'],
                'currency' => $plan['currency'],
            ],
            'status'       => $connection->plan_status,
            'purchased'    => $connection->hasPaidPlan(),
            'purchased_at' => $connection->plan_purchased_at,
        ];
    }

    private function connection(Request $request): ClientShopifyConnection
    {
        return $request->attributes->get('shopify_connection');
    }
}
