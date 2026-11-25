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
        return $this->decide($request, $client, 'active');
    }

    public function reject(Request $request, Client $client): JsonResponse
    {
        $reason = $request->validate(['reason' => 'required|string|max:1000'])['reason'];

        return $this->decide($request, $client, Client::STATUS_REJECTED, $reason);
    }

    private function decide(Request $request, Client $client, string $status, ?string $reason = null): JsonResponse
    {
        $decided = DB::transaction(function () use ($request, $client, $status, $reason) {
            // Locked so two people cannot decide the same signup.
            $fresh = Client::whereKey($client->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->signed_up_at === null || $fresh->status !== Client::STATUS_PENDING) {
                return null;
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
            'client_types' => $client->client_types ?? [],
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
