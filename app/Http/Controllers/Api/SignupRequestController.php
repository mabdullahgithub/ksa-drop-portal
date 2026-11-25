<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Notifications\ClientStatusChangedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The "Signup requests" tab of the Requests page: merchants who signed up on
 * the public page. Approving activates the account, rejecting keeps it shut
 * with a reason; either way the applicant is told.
 */
class SignupRequestController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'all'])],
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $status = $validated['status'] ?? 'pending';
        $search = trim((string) ($validated['search'] ?? ''));

        $clients = Client::query()
            ->whereNotNull('signed_up_at')
            ->with(['user', 'signupReviewer'])
            ->when($status === 'pending', fn ($query) => $query->where('status', Client::STATUS_PENDING))
            ->when($status === 'rejected', fn ($query) => $query->where('status', Client::STATUS_REJECTED))
            // Anything past review counts as approved, suspended later or not.
            ->when($status === 'approved', fn ($query) => $query->whereNotIn('status', [Client::STATUS_PENDING, Client::STATUS_REJECTED]))
            ->when($search !== '', fn ($query) => $query->search($search));

        // The queue is worked oldest first; the history reads newest first.
        $clients = $status === 'pending'
            ? $clients->orderBy('signed_up_at')->orderBy('id')
            : $clients->orderByDesc('signed_up_at')->orderByDesc('id');

        return response()->json($clients->paginate(self::PER_PAGE)->through(fn (Client $client) => $this->present($client)));
    }

    public function approve(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'charges' => 'nullable|array',
            ...collect(Client::CHARGE_KEYS)->mapWithKeys(fn (string $key) => [
                "charges.{$key}" => 'nullable|numeric|min:0|max:' . ($key === 'vat' ? 100 : 999999),
            ])->all(),
        ]);

        // Only the known keys, as numbers; a blank one is 0.
        $charges = array_map('floatval', array_intersect_key($validated['charges'] ?? [], array_flip(Client::CHARGE_KEYS)));

        // Approving needs no say over charges, but setting them does. Sending
        // zeros, or what is already there, changes nothing.
        abort_if(
            ! $request->user()->can('edit client charges')
                && collect($charges)->contains(fn (float $value, string $key) => $value !== (float) ($client->charges[$key] ?? 0)),
            403,
            'You do not have permission to set charges.',
        );

        return $this->decide($request, $client, 'active', charges: $charges);
    }

    public function reject(Request $request, Client $client): JsonResponse
    {
        $reason = $request->validate(['reason' => 'required|string|max:1000'])['reason'];

        return $this->decide($request, $client, Client::STATUS_REJECTED, $reason);
    }

    /** @param  ?array<string, float>  $charges  given when approving */
    private function decide(Request $request, Client $client, string $status, ?string $reason = null, ?array $charges = null): JsonResponse
    {
        $decided = DB::transaction(function () use ($request, $client, $status, $reason, $charges) {
            // Locked so two people cannot decide the same signup.
            $fresh = Client::whereKey($client->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->signed_up_at === null || $fresh->status !== Client::STATUS_PENDING) {
                return null;
            }

            if ($charges !== null) {
                // What was entered, over what the client page already had,
                // over 0 for the rest.
                $fresh->charges = array_merge(array_fill_keys(Client::CHARGE_KEYS, 0), $fresh->charges ?? [], $charges);
            }

            $fresh->forceFill([
                'status' => $status,
                'signup_reviewed_at' => now(),
                'signup_reviewed_by' => $request->user()->id,
                'signup_rejection_reason' => $reason,
            ])->save();

            return $fresh;
        });

        if (! $decided) {
            return response()->json(['message' => 'This signup request has already been decided.'], 422);
        }

        try {
            $decided->user?->notify(new ClientStatusChangedNotification($decided, Client::STATUS_PENDING, $status));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => $status === 'active' ? 'Signup approved' : 'Signup rejected',
        ]);
    }

    private function present(Client $client): array
    {
        return [
            'id' => $client->id,
            'reference' => $client->short_id,
            'company_name' => $client->company_name,
            'contact_person' => $client->contact_person,
            'email' => $client->user?->email,
            'phone' => $client->phone,
            'city' => $client->city,
            'country' => $client->country === 'SA' ? 'Saudi Arabia' : $client->country,
            'client_types' => $client->client_types ?? [],
            // To prefill the approval form, for those who may set them.
            'charges' => request()->user()->can('edit client charges') ? $client->charges : null,
            'status' => match ($client->status) {
                Client::STATUS_PENDING => 'pending',
                Client::STATUS_REJECTED => 'rejected',
                default => 'approved',
            },
            'signed_up_at' => $client->signed_up_at?->toIso8601String(),
            'reviewed_at' => $client->signup_reviewed_at?->toIso8601String(),
            'reviewed_by' => $client->signupReviewer?->name,
            'rejection_reason' => $client->signup_rejection_reason,
        ];
    }
}
