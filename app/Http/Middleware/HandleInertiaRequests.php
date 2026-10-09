<?php

namespace App\Http\Middleware;

use App\Http\Concerns\ResolvesNotifiableUser;
use App\Models\Client;
use App\Services\Inventory\StockAlerts;
use App\Support\PermissionCatalog;
use App\Support\WhatsAppMessaging;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use ResolvesNotifiableUser;

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

        // Resolve the client: impersonated client takes priority over the logged-in user's own client
        $client = null;
        $impersonating = null;

        if ($user && session()->has('impersonate.admin_id')) {
            try {
                $impersonatedClient = Client::find(session('impersonate.client_id'));
                if ($impersonatedClient) {
                    $client = $impersonatedClient;
                    $impersonating = [
                        'client_id' => $client->id,
                        'client_name' => $client->company_name,
                    ];
                }
            } catch (\Exception $e) {
                $client = null;
            }
        } elseif ($user && $user->hasRole('client')) {
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
                // superadmin and developer are sent the whole list: for them
                // nothing is ever checked (see the Gate::before).
                'permissions' => match (true) {
                    ! $user => [],
                    $user->hasFullAccess() => PermissionCatalog::names(),
                    default => $user->getAllPermissions()->pluck('name'),
                },
                'full_access' => (bool) $user?->hasFullAccess(),
                'roles' => $user ? $user->getRoleNames() : [],
                'portal_features' => $client?->portal_features,
                'impersonating' => $impersonating,
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
                    'is_dropshipper' => $client->is_dropshipper,
                    'is_fulfilment' => $client->is_fulfilment,
                    'status' => $client->status,
                    'charges' => $client->charges,
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
                'error' => fn () => $request->session()->get('error'),
            ],
            // Delivered on every Inertia visit so the notification badge stays
            // fresh from normal navigation alone — no dedicated polling
            // request needed for the common case. Cached briefly server-side
            // (see ResolvesNotifiableUser) so this costs nothing extra.
            //
            // Deliberately NOT nested under a `notifications` key: the
            // notifications index page already renders its own top-level
            // `notifications` prop (the paginated list), which would silently
            // overwrite this one on that exact page.
            'unreadNotificationsCount' => fn () => $user
                ? $this->cachedUnreadCount($this->resolveNotifiableUser($request) ?? $user)
                : 0,
            // The messaging toggle on the WhatsApp page. While off, the screens
            // hide everything WhatsApp added (call/WhatsApp columns, the
            // call-outcome panel, the dashboard section). Only staff see it.
            'whatsappMessaging' => fn () => $user && ! $user->hasRole('client') && WhatsAppMessaging::enabled(),
            // The stock warning cards: a fulfilment client's products down to
            // their last few units, or out. See StockAlerts.
            'stockAlerts' => fn () => $user ? $this->stockAlerts($request, $client) : null,
        ];
    }

    /**
     * What the stock warning cards show: a client sees their own products, the
     * team sees every fulfilment client's. Null when there is nothing to warn
     * about, or nobody here it concerns.
     */
    private function stockAlerts(Request $request, ?Client $client): ?array
    {
        $user = $request->user();
        $alerts = app(StockAlerts::class);

        if ($client) {
            $products = $alerts->forClient($client);
            $scope = "client-{$client->id}";
        } elseif (! $user->hasRole('client') && $user->can('view stock alerts')) {
            $products = $alerts->forStaff();
            $scope = 'staff';
        } else {
            return null;
        }

        if ($products === []) {
            return null;
        }

        return [
            'audience' => $client ? 'client' : 'staff',
            // A card that was closed comes back at the next sign-in: the
            // session, and this with it, is thrown away on sign-out.
            'session' => $scope . '-' . ($request->hasSession()
                ? $request->session()->remember('stock_alerts_seen', fn () => Str::random(12))
                : ''),
            'products' => $products,
        ];
    }
}
