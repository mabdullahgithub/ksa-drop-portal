<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\ConnectorSetting;
use App\Services\Shipping\CourierManager;
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

    public function show(Connector $connector)
    {
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
     * the server when someone with `edit apps` explicitly asks for this
     * one key, not on every settings-page load, and every reveal is logged.
     */
    public function reveal(Request $request, Connector $connector)
    {
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
        $courierLabels = [
            'jnt_express' => 'J&T Express',
            'imile' => 'iMile',
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
}
