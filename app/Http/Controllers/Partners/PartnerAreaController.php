<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerSetting;
use App\Models\User;
use App\Notifications\PartnerPayoutRequestedNotification;
use App\Services\Partners\PartnerApplications;
use App\Services\Partners\PartnerCode;
use App\Services\Partners\PartnerDocuments;
use App\Services\Partners\PartnerPayouts;
use App\Services\Partners\PartnerPoints;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in partner's own area at /partners/*: their link, referrals,
 * points, payouts and profile. Never shows a referred client's money, only
 * whether they are active and how many deliveries counted.
 */
class PartnerAreaController extends Controller
{
    public function pending(Request $request): Response
    {
        $partner = $request->user()->partner;

        if ($partner?->isApproved()) {
            return Inertia::location(route('partners.dashboard'));
        }

        return Inertia::render('Partners/Pending', [
            'partner' => $partner ? [
                'status' => $partner->status,
                'type' => $partner->type,
                'code' => $partner->code,
                'rejection_reason' => $partner->rejection_reason,
                'created_at' => $partner->created_at,
            ] : null,
            'isClient' => $request->user()->hasRole('client'),
        ]);
    }

    public function dashboard(Request $request, PartnerPayouts $payouts, PartnerPoints $points): Response
    {
        $partner = $this->partner($request);
        $settings = PartnerSetting::all_();

        $active = $partner->activeReferralCount();
        $nextTier = match ($partner->tier) {
            'bronze' => ['name' => 'Silver', 'at' => $settings['silver_min_active_clients']],
            'silver' => ['name' => 'Gold', 'at' => $settings['gold_min_active_clients']],
            default => null,
        };

        return Inertia::render('Partners/Dashboard', [
            'partner' => $this->summary($partner),
            'stats' => [
                'points_balance' => $partner->points_balance,
                'available_points' => $payouts->available($partner),
                'referred_clients' => $partner->referredClients()->count(),
                'active_clients' => $active,
                'leads' => $partner->leads()->count(),
                'deliveries_this_month' => $partner->transactions()
                    ->where('type', 'delivery')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
                'points_this_month' => (int) $partner->transactions()
                    ->where('points', '>', 0)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('points'),
                'tier' => $partner->tier,
                'multiplier' => $points->multiplierFor($partner->tier),
                'next_tier' => $nextTier,
                'min_redeem_points' => $settings['min_redeem_points'],
            ],
            'recent' => $partner->transactions()->latest('created_at')->limit(8)->get()
                ->map(fn ($t) => $this->transactionRow($t)),
            'program' => PublicPartnerController::programSummary(),
        ]);
    }

    public function referrals(Request $request): Response
    {
        $partner = $this->partner($request);

        $clients = $partner->referredClients()
            ->withCount(['orders as delivered_count' => fn ($q) => $q->whereHas('shipments', fn ($s) => $s->where('status', 'delivered'))])
            ->orderByDesc('referred_at')
            ->get()
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => $client->company_name,
                'status' => $client->status,
                'referred_at' => $client->referred_at,
                'activated_at' => $client->partner_activated_at,
                'delivered_count' => (int) $client->delivered_count,
                'earning_until' => $client->partner_activated_at?->copy()->addMonths((int) PartnerSetting::get('delivery_months')),
            ]);

        $leads = $partner->leads()->latest()->get()->map(fn ($lead) => [
            'id' => $lead->id,
            'name' => $lead->name,
            'company' => $lead->company,
            'status' => $lead->status,
            'created_at' => $lead->created_at,
        ]);

        return Inertia::render('Partners/Referrals', [
            'partner' => $this->summary($partner),
            'clients' => $clients,
            'leads' => $leads,
        ]);
    }

    public function points(Request $request, PartnerPayouts $payouts, PartnerPoints $points): Response
    {
        $partner = $this->partner($request);
        $settings = PartnerSetting::all_();

        $transactions = $partner->transactions()->latest('created_at')->latest('id')->paginate(25)
            ->through(fn ($t) => $this->transactionRow($t));

        $monthStart = now()->startOfMonth();
        $earned = fn () => $partner->transactions()->where('points', '>', 0);

        // Earnings per month for the last six months, grouped in PHP so the
        // query stays the same on every database.
        $since = $monthStart->copy()->subMonths(5);
        $byMonth = $earned()->where('created_at', '>=', $since)->get(['points', 'created_at'])
            ->groupBy(fn ($t) => $t->created_at->format('Y-m'))
            ->map(fn ($rows) => (int) $rows->sum('points'));

        $monthly = collect(range(0, 5))->map(function ($i) use ($since, $byMonth) {
            $m = $since->copy()->addMonths($i);

            return ['label' => $m->format('M'), 'points' => (int) ($byMonth[$m->format('Y-m')] ?? 0)];
        })->all();

        $breakdown = $earned()->selectRaw('type, SUM(points) as total, COUNT(*) as entries')
            ->groupBy('type')->get()
            ->map(fn ($r) => ['type' => $r->type, 'points' => (int) $r->total, 'entries' => (int) $r->entries])
            ->values();

        $active = $partner->activeReferralCount();
        $nextTier = match ($partner->tier) {
            'bronze' => ['name' => 'Silver', 'at' => (int) $settings['silver_min_active_clients']],
            'silver' => ['name' => 'Gold', 'at' => (int) $settings['gold_min_active_clients']],
            default => null,
        };

        return Inertia::render('Partners/Points', [
            'partner' => $this->summary($partner),
            'transactions' => $transactions,
            'stats' => [
                'balance' => (int) $partner->points_balance,
                'available' => $payouts->available($partner),
                'on_hold' => max(0, (int) $partner->points_balance - $payouts->available($partner)),
                'lifetime_earned' => (int) $earned()->sum('points'),
                'redeemed' => abs((int) $partner->transactions()->where('type', 'payout')->sum('points')),
                'this_month' => (int) $earned()->where('created_at', '>=', $monthStart)->sum('points'),
                'last_month' => (int) $earned()
                    ->where('created_at', '>=', $monthStart->copy()->subMonth())
                    ->where('created_at', '<', $monthStart)
                    ->sum('points'),
                'min_redeem' => (int) $settings['min_redeem_points'],
                'tier' => $partner->tier,
                'multiplier' => $points->multiplierFor($partner->tier),
                'active_clients' => $active,
                'next_tier' => $nextTier,
                'monthly' => $monthly,
                'breakdown' => $breakdown,
            ],
            'program' => PublicPartnerController::programSummary(),
        ]);
    }

    /** The partner's one chance to pick their own code. */
    public function updateCode(Request $request)
    {
        $partner = $this->partner($request);

        abort_unless($partner->isApproved(), 403);

        if ($partner->code_customized_at) {
            return back()->withErrors(['code' => 'You have already customized your code. Contact our team to change it again.']);
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);
        $code = PartnerCode::normalize($data['code']);

        if (! PartnerCode::isValid($code)) {
            return back()->withErrors(['code' => 'Use 4–20 letters and digits only.']);
        }

        if ($code === $partner->code) {
            return back()->withErrors(['code' => 'That is already your code.']);
        }

        if (! PartnerCode::isAvailable($code, $partner->id)) {
            return back()->withErrors(['code' => 'That code is taken. Try another.']);
        }

        PartnerCode::change($partner, $code, customized: true);

        return back()->with('success', 'Your code is now ' . $code . '. Links with your old code still work.');
    }

    public function payouts(Request $request, PartnerPayouts $payouts): Response
    {
        $partner = $this->partner($request);

        return Inertia::render('Partners/Payouts', [
            'partner' => $this->summary($partner),
            'available' => $payouts->available($partner),
            'minRedeem' => (int) PartnerSetting::get('min_redeem_points'),
            'hasPayoutMethod' => ! empty($partner->payout_method['type']),
            'payoutMethod' => ! empty($partner->payout_method['type']) ? $partner->payout_method : null,
            'requests' => $partner->payoutRequests()->latest()->get()->map(fn ($r) => [
                'id' => $r->id,
                'points' => $r->points,
                'status' => $r->status,
                'partner_note' => $r->partner_note,
                'admin_note' => $r->admin_note,
                'amount_paid' => $r->amount_paid,
                'currency' => $r->currency,
                'paid_at' => $r->paid_at,
                'created_at' => $r->created_at,
                'method' => $r->payout_method['type'] ?? null,
            ]),
        ]);
    }

    public function requestPayout(Request $request, PartnerPayouts $payouts)
    {
        $partner = $this->partner($request);

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payout = $payouts->request($partner, (int) $data['points'], $data['note'] ?? null);

        try {
            User::role(['admin', 'superadmin'])->each(
                fn (User $admin) => $admin->notify(new PartnerPayoutRequestedNotification($payout))
            );
        } catch (\Throwable $e) {
            \Log::warning('Partner payout notification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Payout requested. We will review it and transfer within the next cycle.');
    }

    public function profile(Request $request): Response
    {
        $partner = $this->partner($request);

        return Inertia::render('Partners/Profile', [
            'partner' => $this->summary($partner) + [
                'company_name' => $partner->company_name,
                'phone' => $partner->phone,
                'whatsapp' => $partner->whatsapp,
                'country' => $partner->country,
                'city' => $partner->city,
                'website' => $partner->website,
                'social_links' => $partner->social_links ?? [],
                'audience_size' => $partner->audience_size,
                'payout_method' => $partner->payout_method,
                'has_id_document' => $partner->hasIdDocument(),
                'terms_version' => $partner->terms_version,
                'terms_accepted_at' => $partner->terms_accepted_at,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $partner = $this->partner($request);

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['nullable', 'string', 'max:255'],
            'audience_size' => ['nullable', 'string', 'max:30'],
            'logo' => PartnerDocuments::LOGO_RULES,
        ]);

        $partner->fill(collect($data)->except('logo')->all())->save();

        if ($request->hasFile('logo')) {
            PartnerDocuments::storeLogo($partner, $request->file('logo'));
        }

        return back()->with('success', 'Profile saved.');
    }

    public function updatePayoutMethod(Request $request)
    {
        $partner = $this->partner($request);

        $data = $request->validate([
            'type' => ['required', 'in:bank,other'],
            'bank_name' => ['required_if:type,bank', 'nullable', 'string', 'max:100'],
            'holder_name' => ['required_if:type,bank', 'nullable', 'string', 'max:255'],
            'iban' => ['required_if:type,bank', 'nullable', 'string', 'max:34'],
            'details' => ['required_if:type,other', 'nullable', 'string', 'max:500'],
        ]);

        $partner->forceFill(['payout_method' => $data])->save();

        return back()->with('success', 'Payout method saved.');
    }

    public function assets(Request $request): Response
    {
        $partner = $this->partner($request);

        return Inertia::render('Partners/Assets', [
            'partner' => $this->summary($partner),
        ]);
    }

    /**
     * The client portal's "Refer & earn" page: one tabbed screen that covers
     * every state (not joined, pending, approved) so the sidebar link never
     * dead-ends. Approved partners get their link, earnings and referrals.
     */
    public function referAndEarn(Request $request, PartnerPayouts $payouts, PartnerPoints $points): Response
    {
        $client = $this->portalClient($request);
        $partner = $client->partnerAccount;
        $settings = PartnerSetting::all_();
        $program = PublicPartnerController::programSummary();

        $props = [
            'program' => $program,
            'state' => $partner ? ($partner->isApproved() ? 'approved' : $partner->status) : 'none',
            'canManage' => ! session()->has('impersonate.admin_id'),
            'partner' => null,
        ];

        if (! $partner || ! $partner->isApproved()) {
            return Inertia::render('Portal/ReferAndEarn', $props);
        }

        $active = $partner->activeReferralCount();
        $nextTier = match ($partner->tier) {
            'bronze' => ['name' => 'Silver', 'at' => $settings['silver_min_active_clients']],
            'silver' => ['name' => 'Gold', 'at' => $settings['gold_min_active_clients']],
            default => null,
        };

        $referred = $partner->referredClients()
            ->orderByDesc('referred_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->company_name,
                'activated' => (bool) $c->partner_activated_at,
                'referred_at' => $c->referred_at,
                'earning_until' => $c->partner_activated_at?->copy()->addMonths((int) $settings['delivery_months']),
            ]);

        return Inertia::render('Portal/ReferAndEarn', array_merge($props, [
            'partner' => $this->summary($partner),
            'stats' => [
                'available_points' => $payouts->available($partner),
                'referred_clients' => $referred->count(),
                'active_clients' => $active,
                'leads' => $partner->leads()->count(),
                'points_this_month' => (int) $partner->transactions()
                    ->where('points', '>', 0)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('points'),
                'tier' => $partner->tier,
                'multiplier' => $points->multiplierFor($partner->tier),
                'next_tier' => $nextTier,
                'min_redeem_points' => (int) $settings['min_redeem_points'],
                'total_paid_points' => (int) $partner->payoutRequests()->where('status', 'paid')->sum('points'),
            ],
            'hasPayoutMethod' => ! empty($partner->payout_method['type']),
            'clients' => $referred,
            'payouts' => $partner->payoutRequests()->latest()->get()->map(fn ($r) => [
                'id' => $r->id,
                'points' => $r->points,
                'status' => $r->status,
                'amount_paid' => $r->amount_paid,
                'currency' => $r->currency,
                'paid_at' => $r->paid_at,
                'created_at' => $r->created_at,
            ]),
            'recent' => $partner->transactions()->latest('created_at')->limit(8)->get()
                ->map(fn ($t) => $this->transactionRow($t)),
        ]));
    }

    // ── A client joining from their portal ──────────────────────────────────

    public function joinForm(Request $request): Response
    {
        $client = $this->portalClient($request);

        if ($client->partnerAccount) {
            // An admin impersonating the client cannot open the partner area.
            return session()->has('impersonate.admin_id')
                ? Inertia::location(route('portal.dashboard'))
                : Inertia::location(route('partners.dashboard'));
        }

        return Inertia::render('Portal/PartnerJoin', [
            'program' => PublicPartnerController::programSummary(),
            'suggestedCode' => PartnerCode::generate($client->company_name),
            'client' => [
                'company_name' => $client->company_name,
                'phone' => $client->phone,
                'has_cr' => (bool) $client->commercial_registration,
            ],
        ]);
    }

    public function join(Request $request, PartnerApplications $applications)
    {
        abort_if(
            session()->has('impersonate.admin_id'),
            403,
            'Partner applications must be submitted by the client, not while impersonating.'
        );

        $client = $this->portalClient($request);
        abort_if($client->partnerAccount, 422, 'You already joined the partner program.');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'promotion_plan' => ['required', 'string', 'min:20', 'max:3000'],
            'motivation' => ['nullable', 'string', 'max:5000'],
            'id_document_type' => ['nullable', 'in:' . implode(',', Partner::ID_DOCUMENT_TYPES)],
            'id_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'terms' => ['accepted'],
        ]);

        if (! empty($data['code'])) {
            $code = PartnerCode::normalize($data['code']);

            if (! PartnerCode::isValid($code) || ! PartnerCode::isAvailable($code)) {
                return back()->withErrors(['code' => 'That code is taken or invalid. Use 4–20 letters and digits.'])->withInput();
            }

            $data['code'] = $code;
        }

        $applications->join($client, $data, $request->file('id_document'), $request->ip());

        return redirect()->route('partners.pending')->with('success', 'Thanks for joining. We will activate your link once reviewed.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** The client behind the portal: the impersonated one for admins, else the user's own. */
    private function portalClient(Request $request): \App\Models\Client
    {
        $client = session()->has('impersonate.admin_id')
            ? \App\Models\Client::find(session('impersonate.client_id'))
            : $request->user()->client;

        abort_unless($client, 404, 'No client account found.');

        return $client;
    }

    private function partner(Request $request): Partner
    {
        return $request->user()->partner;
    }

    private function summary(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'code' => $partner->code,
            'code_can_change' => $partner->isApproved() && ! $partner->code_customized_at,
            'previous_codes' => PartnerCode::previous($partner),
            'display_name' => $partner->display_name,
            'type' => $partner->type,
            'status' => $partner->status,
            'tier' => $partner->tier,
            'points_balance' => $partner->points_balance,
            'referral_url' => $partner->referralUrl(),
            'logo_url' => PartnerDocuments::logoUrl($partner),
            'is_client' => (bool) $partner->client_id,
        ];
    }

    private function transactionRow($t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type,
            'points' => $t->points,
            'description' => $t->description,
            'created_at' => $t->created_at,
        ];
    }
}
