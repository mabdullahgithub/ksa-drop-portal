<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\Clients\WelcomeClientMail;
use App\Models\Client;
use App\Models\ClientProduct;
use App\Models\User;
use App\Notifications\ClientCreatedNotification;
use App\Notifications\ClientStatusChangedNotification;
use App\Notifications\ProductVerifiedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with('user');

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->withStatus($request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->withType($request->type);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $clients = $query->paginate($perPage);

        $clients->getCollection()->transform(function ($client) {
            return $this->formatClient($client);
        });

        return response()->json($clients);
    }

    public function show(Client $client)
    {
        $client->load(['user', 'creator', 'clientProducts']);

        $data = $this->formatClient($client);
        $data['orders_count'] = $client->orders()->count();
        $data['total_revenue'] = round((float) $client->orders()->sum('total'), 2);
        $data['products_count'] = $client->clientProducts()->count();
        $data['verified_products_count'] = $client->clientProducts()->verified()->count();

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'client_types' => 'required|array|min:1',
            'client_types.*' => 'in:dropshipper,fulfilment',
            'company_name' => 'required|string|max:255',
            'client_id' => 'nullable|string|min:2|max:6|unique:clients,short_id|alpha_num',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'commercial_registration' => 'nullable|string|max:50',
            'portal_features' => 'nullable|array',
            'portal_features.*' => 'in:orders,inventory,revenue,finance,products',
            'charges' => 'nullable|array',
            'charges.delivery' => 'nullable|numeric|min:0',
            'charges.return' => 'nullable|numeric|min:0',
            'charges.cod' => 'nullable|numeric|min:0',
            'charges.warehousing' => 'nullable|numeric|min:0',
            'charges.call_confirmation' => 'nullable|numeric|min:0',
            'charges.vat' => 'nullable|numeric|min:0|max:100',
            'charges.other' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $password = Str::random(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('client');

        $clientId = $validated['client_id'] ?? Client::generateClientId($validated['company_name']);

        $client = Client::create([
            'user_id' => $user->id,
            'created_by' => auth()->id(),
            'client_types' => $validated['client_types'],
            'company_name' => $validated['company_name'],
            'short_id' => $clientId,
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'secondary_phone' => $validated['secondary_phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? 'SA',
            'postal_code' => $validated['postal_code'] ?? null,
            'tax_id' => $validated['tax_id'] ?? null,
            'commercial_registration' => $validated['commercial_registration'] ?? null,
            'portal_features' => $validated['portal_features'] ?? ['orders', 'inventory', 'revenue'],
            'charges' => $validated['charges'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        try {
            Mail::to($user->email)->send(new WelcomeClientMail($client, $password));
        } catch (\Exception $e) {
            // Email sending failure should not block client creation
        }

        // Notify all admins and superadmins
        User::role(['admin', 'superadmin'])->each(
            fn ($adminUser) => $adminUser->notify(new ClientCreatedNotification($client))
        );

        $client->load('user');

        return response()->json([
            'message' => 'Client created successfully',
            'client' => $this->formatClient($client),
        ], 201);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'client_types' => 'sometimes|array|min:1',
            'client_types.*' => 'in:dropshipper,fulfilment',
            'company_name' => 'sometimes|string|max:255',
            'client_id' => 'sometimes|string|min:2|max:6|alpha_num|unique:clients,short_id,' . $client->id,
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'secondary_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'commercial_registration' => 'nullable|string|max:50',
            'portal_features' => 'nullable|array',
            'portal_features.*' => 'in:orders,inventory,revenue,finance,products',
            'charges' => 'nullable|array',
            'charges.delivery' => 'nullable|numeric|min:0',
            'charges.return' => 'nullable|numeric|min:0',
            'charges.cod' => 'nullable|numeric|min:0',
            'charges.warehousing' => 'nullable|numeric|min:0',
            'charges.call_confirmation' => 'nullable|numeric|min:0',
            'charges.vat' => 'nullable|numeric|min:0|max:100',
            'charges.other' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['client_id'])) {
            $validated['short_id'] = $validated['client_id'];
            unset($validated['client_id']);
        }

        $client->update($validated);
        $client->load('user');

        return response()->json([
            'message' => 'Client updated successfully',
            'client' => $this->formatClient($client),
        ]);
    }

    public function updateStatus(Request $request, Client $client)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $oldStatus = $client->status;
        $newStatus = $request->status;

        $client->update(['status' => $newStatus]);

        // Only notify if the status actually changed
        if ($oldStatus !== $newStatus) {
            $client->user?->notify(new ClientStatusChangedNotification($client, $oldStatus, $newStatus));
        }

        return response()->json([
            'message' => 'Client status updated successfully',
            'client' => $this->formatClient($client->fresh('user')),
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
            'action' => 'required|in:activate,deactivate,suspend',
        ]);

        $statusMap = [
            'activate' => 'active',
            'deactivate' => 'inactive',
            'suspend' => 'suspended',
        ];

        $status = $statusMap[$validated['action']];

        Client::whereIn('id', $validated['client_ids'])->update(['status' => $status]);

        return response()->json([
            'message' => count($validated['client_ids']) . ' clients updated successfully',
        ]);
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return response()->json([
            'message' => 'Client deleted successfully',
        ]);
    }

    public function statistics()
    {
        $total = Client::count();
        $active = Client::where('status', 'active')->count();
        $inactive = Client::where('status', 'inactive')->count();
        $suspended = Client::where('status', 'suspended')->count();
        $dropshippers = Client::whereJsonContains('client_types', 'dropshipper')->count();
        $fulfilment = Client::whereJsonContains('client_types', 'fulfilment')->count();

        return response()->json([
            'total_clients' => $total,
            'active_clients' => $active,
            'inactive_clients' => $inactive,
            'suspended_clients' => $suspended,
            'dropshippers_count' => $dropshippers,
            'fulfilment_count' => $fulfilment,
        ]);
    }

    public function filterOptions()
    {
        return response()->json([
            'statuses' => [
                ['value' => 'all', 'label' => 'All'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'inactive', 'label' => 'Inactive'],
                ['value' => 'suspended', 'label' => 'Suspended'],
            ],
            'types' => [
                ['value' => 'all', 'label' => 'All Types'],
                ['value' => 'dropshipper', 'label' => 'Dropshipper'],
                ['value' => 'fulfilment', 'label' => 'Fulfilment'],
            ],
        ]);
    }

    public function export(Request $request)
    {
        $query = Client::with('user');

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $query->withStatus($request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->withType($request->type);
        }

        $clients = $query->orderBy('created_at', 'desc')->get();

        $csvData = "Client ID,Company Name,Contact Person,Email,Phone,Type,Status,Created At\n";

        foreach ($clients as $client) {
            $csvData .= implode(',', [
                $client->client_id,
                '"' . str_replace('"', '""', $client->company_name) . '"',
                '"' . str_replace('"', '""', $client->contact_person ?? '') . '"',
                $client->user->email ?? '',
                $client->phone ?? '',
                $client->type_label,
                $client->status,
                $client->created_at->format('Y-m-d'),
            ]) . "\n";
        }

        return response($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="clients-' . date('Y-m-d') . '.csv"',
        ]);
    }

    // --- Client Products (Inventory) ---

    public function products(Request $request, Client $client)
    {
        $query = $client->clientProducts();

        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        if ($request->has('verification_status') && $request->verification_status !== 'all') {
            $query->where('verification_status', $request->verification_status);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    public function storeProduct(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'quantity' => 'required|integer|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $productCode = ClientProduct::generateProductCode($client);

        $product = $client->clientProducts()->create([
            'product_code' => $productCode,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'unit_price' => $validated['unit_price'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Product added successfully',
            'product' => $product,
        ], 201);
    }

    public function updateProduct(Request $request, Client $client, ClientProduct $product)
    {
        if ($product->client_id !== $client->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|integer|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh(),
        ]);
    }

    public function verifyProduct(Request $request, Client $client, ClientProduct $product)
    {
        if ($product->client_id !== $client->id) {
            abort(404);
        }

        $product->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        $product->client->user?->notify(new ProductVerifiedNotification($product->fresh()));

        return response()->json([
            'message' => 'Product verified successfully',
            'product' => $product->fresh(),
        ]);
    }

    public function destroyProduct(Client $client, ClientProduct $product)
    {
        if ($product->client_id !== $client->id) {
            abort(404);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    private function formatClient(Client $client): array
    {
        return [
            'id' => $client->id,
            'user_id' => $client->user_id,
            'created_by' => $client->created_by,
            'client_types' => $client->client_types,
            'company_name' => $client->company_name,
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
            'status' => $client->status,
            'status_color' => $client->status_color,
            'portal_features' => $client->portal_features,
            'charges' => $client->charges,
            'notes' => $client->notes,
            'type_label' => $client->type_label,
            'is_dropshipper' => $client->is_dropshipper,
            'is_fulfilment' => $client->is_fulfilment,
            'user' => $client->user ? [
                'id' => $client->user->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
            ] : null,
            'creator' => $client->relationLoaded('creator') && $client->creator ? [
                'id' => $client->creator->id,
                'name' => $client->creator->name,
            ] : null,
            'orders_count' => $client->orders_count ?? $client->orders()->count(),
            'created_at' => $client->created_at?->toISOString(),
            'updated_at' => $client->updated_at?->toISOString(),
        ];
    }
}
