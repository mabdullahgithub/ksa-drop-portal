<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Mail\Partners\PartnerNewLeadMail;
use App\Models\Partner;
use App\Models\PartnerLead;
use App\Models\PartnerSetting;
use App\Models\User;
use App\Notifications\PartnerLeadReceivedNotification;
use App\Services\EmailService;
use App\Services\Partners\PartnerApplications;
use App\Services\Partners\PartnerCode;
use App\Services\Partners\PartnerDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of the partner program: the landing page, the application
 * form, and each partner's referral page at /r/CODE.
 */
class PublicPartnerController extends Controller
{
    public const REFERRAL_COOKIE = 'ksad_ref';

    public function landing(): Response
    {
        return Inertia::render('Partners/Landing', [
            'program' => self::programSummary(),
        ]);
    }

    public function applyForm(Request $request): Response
    {
        // Someone already signed in as a client joins from their portal
        // instead, where their details are known.
        if ($request->user()?->hasRole('client')) {
            return Inertia::render('Partners/Apply', ['program' => self::programSummary(), 'redirectToPortalJoin' => true]);
        }

        return Inertia::render('Partners/Apply', [
            'program' => self::programSummary(),
            'types' => Partner::TYPES,
            'idDocumentTypes' => Partner::ID_DOCUMENT_TYPES,
            'applied' => (bool) $request->session()->pull('partner_applied', false),
        ]);
    }

    public function apply(Request $request, PartnerApplications $applications)
    {
        // A honeypot: bots fill every field, people never see this one.
        if ($request->filled('website_url_confirm')) {
            return redirect()->route('partners.landing');
        }

        $data = $request->validate([
            'type' => ['required', 'in:' . implode(',', [Partner::TYPE_AGENCY, Partner::TYPE_INFLUENCER, Partner::TYPE_CLIENT])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => ['required', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.tiktok' => ['nullable', 'string', 'max:255'],
            'social_links.youtube' => ['nullable', 'string', 'max:255'],
            'social_links.snapchat' => ['nullable', 'string', 'max:255'],
            'social_links.x' => ['nullable', 'string', 'max:255'],
            'social_links.linkedin' => ['nullable', 'string', 'max:255'],
            'social_links.telegram' => ['nullable', 'string', 'max:255'],
            'social_links.other' => ['nullable', 'string', 'max:255'],
            'audience_size' => ['nullable', 'string', 'max:30'],
            'promotion_plan' => ['nullable', 'string', 'max:3000'],
            'motivation' => ['nullable', 'string', 'max:5000'],
            'id_document_type' => ['nullable', 'in:' . implode(',', Partner::ID_DOCUMENT_TYPES)],
            'id_document' => PartnerDocuments::ID_RULES,
            'id_document_back' => PartnerDocuments::ID_RULES,
            'logo' => PartnerDocuments::LOGO_RULES,
            'code' => ['nullable', 'string', 'max:20'],
            'payout_method' => ['nullable', 'array'],
            'payout_method.type' => ['nullable', 'in:bank,other'],
            'payout_method.bank_name' => ['nullable', 'string', 'max:100'],
            'payout_method.holder_name' => ['nullable', 'string', 'max:255'],
            'payout_method.iban' => ['nullable', 'string', 'max:34'],
            'payout_method.details' => ['nullable', 'string', 'max:500'],
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'An account already exists for this email. Sign in and join from your portal instead.',
        ]);

        if (! empty($data['code'])) {
            $code = PartnerCode::normalize($data['code']);

            if (! PartnerCode::isValid($code) || ! PartnerCode::isAvailable($code)) {
                return back()->withErrors(['code' => 'That code is taken or invalid. Use 4–20 letters and digits.'])->withInput();
            }

            $data['code'] = $code;
        }

        $partner = $applications->apply($data, $request->file('id_document'), $request->file('id_document_back'), $request->file('logo'), $request->ip());

        Auth::login($partner->user);
        $request->session()->regenerate();

        // The apply page shows a short "done" screen, then returns to /partners.
        return redirect()->route('partners.apply')->with('partner_applied', true);
    }

    /** The referral page: sets the attribution cookie and offers the lead form. */
    public function referral(Request $request, string $code)
    {
        $partner = PartnerCode::find($code);

        if (! $partner) {
            return redirect()->route('partners.landing');
        }

        $days = (int) PartnerSetting::get('cookie_days');

        // Attribution for a visitor who comes back later without the link.
        cookie()->queue(cookie(self::REFERRAL_COOKIE, $partner->code, $days * 24 * 60, httpOnly: false));

        return Inertia::render('Partners/Referral', [
            'partner' => [
                'code' => $partner->code,
                'display_name' => $partner->display_name,
                'type' => $partner->type,
                'logo_url' => PartnerDocuments::logoUrl($partner),
            ],
            'whatsapp' => $this->whatsAppUrl($partner),
        ]);
    }

    public function lead(Request $request, string $code)
    {
        if ($request->filled('website_url_confirm')) {
            return back();
        }

        $partner = PartnerCode::find($code);
        abort_unless($partner, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'store_url' => ['nullable', 'string', 'max:255'],
            'monthly_orders' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $partner->leads()->create($data + ['source' => 'link', 'ip' => $request->ip()]);

        try {
            User::role(['admin', 'superadmin'])->each(
                fn (User $admin) => $admin->notify(new PartnerLeadReceivedNotification($lead))
            );

            $emailService = app(EmailService::class);
            if ($emailService->isEnabled()) {
                $emailService->configureMailer();
                Mail::to($partner->user->email)->send(new PartnerNewLeadMail($partner, $lead));
            }
        } catch (\Throwable $e) {
            \Log::warning('Partner lead notifications failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Thanks! Our team will contact you shortly to open your account.');
    }

    public function logo(string $code)
    {
        $partner = PartnerCode::find($code);
        abort_unless($partner, 404);

        return PartnerDocuments::logoResponse($partner);
    }

    /** What the public pages say about the program: the live numbers. */
    public static function programSummary(): array
    {
        $s = PartnerSetting::all_();

        return [
            'approval_bonus_points' => $s['approval_bonus_points'],
            'activation_points' => $s['activation_points'],
            'delivery_points' => $s['delivery_points'],
            'delivery_months' => $s['delivery_months'],
            'milestone_deliveries' => $s['milestone_deliveries'],
            'milestone_points' => $s['milestone_points'],
            'min_redeem_points' => $s['min_redeem_points'],
            'tiers' => [
                ['name' => 'Bronze', 'min_active_clients' => 0, 'multiplier' => 1.0],
                ['name' => 'Silver', 'min_active_clients' => $s['silver_min_active_clients'], 'multiplier' => $s['silver_multiplier']],
                ['name' => 'Gold', 'min_active_clients' => $s['gold_min_active_clients'], 'multiplier' => $s['gold_multiplier']],
            ],
            'terms_version' => $s['terms_version'],
        ];
    }

    private function whatsAppUrl(Partner $partner): string
    {
        $text = "Hi KSA Drop, I'd like to open a merchant account. Referral code: {$partner->code}";

        return 'https://wa.me/966503007729?text=' . rawurlencode($text);
    }
}
