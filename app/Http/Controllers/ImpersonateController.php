<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    public function impersonate(Client $client)
    {
        if (!auth()->user()->hasPermissionTo('impersonate client')) {
            abort(403);
        }

        if (!$client->user_id) {
            abort(422, 'This client has no associated user account.');
        }

        session([
            'impersonate.admin_id' => auth()->id(),
            'impersonate.client_id' => $client->id,
            'impersonate.client_name' => $client->company_name,
        ]);

        return redirect()->route('portal.dashboard');
    }

    public function leave()
    {
        session()->forget(['impersonate.admin_id', 'impersonate.client_id', 'impersonate.client_name']);

        return redirect()->route('client');
    }
}
