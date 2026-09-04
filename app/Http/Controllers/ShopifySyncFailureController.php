<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Models\Client;
use App\Models\ClientShopifyConnection;
use App\Models\ShopifySyncFailure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Portal view onto the Shopify dead-letter queue: which orders did not make it
 * in, why, and a button to try again without waiting for the backoff.
 *
 * The automatic sweep (shopify:retry-failed-syncs) handles the common cases on
 * its own; this exists for the ones it cannot — a payload that keeps failing
 * until something is fixed, or a failure that has already exhausted its
 * attempts and been abandoned.
 */
class ShopifySyncFailureController extends Controller
{
    /**
     * Resolve the acting client (impersonation-aware).
     */
    private function resolveClient(): ?Client
    {
        if (session()->has('impersonate.client_id')) {
            return Client::find(session('impersonate.client_id'));
        }

        return auth()->user()->client ?? null;
    }

    /**
     * Failures this client is allowed to see.
     *
     * Matched on shop domain as well as client_id: a delivery parked before the
     * store was claimed has no client on it, and those are precisely the ones
     * the merchant most needs to see after connecting.
     */
    private function failureQuery(Client $client): Builder
    {
        $shops = ClientShopifyConnection::where('client_id', $client->id)->pluck('shop_domain');

        return ShopifySyncFailure::query()
            ->where(fn (Builder $q) => $q->where('client_id', $client->id)->orWhereIn('shop_domain', $shops));
    }

    /**
     * Paginated list of unresolved sync failures.
     */
    public function index(Request $request)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $failures = $this->failureQuery($client)
            ->unresolved()
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 25), 100))
            ->withQueryString();

        return response()->json($failures);
    }

    /**
     * How many orders are currently stuck — for the tab badge, so the merchant
     * sees there is something to look at without opening the panel.
     */
    public function count()
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        return response()->json([
            'count' => $this->failureQuery($client)->unresolved()->count(),
        ]);
    }

    /**
     * Replay one failure now.
     */
    public function retry(Request $request, int $failureId)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $failure = $this->failureQuery($client)->unresolved()->whereKey($failureId)->first();

        if (! $failure) {
            return response()->json(['message' => 'Sync failure not found.'], 404);
        }

        $this->dispatchReplay($failure, $client);

        return response()->json(['message' => 'Retrying this order now.', 'id' => $failure->id]);
    }

    /**
     * Replay every unresolved failure for this client.
     *
     * Chunked rather than loaded in one go: each row carries a full webhook
     * payload, so a store that has been disconnected for a day could put tens
     * of thousands of them — hundreds of megabytes — into a single web request.
     */
    public function retryAll(Request $request)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $retried = 0;

        $this->failureQuery($client)->unresolved()
            ->chunkById(200, function ($failures) use ($client, &$retried) {
                foreach ($failures as $failure) {
                    $this->dispatchReplay($failure, $client);
                    $retried++;
                }
            });

        return response()->json([
            'message' => "Retrying {$retried} order(s).",
            'retried' => $retried,
        ]);
    }

    /**
     * Stop retrying a failure the merchant does not want imported (a test
     * order, one they have already entered by hand). It stays visible as
     * abandoned rather than being deleted, and "Retry" still works on it.
     */
    public function discard(Request $request, int $failureId)
    {
        $client = $this->resolveClient();
        abort_unless($client, 403);

        $failure = $this->failureQuery($client)->unresolved()->whereKey($failureId)->first();

        if (! $failure) {
            return response()->json(['message' => 'Sync failure not found.'], 404);
        }

        $failure->forceFill([
            'status'          => ShopifySyncFailure::STATUS_ABANDONED,
            'next_attempt_at' => null,
        ])->save();

        return response()->json(['message' => 'Order will no longer be retried.', 'id' => $failure->id]);
    }

    private function dispatchReplay(ShopifySyncFailure $failure, Client $client): void
    {
        $failure->beginImmediateReplay($client->id);

        ProcessShopifyWebhookJob::dispatch(
            $failure->shop_domain,
            $failure->topic,
            $failure->payload,
            $failure->id,
        );
    }
}
