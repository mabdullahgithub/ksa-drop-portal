<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use Illuminate\Http\Request;

class EmbeddedSettingsController extends Controller
{
    /** Statuses offered by the settings UI and accepted by validation. */
    private const FINANCIAL_STATUSES   = ['paid', 'pending', 'refunded', 'partially_refunded', 'partially_paid', 'voided'];
    private const FULFILLMENT_STATUSES = ['fulfilled', 'unfulfilled', 'cancelled'];

    public function show(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        return response()->json([
            'sync_mode'    => $connection->sync_mode,
            'sync_filters' => $this->withDefaults($connection->sync_filters ?? []),
        ]);
    }

    public function update(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        $validated = $request->validate([
            'financial_statuses'     => 'array',
            'financial_statuses.*'   => 'string|in:' . implode(',', self::FINANCIAL_STATUSES),
            'fulfillment_statuses'   => 'array',
            'fulfillment_statuses.*' => 'string|in:' . implode(',', self::FULFILLMENT_STATUSES),
            'tags_include'           => 'array',
            'tags_include.*'         => 'string|max:255',
            'tags_exclude'           => 'array',
            'tags_exclude.*'         => 'string|max:255',
            'payment_method'         => 'required|in:all,cod,prepaid',
        ]);

        $connection->update(['sync_filters' => $this->withDefaults($validated)]);

        return response()->json([
            'message'      => 'Settings saved.',
            'sync_filters' => $connection->sync_filters,
        ]);
    }

    private function withDefaults(array $filters): array
    {
        return [
            'financial_statuses'   => array_values($filters['financial_statuses'] ?? []),
            'fulfillment_statuses' => array_values($filters['fulfillment_statuses'] ?? []),
            'tags_include'         => array_values($filters['tags_include'] ?? []),
            'tags_exclude'         => array_values($filters['tags_exclude'] ?? []),
            'payment_method'       => $filters['payment_method'] ?? 'all',
        ];
    }
}
