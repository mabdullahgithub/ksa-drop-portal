<?php

namespace App\Http\Controllers;

use App\Models\Connector;
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

    public function enabledWithComingSoon()
    {
        $connectors = Connector::where('enabled', true)->get();
        $comingSoon = Connector::where('key', 'coming_soon')->where('enabled', true)->exists();

        return response()->json([
            'connectors' => $connectors->filter(fn ($c) => !in_array($c->key, ['coming_soon', 'jnt_express']))->values(),
            'coming_soon' => $comingSoon,
        ]);
    }
}
