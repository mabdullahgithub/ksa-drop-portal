<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Inertia\Inertia;

class ClientPageController extends Controller
{
    public function show(Client $client)
    {
        $client->load(['user', 'creator', 'clientProducts']);

        $data = [
            'id'                      => $client->id,
            'user_id'                 => $client->user_id,
            'created_by'              => $client->created_by,
            'client_types'            => $client->client_types,
            'company_name'            => $client->company_name,
            'client_id'               => $client->client_id,
            'contact_person'          => $client->contact_person,
            'phone'                   => $client->phone,
            'secondary_phone'         => $client->secondary_phone,
            'address'                 => $client->address,
            'city'                    => $client->city,
            'country'                 => $client->country,
            'postal_code'             => $client->postal_code,
            'tax_id'                  => $client->tax_id,
            'commercial_registration' => $client->commercial_registration,
            'status'                  => $client->status,
            'status_color'            => $client->status_color,
            'portal_features'         => $client->portal_features,
            'charges'                 => $client->charges,
            'notes'                   => $client->notes,
            'type_label'              => $client->type_label,
            'is_dropshipper'          => $client->is_dropshipper,
            'is_fulfilment'           => $client->is_fulfilment,
            'user'                    => $client->user ? [
                'id'    => $client->user->id,
                'name'  => $client->user->name,
                'email' => $client->user->email,
            ] : null,
            'creator'                 => $client->creator ? [
                'id'   => $client->creator->id,
                'name' => $client->creator->name,
            ] : null,
            'orders_count'            => $client->orders()->count(),
            'total_revenue'           => round((float) $client->orders()->sum('total'), 2),
            'products_count'          => $client->clientProducts()->count(),
            'verified_products_count' => $client->clientProducts()->verified()->count(),
            'created_at'              => $client->created_at?->toISOString(),
            'updated_at'              => $client->updated_at?->toISOString(),
        ];

        return Inertia::render('Client/Show', ['client' => $data]);
    }
}
