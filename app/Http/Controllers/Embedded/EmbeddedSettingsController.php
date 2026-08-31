<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Services\EmbeddedPayloadService;
use Illuminate\Http\Request;

class EmbeddedSettingsController extends Controller
{
    public function __construct(private EmbeddedPayloadService $payload) {}

    public function show(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        return response()->json($this->payload->settings($connection));
    }

    public function update(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        $validated = $request->validate([
            'financial_statuses'     => 'array',
            'financial_statuses.*'   => 'string|in:' . implode(',', EmbeddedPayloadService::FINANCIAL_STATUSES),
            'fulfillment_statuses'   => 'array',
            'fulfillment_statuses.*' => 'string|in:' . implode(',', EmbeddedPayloadService::FULFILLMENT_STATUSES),
            'tags_include'           => 'array',
            'tags_include.*'         => 'string|max:255',
            'tags_exclude'           => 'array',
            'tags_exclude.*'         => 'string|max:255',
            'payment_method'         => 'required|in:all,cod,prepaid',
        ]);

        $connection->update(['sync_filters' => $this->payload->filtersWithDefaults($validated)]);

        return response()->json([
            'message'      => 'Settings saved.',
            'sync_filters' => $connection->sync_filters,
        ]);
    }
}
