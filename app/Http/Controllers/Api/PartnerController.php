<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerLead;
use App\Models\PartnerPayoutRequest;
use App\Models\PartnerSetting;
use App\Services\Partners\PartnerApplications;
use App\Services\Partners\PartnerCode;
use App\Services\Partners\PartnerDocuments;
use App\Services\Partners\PartnerPoints;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff side of the partner program: the Partners page and its API.
 */
class PartnerController extends Controller
{
    public function __construct(
        private PartnerApplications $applications,
        private PartnerPoints $points,
    ) {}

    public function page(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Partners', [
            'partners' => $this->rows(),
            'stats' => $user->can('view partner stats') ? $this->stats() : null,
            'leads' => $user->can('view partner leads') ? $this->leadRows() : null,
            'payouts' => $user->can('view partner payouts') ? $this->payoutRows() : null,
            'settings' => $user->can('edit partner settings') ? PartnerSetting::all_() : null,
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['partners' => $this->rows()]);
    }

    public function show(Partner $partner): JsonResponse
    {
        return response()->json(['partner' => $this->detail($partner)]);
    }

    public function approve(Request $request, Partner $partner): JsonResponse
    {
        $partner = $this->applications->approve($partner, $request->user());

        return response()->json(['partner' => $this->detail($partner), 'message' => "{$partner->display_name} approved."]);
    }

    public function reject(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $partner = $this->applications->reject($partner, $request->user(), $data['reason']);

        return response()->json(['partner' => $this->detail($partner), 'message' => 'Application rejected.']);
    }

    public function status(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate(['suspended' => ['required', 'boolean']]);

        abort_if($partner->status === Partner::STATUS_PENDING || $partner->status === Partner::STATUS_REJECTED, 422, 'Decide on the application first.');

        $partner = $this->applications->suspend($partner, $request->user(), $data['suspended']);

        return response()->json(['partner' => $this->detail($partner), 'message' => $data['suspended'] ? 'Partner suspended.' : 'Partner reactivated.']);
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:20'],
            'tier' => ['sometimes', 'required', 'in:' . implode(',', Partner::TIERS)],
            'type' => ['sometimes', 'required', 'in:' . implode(',', Partner::TYPES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($data['code'])) {
            $code = PartnerCode::normalize($data['code']);

            if (! PartnerCode::isValid($code) || ! PartnerCode::isAvailable($code, $partner->id)) {
                return response()->json(['message' => 'That code is taken or invalid.', 'errors' => ['code' => ['That code is taken or invalid.']]], 422);
            }

            unset($data['code']);
            PartnerCode::change($partner, $code);
            $partner->refresh();
        }

        $partner->fill($data)->save();

        return response()->json(['partner' => $this->detail($partner->fresh()), 'message' => 'Partner updated.']);
    }

    public function adjustPoints(Request $request, Partner $partner): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $this->points->adjust($partner, (int) $data['points'], $data['note'], $request->user());

        return response()->json(['partner' => $this->detail($partner->fresh()), 'message' => 'Points adjusted.']);
    }

    public function document(Request $request, Partner $partner): StreamedResponse
    {
        return PartnerDocuments::idResponse($partner, $request->query('side') === 'back');
    }

    public function logo(Partner $partner): StreamedResponse
    {
        return PartnerDocuments::logoResponse($partner);
    }

    public function transactions(Partner $partner): JsonResponse
    {
        return response()->json([
            'transactions' => $partner->transactions()->with('createdBy:id,name', 'client:id,company_name')
                ->latest('created_at')->latest('id')->limit(200)->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'points' => $t->points,
                    'description' => $t->description,
                    'client' => $t->client?->company_name,
                    'by' => $t->createdBy?->name,
                    'created_at' => $t->created_at,
                ]),
        ]);
    }

    // ── leads ───────────────────────────────────────────────────────────────

    public function leads(): JsonResponse
    {
        return response()->json(['leads' => $this->leadRows()]);
    }

    public function updateLead(Request $request, PartnerLead $lead): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'required', 'in:' . implode(',', PartnerLead::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead->fill($data)->save();

        return response()->json(['lead' => $this->leadRow($lead->fresh('partner', 'client')), 'message' => 'Lead updated.']);
    }

    // ── settings ────────────────────────────────────────────────────────────

    public function settings(): JsonResponse
    {
        return response()->json(['settings' => PartnerSetting::all_()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'approval_bonus_points' => ['required', 'integer', 'min:0', 'max:100000'],
            'activation_points' => ['required', 'integer', 'min:0', 'max:100000'],
            'delivery_points' => ['required', 'integer', 'min:0', 'max:10000'],
            'delivery_months' => ['required', 'integer', 'min:0', 'max:120'],
            'milestone_deliveries' => ['required', 'integer', 'min:0', 'max:100000'],
            'milestone_points' => ['required', 'integer', 'min:0', 'max:100000'],
            'min_redeem_points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'silver_min_active_clients' => ['required', 'integer', 'min:1', 'max:10000'],
            'silver_multiplier' => ['required', 'numeric', 'min:1', 'max:10'],
            'gold_min_active_clients' => ['required', 'integer', 'min:1', 'max:10000', 'gt:silver_min_active_clients'],
            'gold_multiplier' => ['required', 'numeric', 'min:1', 'max:10', 'gte:silver_multiplier'],
            'terms_version' => ['required', 'string', 'max:20'],
        ]);

        PartnerSetting::put($data);

        return response()->json(['settings' => PartnerSetting::all_(), 'message' => 'Settings saved.']);
    }

    // ── rows ────────────────────────────────────────────────────────────────

    private function rows(): array
    {
        return Partner::with('user:id,name,email')
            ->withCount([
                'referredClients',
                'referredClients as active_clients_count' => fn ($q) => $q->whereNotNull('partner_activated_at'),
                'leads',
            ])
            ->orderByRaw("case status when 'pending' then 0 else 1 end")
            ->latest()
            ->get()
            ->map(fn (Partner $p) => $this->row($p))
            ->all();
    }

    private function row(Partner $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'display_name' => $p->display_name,
            'company_name' => $p->company_name,
            'type' => $p->type,
            'status' => $p->status,
            'tier' => $p->tier,
            'points_balance' => $p->points_balance,
            'phone' => $p->phone,
            'country' => $p->country,
            'city' => $p->city,
            'user' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name, 'email' => $p->user->email] : null,
            'client_id' => $p->client_id,
            'referred_clients_count' => (int) ($p->referred_clients_count ?? 0),
            'active_clients_count' => (int) ($p->active_clients_count ?? 0),
            'leads_count' => (int) ($p->leads_count ?? 0),
            'has_id_document' => $p->hasIdDocument(),
            'created_at' => $p->created_at,
            'approved_at' => $p->approved_at,
        ];
    }

    private function detail(Partner $p): array
    {
        $p->loadMissing('user:id,name,email', 'reviewer:id,name', 'client:id,company_name');
        $p->loadCount([
            'referredClients',
            'referredClients as active_clients_count' => fn ($q) => $q->whereNotNull('partner_activated_at'),
            'leads',
        ]);

        return $this->row($p) + [
            'whatsapp' => $p->whatsapp,
            'website' => $p->website,
            'social_links' => $p->social_links ?? [],
            'audience_size' => $p->audience_size,
            'promotion_plan' => $p->promotion_plan,
            'motivation' => $p->motivation,
            'id_document_type' => $p->id_document_type,
            'has_id_document_back' => (bool) $p->id_document_back_path,
            'payout_method' => $p->payout_method,
            'terms_version' => $p->terms_version,
            'terms_accepted_at' => $p->terms_accepted_at,
            'reviewed_by' => $p->reviewer?->name,
            'reviewed_at' => $p->reviewed_at,
            'rejection_reason' => $p->rejection_reason,
            'admin_notes' => $p->admin_notes,
            'own_client' => $p->client ? ['id' => $p->client->id, 'company_name' => $p->client->company_name] : null,
            'referral_url' => $p->referralUrl(),
            'referred_clients' => $p->referredClients()->get(['id', 'company_name', 'short_id', 'status', 'referred_at', 'partner_activated_at'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'company_name' => $c->company_name,
                    'client_id' => $c->client_id,
                    'status' => $c->status,
                    'referred_at' => $c->referred_at,
                    'activated_at' => $c->partner_activated_at,
                ]),
        ];
    }

    private function stats(): array
    {
        return [
            'pending' => Partner::where('status', Partner::STATUS_PENDING)->count(),
            'approved' => Partner::where('status', Partner::STATUS_APPROVED)->count(),
            'referred_clients' => \App\Models\Client::whereNotNull('partner_id')->count(),
            'active_clients' => \App\Models\Client::whereNotNull('partner_activated_at')->count(),
            'points_outstanding' => (int) Partner::sum('points_balance'),
            'open_payouts' => PartnerPayoutRequest::whereIn('status', ['requested', 'approved'])->count(),
            'new_leads' => PartnerLead::where('status', 'new')->count(),
        ];
    }

    private function leadRows(): array
    {
        return PartnerLead::with('partner:id,code,display_name', 'client:id,company_name')
            ->latest()->limit(500)->get()
            ->map(fn ($l) => $this->leadRow($l))
            ->all();
    }

    private function leadRow(PartnerLead $l): array
    {
        return [
            'id' => $l->id,
            'name' => $l->name,
            'company' => $l->company,
            'phone' => $l->phone,
            'email' => $l->email,
            'store_url' => $l->store_url,
            'monthly_orders' => $l->monthly_orders,
            'message' => $l->message,
            'notes' => $l->notes,
            'status' => $l->status,
            'source' => $l->source,
            'partner' => $l->partner ? ['id' => $l->partner->id, 'code' => $l->partner->code, 'display_name' => $l->partner->display_name] : null,
            'client' => $l->client ? ['id' => $l->client->id, 'company_name' => $l->client->company_name] : null,
            'created_at' => $l->created_at,
        ];
    }

    private function payoutRows(): array
    {
        return PartnerPayoutRequest::with('partner:id,code,display_name,points_balance', 'handledBy:id,name')
            ->orderByRaw("case status when 'requested' then 0 when 'approved' then 1 else 2 end")
            ->latest()->limit(500)->get()
            ->map(fn ($r) => PartnerPayoutController::row($r))
            ->all();
    }
}
