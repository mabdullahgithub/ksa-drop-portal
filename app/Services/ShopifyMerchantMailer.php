<?php

namespace App\Services;

use App\Mail\Shopify\ShopifyStoreConnectedMail;
use App\Mail\Shopify\ShopifyStoreDisconnectedMail;
use App\Models\Client;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tells a client's portal user when their Shopify store is linked to or
 * unlinked from their account.
 *
 * Both mailables are queued, so the calling request only writes a queue row.
 * Nothing here may break the linking or unlinking it reports on — the store's
 * state has already changed whether or not the mail goes out.
 */
class ShopifyMerchantMailer
{
    public function __construct(private EmailService $emailService) {}

    public function storeConnected(Client $client, string $shop): void
    {
        $this->send($client, $shop, new ShopifyStoreConnectedMail($client, $shop));
    }

    /**
     * @param  string  $reason  'portal' (disconnected from the portal) or
     *                          'uninstalled' (app removed in Shopify admin)
     */
    public function storeDisconnected(Client $client, string $shop, string $reason): void
    {
        $this->send($client, $shop, new ShopifyStoreDisconnectedMail($client, $shop, $reason));
    }

    private function send(Client $client, string $shop, Mailable $mail): void
    {
        try {
            if ($this->emailService->isEnabled() && $client->user) {
                $this->emailService->configureMailer();
                Mail::to($client->user->email)->send($mail);
            }
        } catch (\Throwable $e) {
            Log::channel('shopify')->error('Shopify merchant email failed', [
                'mail'      => class_basename($mail),
                'shop'      => $shop,
                'client_id' => $client->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
