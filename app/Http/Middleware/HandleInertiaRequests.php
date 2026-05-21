<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $client = null;
        if ($user && $user->hasRole('client')) {
            try {
                $client = $user->client;
            } catch (\Exception $e) {
                $client = null;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name') : [],
                'roles' => $user ? $user->getRoleNames() : [],
                'portal_features' => $client?->portal_features,
                'client' => $client ? [
                    'id' => $client->id,
                    'company_name' => $client->company_name,
                    'logo' => $client->logo,
                    'client_id' => $client->client_id,
                    'contact_person' => $client->contact_person,
                    'phone' => $client->phone,
                    'secondary_phone' => $client->secondary_phone,
                    'address' => $client->address,
                    'city' => $client->city,
                    'country' => $client->country,
                    'postal_code' => $client->postal_code,
                    'tax_id' => $client->tax_id,
                    'commercial_registration' => $client->commercial_registration,
                    'type_label' => $client->type_label,
                ] : null,
            ],
            'preferences' => $request->user()?->preference ? [
                'toast_position' => $request->user()->preference->toast_position ?? 'bottom-right',
                'in_app_notifications' => $request->user()->preference->in_app_notifications ?? true,
            ] : [
                'toast_position' => 'bottom-right',
                'in_app_notifications' => true,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
