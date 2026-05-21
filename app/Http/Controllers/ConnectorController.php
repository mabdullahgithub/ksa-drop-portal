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
}
