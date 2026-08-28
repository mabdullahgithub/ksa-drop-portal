<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\MetaWhatsAppService;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Throwable;

/**
 * Smoke test for the WhatsApp Cloud API wiring.
 *
 * Proves four things at once that are otherwise only testable by putting a real
 * order through the flow: the credentials authenticate, the recipient is
 * reachable, the template exists and is approved, and the parameter count
 * matches what Meta expects.
 *
 * Deliberately writes nothing to whatsapp_messages — it has no order to attach
 * to, and a customer-facing send with no audit trail is what that table exists
 * to prevent. Use it for setup verification only.
 *
 *   php artisan whatsapp:test-send +923395904280
 *   php artisan whatsapp:test-send +966501234567 --template=order_pending \
 *       --language=ar --var=Zain --var=KSA-1001 --var="Al Olaya, Riyadh"
 */
class SendTestWhatsAppMessage extends Command
{
    protected $signature = 'whatsapp:test-send
                            {phone : Recipient in any format — normalised to E.164}
                            {--template=hello_world : Approved template name}
                            {--language=en_US : Template language code}
                            {--var=* : Positional body variables, in order}';

    protected $description = 'Send one WhatsApp template to verify the Meta Cloud API setup';

    public function handle(MetaWhatsAppService $whatsapp): int
    {
        $to = PhoneNumber::toE164($this->argument('phone'));

        if (! $to) {
            $this->error('Could not normalise that number to E.164.');
            $this->line('Supported country codes are listed in PhoneNumber::NSN_LENGTH.');

            return self::FAILURE;
        }

        if (! $whatsapp->isConfigured()) {
            $this->error('WhatsApp is not configured — set the Phone Number ID and Access Token.');

            return self::FAILURE;
        }

        $template = (string) $this->option('template');
        $language = (string) $this->option('language');
        $variables = $this->option('var');

        $this->line("Sending <info>{$template}</info> ({$language}) to <info>{$to}</info>…");

        try {
            $response = $whatsapp->sendTestTemplate($to, $template, $language, $variables);
        } catch (Throwable $e) {
            $this->error('Send failed: ' . $e->getMessage());
            $this->newLine();
            $this->line('Common causes:');
            $this->line('  <comment>131030</comment>  recipient not in the test number\'s allow-list');
            $this->line('  <comment>132001</comment>  template name or language does not exist');
            $this->line('  <comment>132000</comment>  wrong number of --var values for the template');
            $this->line('  <comment>190</comment>     access token expired (dashboard tokens last ~24h)');

            return self::FAILURE;
        }

        $this->info('Accepted by Meta.');
        $this->line('  message id: ' . ($response['messages'][0]['id'] ?? '?'));
        $this->line('  status:     ' . ($response['messages'][0]['message_status'] ?? '?'));
        $this->newLine();
        $this->line('Delivery receipts only arrive if the webhook is registered and');
        $this->line('WHATSAPP_APP_SECRET is set — check storage/logs/whatsapp.log.');

        return self::SUCCESS;
    }
}
