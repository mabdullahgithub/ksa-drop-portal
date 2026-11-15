<?php

namespace App\Services\Partners;

use App\Mail\Partners\PartnerApplicationReceivedMail;
use App\Mail\Partners\PartnerApprovedMail;
use App\Mail\Partners\PartnerRejectedMail;
use App\Models\Client;
use App\Models\Partner;
use App\Models\PartnerSetting;
use App\Models\User;
use App\Notifications\PartnerApplicationSubmittedNotification;
use App\Services\EmailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Joining the program and deciding on applications. A new applicant gets a
 * portal user straight away (so they can sign in and watch their status);
 * a client who joins keeps the user they have.
 */
class PartnerApplications
{
    public function __construct(private PartnerPoints $points) {}

    /**
     * A public application. `$data` is the validated form; the password is
     * the applicant's own, so they can sign in at once.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(array $data, ?UploadedFile $idDocument, ?UploadedFile $idDocumentBack, ?UploadedFile $logo, ?string $ip): Partner
    {
        return DB::transaction(function () use ($data, $idDocument, $idDocumentBack, $logo, $ip) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('partner');

            $partner = $this->createPartner($user, null, $data, $ip);

            if ($idDocument) {
                PartnerDocuments::storeId($partner, $idDocument);
            }

            if ($idDocumentBack) {
                PartnerDocuments::storeIdBack($partner, $idDocumentBack);
            }

            if ($logo) {
                PartnerDocuments::storeLogo($partner, $logo);
            }

            $this->afterSubmit($partner);

            return $partner;
        });
    }

    /**
     * An existing client switching partner mode on from their portal.
     *
     * @param  array<string, mixed>  $data
     */
    public function join(Client $client, array $data, ?UploadedFile $idDocument, ?string $ip): Partner
    {
        return DB::transaction(function () use ($client, $data, $idDocument, $ip) {
            $user = $client->user;
            $user->assignRole('partner');

            $partner = $this->createPartner($user, $client, $data + [
                'type' => Partner::TYPE_CLIENT,
                'name' => $user->name,
                'company_name' => $client->company_name,
                'phone' => $data['phone'] ?? $client->phone ?? '',
                'country' => $client->country ?? 'SA',
                'city' => $client->city,
            ], $ip);

            if ($idDocument) {
                PartnerDocuments::storeId($partner, $idDocument);
            }

            $this->afterSubmit($partner);

            return $partner;
        });
    }

    public function approve(Partner $partner, User $by): Partner
    {
        abort_if($partner->status === Partner::STATUS_APPROVED, 422, 'This partner is already approved.');

        $firstApproval = $partner->approved_at === null;

        $partner->forceFill([
            'status' => Partner::STATUS_APPROVED,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'approved_at' => $partner->approved_at ?? now(),
            'rejection_reason' => null,
        ])->save();

        if ($firstApproval) {
            $this->points->approvalBonus($partner, $by);
            $this->send($partner, new PartnerApprovedMail($partner));
        }

        return $partner->fresh();
    }

    public function reject(Partner $partner, User $by, string $reason): Partner
    {
        $partner->forceFill([
            'status' => Partner::STATUS_REJECTED,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->send($partner, new PartnerRejectedMail($partner));

        return $partner;
    }

    public function suspend(Partner $partner, User $by, bool $suspend): Partner
    {
        $partner->forceFill([
            'status' => $suspend ? Partner::STATUS_SUSPENDED : Partner::STATUS_APPROVED,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ])->save();

        return $partner;
    }

    /** @param  array<string, mixed>  $data */
    private function createPartner(User $user, ?Client $client, array $data, ?string $ip): Partner
    {
        $code = ! empty($data['code'])
            ? PartnerCode::normalize($data['code'])
            : PartnerCode::generate($data['company_name'] ?? $data['name']);

        return Partner::create([
            'user_id' => $user->id,
            'client_id' => $client?->id,
            'type' => $data['type'],
            'status' => Partner::STATUS_PENDING,
            'code' => $code,
            'display_name' => $data['display_name'] ?? $data['company_name'] ?? $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'country' => $data['country'] ?? 'SA',
            'city' => $data['city'] ?? null,
            'website' => $data['website'] ?? null,
            'social_links' => array_filter($data['social_links'] ?? []),
            'audience_size' => $data['audience_size'] ?? null,
            'promotion_plan' => $data['promotion_plan'] ?? null,
            'motivation' => $data['motivation'] ?? null,
            'id_document_type' => $data['id_document_type'] ?? null,
            'payout_method' => ! empty($data['payout_method']['type']) ? $data['payout_method'] : null,
            'terms_version' => PartnerSetting::get('terms_version'),
            'terms_accepted_at' => now(),
            'applied_ip' => $ip,
        ]);
    }

    private function afterSubmit(Partner $partner): void
    {
        $this->send($partner, new PartnerApplicationReceivedMail($partner));

        try {
            User::role(['admin', 'superadmin'])->each(
                fn (User $admin) => $admin->notify(new PartnerApplicationSubmittedNotification($partner))
            );
        } catch (\Throwable $e) {
            \Log::warning('Partner application notification failed: ' . $e->getMessage());
        }
    }

    private function send(Partner $partner, \Illuminate\Mail\Mailable $mail): void
    {
        try {
            $emailService = app(EmailService::class);
            if ($emailService->isEnabled()) {
                $emailService->configureMailer();
                Mail::to($partner->user->email)->send($mail);
            }
        } catch (\Throwable $e) {
            \Log::error('Partner mail failed for partner ' . $partner->id . ': ' . $e->getMessage());
        }
    }
}
