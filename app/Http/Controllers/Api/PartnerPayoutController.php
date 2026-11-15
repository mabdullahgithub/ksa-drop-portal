<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\Partners\PartnerPayoutPaidMail;
use App\Models\PartnerPayoutRequest;
use App\Services\EmailService;
use App\Services\Partners\PartnerPayouts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerPayoutController extends Controller
{
    public function __construct(private PartnerPayouts $payouts) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'payouts' => PartnerPayoutRequest::with('partner:id,code,display_name,points_balance', 'handledBy:id,name')
                ->orderByRaw("case status when 'requested' then 0 when 'approved' then 1 else 2 end")
                ->latest()->limit(500)->get()
                ->map(fn ($r) => self::row($r)),
        ]);
    }

    public function approve(Request $request, PartnerPayoutRequest $payout): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $payout = $this->payouts->approve($payout, $request->user(), $data['note'] ?? null);

        return response()->json(['payout' => self::row($payout->fresh('partner', 'handledBy')), 'message' => 'Payout approved; points deducted.']);
    }

    public function pay(Request $request, PartnerPayoutRequest $payout): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $payout = $this->payouts->pay($payout, $request->user(), (float) $data['amount'], $data['currency'], $request->file('proof'), $data['note'] ?? null);

        try {
            $emailService = app(EmailService::class);
            if ($emailService->isEnabled()) {
                $emailService->configureMailer();
                Mail::to($payout->partner->user->email)->send(new PartnerPayoutPaidMail($payout->partner, $payout));
            }
        } catch (\Throwable $e) {
            \Log::error('Partner payout mail failed for payout ' . $payout->id . ': ' . $e->getMessage());
        }

        return response()->json(['payout' => self::row($payout->fresh('partner', 'handledBy')), 'message' => 'Payout marked as paid.']);
    }

    public function reject(Request $request, PartnerPayoutRequest $payout): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $payout = $this->payouts->reject($payout, $request->user(), $data['reason']);

        return response()->json(['payout' => self::row($payout->fresh('partner', 'handledBy')), 'message' => 'Payout rejected.']);
    }

    public function proof(PartnerPayoutRequest $payout): StreamedResponse
    {
        return $this->payouts->proofResponse($payout);
    }

    public static function row(PartnerPayoutRequest $r): array
    {
        return [
            'id' => $r->id,
            'points' => $r->points,
            'status' => $r->status,
            'payout_method' => $r->payout_method,
            'partner_note' => $r->partner_note,
            'admin_note' => $r->admin_note,
            'amount_paid' => $r->amount_paid,
            'currency' => $r->currency,
            'paid_at' => $r->paid_at,
            'has_proof' => (bool) $r->proof_path,
            'handled_by' => $r->handledBy?->name,
            'partner' => $r->partner ? [
                'id' => $r->partner->id,
                'code' => $r->partner->code,
                'display_name' => $r->partner->display_name,
                'points_balance' => $r->partner->points_balance,
            ] : null,
            'created_at' => $r->created_at,
        ];
    }
}
