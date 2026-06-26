<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Connector;
use App\Models\Order;
use Illuminate\Http\Request;

class ConnectorController extends Controller
{
    public function index()
    {
        return response()->json(Connector::all());
    }

    public function toggle(Connector $connector)
    {
        $connector->update(['enabled' => !$connector->enabled]);
        return response()->json($connector);
    }

    public function enabledWithComingSoon(Request $request)
    {
        $connectors = Connector::where('enabled', true)->get();
        $comingSoon = Connector::where('key', 'coming_soon')->where('enabled', true)->exists();

        $connectors = $connectors
            ->filter(fn ($c) => !in_array($c->key, ['coming_soon', 'jnt_express']))
            ->values();

        // Attach the acting client's Shopify connection state to the shopify card.
        $client = $this->resolveClient($request);
        if ($client) {
            $connectors->each(function ($connector) use ($client) {
                if ($connector->key === 'shopify') {
                    $this->attachShopifyState($connector, $client);
                }
            });
        }

        return response()->json([
            'connectors' => $connectors,
            'coming_soon' => $comingSoon,
        ]);
    }

    private function resolveClient(Request $request): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return $request->user()?->client;
    }

    private function attachShopifyState(Connector $connector, Client $client): void
    {
        $conn = $client->shopifyConnection;

        $connector->client_connected   = $conn?->status === 'active';
        $connector->shop_domain        = $conn?->shop_domain;
        $connector->sync_mode          = $conn?->sync_mode;
        $connector->last_synced_at     = $conn?->last_synced_at?->toISOString();
        $connector->connected_at       = $conn?->connected_at?->toISOString();
        $connector->needs_reconnect    = $conn?->status === 'error';
        $connector->webhooks_registered = (bool) ($conn?->webhooks_registered);

        $connector->pending_count = ($conn?->status === 'active' && $conn?->sync_mode === 'manual_approval')
            ? Order::withoutGlobalScope('shopify_visible')
                ->where('client_id', $client->id)
                ->where('shopify_sync_status', 'pending_review')
                ->count()
            : 0;
    }
}
