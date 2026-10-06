<?php

namespace App\Console\Commands;

use App\Models\EmailGraveyard;
use App\Services\EmailService;
use App\Services\Mail\AddressNotFound;
use App\Services\Mail\BounceReport;
use App\Services\Mail\ImapMailbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reads "address not found" bounce emails out of the sending mailbox and
 * moves the addresses they name to the email graveyard.
 *
 * Needed because most providers (Gmail above all) accept a message for an
 * address that does not exist and only say so afterwards, by email. Nothing
 * fails while we are sending, so the SMTP conversation alone never finds out.
 */
class ProcessMailBounces extends Command
{
    protected $signature = 'mail:process-bounces
        {--dry-run : Show what would be buried without changing anything}
        {--days=3 : How far back to look when no earlier run left a position}
        {--limit=50 : Most bounce emails to read in one run}';

    protected $description = 'Bury email addresses that bounced as not found';

    private int $buried = 0;

    public function handle(EmailService $emailService, ImapMailbox $mailbox): int
    {
        if (! config('mail.bounces.enabled', true)) {
            $this->info('Bounce checking is switched off (MAIL_BOUNCE_CHECK=false).');

            return Command::SUCCESS;
        }

        // Pick up SMTP settings saved in the admin UI, the same way sending does.
        $emailService->configureMailer();

        $settings = $this->mailboxSettings();

        if ($settings === null) {
            $this->info('No mailbox to check: mail is not sent over SMTP, or the IMAP host is unknown (set MAIL_BOUNCE_IMAP_HOST).');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = $batch = [];
        $cursorKey = 'mail-bounces:position:'.md5($settings['host'].'|'.$settings['username'].'|'.$settings['folder']);

        try {
            $mailbox->connect($settings['host'], $settings['port']);
            $mailbox->login($settings['username'], $settings['password']);
            $folder = $mailbox->examine($settings['folder']);

            // UIDs only mean anything within one UIDVALIDITY; if the server
            // renumbered the folder, the saved position is worthless.
            $cursor = Cache::get($cursorKey);
            $after = is_array($cursor) && ($cursor['uidvalidity'] ?? null) === $folder['uidvalidity']
                ? (int) $cursor['uid']
                : 0;

            $from = 'OR FROM "mailer-daemon" FROM "postmaster"';
            $uids = $mailbox->search($after > 0
                ? 'UID '.($after + 1).":* {$from}"
                : 'SINCE '.now()->subDays(max(1, (int) $this->option('days')))->format('j-M-Y')." {$from}");

            // "n:*" always matches the newest message, even one below n.
            $uids = array_values(array_filter($uids, fn (int $uid) => $uid > $after));
            $batch = array_slice($uids, 0, max(1, (int) $this->option('limit')));

            foreach ($batch as $uid) {
                array_push($rows, ...$this->process($uid, $mailbox->fetch($uid), $dryRun));
            }

            if (! $dryRun) {
                // Caught up: skip past everything in the folder, bounce or
                // not. Cut short by --limit: resume right after the last one.
                $position = count($batch) < count($uids)
                    ? end($batch)
                    : max($after, ($folder['uidnext'] ?? 1) - 1, $batch ? end($batch) : 0);

                Cache::forever($cursorKey, ['uidvalidity' => $folder['uidvalidity'], 'uid' => $position]);
            }
        } catch (\Throwable $e) {
            Log::channel('mail')->warning('Bounce check failed', [
                'host' => $settings['host'],
                'username' => $settings['username'],
                'error' => $e->getMessage(),
            ]);

            $this->error('Bounce check failed: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            $mailbox->logout();
        }

        if ($rows !== []) {
            $this->table(['Message', 'Address', 'Status', 'Outcome'], $rows);
        }

        $this->info(($dryRun ? 'Dry run. ' : 'Done. ').'Bounce emails read: '.count($batch).', addresses '.($dryRun ? 'that would be buried' : 'buried').": {$this->buried}");

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array{0: int, 1: string, 2: string, 3: string}> one row per failed recipient
     */
    private function process(int $uid, string $raw, bool $dryRun): array
    {
        if (! BounceReport::isDeliveryReport($raw)) {
            return [];
        }

        $rows = [];

        foreach (BounceReport::failures($raw) as $failure) {
            $named = $failure['recipients'][0] ?? '?';
            $status = $failure['status'] ?? '-';

            if (! AddressNotFound::matches($failure['status'], $failure['diagnostic'])) {
                $rows[] = [$uid, $named, $status, 'left alone — not an address-not-found failure'];

                continue;
            }

            $recipient = BounceReport::verifiedRecipient($raw, $failure['recipients']);

            if ($recipient === null) {
                // No token of ours quoted back: either it predates the token
                // header or somebody wrote this report by hand.
                Log::channel('mail')->warning('Bounce ignored — cannot confirm we sent the message', [
                    'uid' => $uid,
                    'recipients' => $failure['recipients'],
                ]);
                $rows[] = [$uid, $named, $status, 'ignored — cannot confirm we sent the message'];

                continue;
            }

            if ($dryRun) {
                $this->buried++;
                $rows[] = [$uid, $recipient, $status, 'would bury'];

                continue;
            }

            $entry = EmailGraveyard::bury($recipient, EmailGraveyard::SOURCE_BOUNCE, $failure['status'], $failure['diagnostic']);
            $this->buried += $entry?->wasRecentlyCreated ? 1 : 0;
            $rows[] = [$uid, $recipient, $status, $entry?->wasRecentlyCreated ? 'buried' : 'already in graveyard'];
        }

        return $rows;
    }

    /**
     * Where bounces arrive: by default the mailbox we send from, reached over
     * IMAP with the SMTP login.
     *
     * @return array{host: string, port: int, username: string, password: string, folder: string}|null
     */
    private function mailboxSettings(): ?array
    {
        $smtp = (array) config('mail.mailers.'.config('mail.default'), []);
        $sendsOverSmtp = ($smtp['transport'] ?? null) === 'smtp';

        $host = config('mail.bounces.host') ?: ($sendsOverSmtp ? $this->imapHostFor((string) ($smtp['host'] ?? '')) : null);
        $username = config('mail.bounces.username') ?: ($sendsOverSmtp ? ($smtp['username'] ?? null) : null);
        $password = config('mail.bounces.password') ?: ($sendsOverSmtp ? ($smtp['password'] ?? null) : null);

        if (! $host || ! $username || ! $password) {
            return null;
        }

        return [
            'host' => $host,
            'port' => (int) config('mail.bounces.port', 993),
            'username' => (string) $username,
            'password' => (string) $password,
            'folder' => (string) config('mail.bounces.folder', 'INBOX'),
        ];
    }

    /**
     * Providers name their IMAP host after their SMTP one.
     */
    private function imapHostFor(string $smtpHost): ?string
    {
        $smtpHost = strtolower($smtpHost);

        if (in_array($smtpHost, ['smtp.office365.com', 'smtp-mail.outlook.com'], true)) {
            return 'outlook.office365.com';
        }

        return str_starts_with($smtpHost, 'smtp.') ? 'imap.'.substr($smtpHost, 5) : null;
    }
}
