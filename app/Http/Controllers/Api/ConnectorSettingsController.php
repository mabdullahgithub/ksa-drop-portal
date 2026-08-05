<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\ConnectorSetting;
use App\Services\Shipping\CourierManager;
use Illuminate\Http\Request;

class ConnectorSettingsController extends Controller
{
    public function show(Connector $connector)
    {
        $settings = ConnectorSetting::where('connector_id', $connector->id)->get();

        $masked = $settings->map(function ($setting) {
            return [
                'key' => $setting->key,
                'value' => $setting->is_encrypted
                    ? ($setting->value ? '••••••••' : null)
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
            if ($isEncrypted && $value === '••••••••') {
                continue;
            }

            ConnectorSetting::updateOrCreate(
                ['connector_id' => $connector->id, 'key' => $key],
                ['value' => $value, 'is_encrypted' => $isEncrypted],
            );
        }

        return response()->json(['message' => 'Settings saved successfully.']);
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
