<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\ClientShopifyConnection;
use App\Services\ShopifyService;
use App\Models\ConnectorSetting;
use App\Services\Shipping\CourierManager;
use App\Services\WhatsApp\MetaWhatsAppService;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConnectorSettingsController extends Controller
{
    /**
     * Placeholder sent to the browser in place of a saved encrypted value.
     * The real value is never included in show()'s response — only
     * reveal() returns it, one key at a time, on explicit request.
     */
    private const MASKED_VALUE = '••••••••';

    /**
     * Each connector's settings are a permission of their own; one without a
     * settings page is left to the full-access roles.
     */
    private function authorizeConnector(Connector $connector): void
    {
        $permission = PermissionCatalog::connectorPermission($connector->key);

        abort_unless(
            $permission ? request()->user()->can($permission) : request()->user()->hasFullAccess(),
            403,
            'You do not have permission to manage this connector.',
        );
    }

    public function show(Connector $connector)
    {
        $this->authorizeConnector($connector);

        $settings = ConnectorSetting::where('connector_id', $connector->id)->get();

        $masked = $settings->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->is_encrypted
                    ? ($setting->value ? self::MASKED_VALUE : null)
                    : $setting->value,
                'is_encrypted' => $setting->is_encrypted,
            ];
        })->keyBy('key');

        return response()->json([
            'connector' => $connector,
            'settings' => $masked,
        ]);
    }

    public function update(Request $request, Connector $connector)
    {
        $this->authorizeConnector($connector);

        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
            'settings.*.is_encrypted' => 'boolean',
        ]);

        foreach ($request->input('settings') as $setting) {
            $key = $setting['key'];
            $value = $setting['value'];
            $isEncrypted = $setting['is_encrypted'] ?? false;

            // Skip if encrypted field sent as masked value
            if ($isEncrypted && $value === self::MASKED_VALUE) {
                continue;
            }

            ConnectorSetting::updateOrCreate(
                ['connector_id' => $connector->id, 'key' => $key],
                ['value' => $value, 'is_encrypted' => $isEncrypted],
            );
        }

        return response()->json(['message' => 'Settings saved successfully.']);
    }

    /**
     * Decrypt and return one encrypted setting's real value on demand — the
     * "reveal" action behind the eye icon on an already-saved secret.
     * Deliberately separate from show(): the real value only ever leaves
     * the server when someone with `reveal connector secrets` explicitly asks for this
     * one key, not on every settings-page load, and every reveal is logged.
     */
    public function reveal(Request $request, Connector $connector)
    {
        $this->authorizeConnector($connector);

        $request->validate([
            'key' => 'required|string',
        ]);

        $setting = ConnectorSetting::where('connector_id', $connector->id)
            ->where('key', $request->input('key'))
            ->first();

        if (! $setting || ! $setting->is_encrypted || ! $setting->value) {
            return response()->json(['message' => 'Setting not found.'], 404);
        }

        Log::info('Connector secret revealed', [
            'connector'    => $connector->key,
            'setting_key'  => $setting->key,
            'user_id'      => $request->user()?->id,
            'user_email'   => $request->user()?->email,
            'ip'           => $request->ip(),
        ]);

        return response()->json(['value' => $setting->value]);
    }

    public function test(Request $request, Connector $connector)
    {
        $this->authorizeConnector($connector);

        // WhatsApp is not a courier — it validates against Meta's Graph API
        // rather than through CourierManager.
        if ($connector->key === 'whatsapp') {
            return $this->testWhatsApp();
        }

        if ($connector->key === 'shopify') {
            return $this->testShopify();
        }

        $courierLabels = [
            'jnt_express' => 'J&T Express',
            'imile' => 'iMile',
            'logestechs' => 'LogesTechs',
        ];

        if (! isset($courierLabels[$connector->key])) {
            return response()->json(['success' => false, 'message' => 'Test not supported for this connector.'], 400);
        }

        try {
            $manager = new CourierManager();
            $driver = $manager->driver($connector->key);
            $success = $driver->testConnection();

            return response()->json([
                'success' => $success,
                'message' => $success
                    ? "Connection successful! {$courierLabels[$connector->key]} API is reachable."
                    : 'Connection failed. Please check your credentials.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prove this server can reach the Shopify Admin API (and so passes the
     * app's IP allowlist). The allowlist is app-wide, so one working store is
     * enough: stores are tried in turn until one answers, and a store that
     * fails for its own reasons (uninstalled, bad token) does not mask the rest.
     */
    private function testShopify()
    {
        $shopify = app(ShopifyService::class);
        $connections = ClientShopifyConnection::where('status', 'active')->latest('id')->limit(5)->get();

        if ($connections->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No connected Shopify store to test with.']);
        }

        $failures = [];

        foreach ($connections as $connection) {
            try {
                $token = $shopify->getValidToken($connection);
                $data  = $shopify->graphql($connection->shop_domain, $token, '{ shop { name } }');

                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! Reached ' . ($data['shop']['name'] ?? $connection->shop_domain) . ' on the Shopify Admin API.',
                ]);
            } catch (\Throwable $e) {
                Log::channel('shopify')->warning('Shopify connection test failed', [
                    'shop' => $connection->shop_domain, 'error' => $e->getMessage(),
                ]);
                $failures[] = $e->getMessage();
            }
        }

        $blocked = collect($failures)->contains(fn ($m) => str_contains($m, '403') || stripos($m, 'allowlist') !== false);

        return response()->json([
            'success' => false,
            'message' => $blocked
                ? 'Shopify blocked the request (403). Check the app IP allowlist includes this server\'s IPv4 and IPv6 addresses.'
                : 'Could not reach the Shopify Admin API from any connected store. See the shopify log.',
        ]);
    }

    /**
     * Validate the saved Meta credentials by fetching the phone number itself —
     * cheap, read-only, and it exercises exactly the auth path the sends use.
     * The verified name is echoed back so the operator can confirm they wired up
     * the right WhatsApp Business number, not just a syntactically valid token.
     */
    private function testWhatsApp()
    {
        try {
            $service = new MetaWhatsAppService();

            if (! $service->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone Number ID and Access Token are both required.',
                ]);
            }

            $number = $service->fetchPhoneNumber();
            $display = $number['display_phone_number'] ?? null;

            if (! $display) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meta accepted the token but returned no phone number — check the Phone Number ID.',
                ]);
            }

            $name = $number['verified_name'] ?? 'unverified';

            return response()->json([
                'success' => true,
                'message' => "Connection successful! Connected to {$display} ({$name}).",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
