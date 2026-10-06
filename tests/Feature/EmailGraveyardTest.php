<?php

namespace Tests\Feature;

use App\Models\EmailGraveyard;
use App\Models\User;
use App\Services\Mail\BounceReport;
use App\Services\Mail\ImapMailbox;
use App\Services\Mail\RejectedRecipientTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

/**
 * An address that turns out not to exist is moved to the email graveyard —
 * whether the mail server refuses it while we are sending or a bounce email
 * reports it afterwards — and nothing is sent to it again.
 */
class EmailGraveyardTest extends TestCase
{
    use RefreshDatabase;

    // ── Not sending to buried addresses ────────────────────────────────────

    public function test_nothing_is_sent_to_a_buried_address(): void
    {
        EmailGraveyard::bury('Dead@Example.com', EmailGraveyard::SOURCE_BOUNCE, '5.1.1', 'does not exist');

        $this->sendTo('dead@example.com');
        $this->sendTo('DEAD@example.com');

        $this->assertCount(0, $this->sentMessages());

        $entry = EmailGraveyard::sole();
        $this->assertSame(2, $entry->blocked_count);
        $this->assertNotNull($entry->last_blocked_at);
    }

    public function test_a_message_to_several_people_still_reaches_the_living_ones(): void
    {
        EmailGraveyard::bury('dead@example.com', EmailGraveyard::SOURCE_BOUNCE);

        Mail::raw('Hello', fn ($message) => $message
            ->to(['dead@example.com', 'alive@example.com'])
            ->cc('dead@example.com')
            ->subject('Hi'));

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);

        $email = $sent[0]->getOriginalMessage();
        $this->assertSame(['alive@example.com'], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $this->assertSame([], $email->getCc());
    }

    public function test_mail_still_goes_out_when_the_graveyard_cannot_be_read(): void
    {
        // A deploy that has not been migrated yet.
        Schema::drop('email_graveyard');

        $this->sendTo('alive@example.com');

        $this->assertCount(1, $this->sentMessages());
    }

    // ── Refused while sending ──────────────────────────────────────────────

    public function test_an_address_the_mail_server_refuses_as_unknown_is_buried(): void
    {
        $this->mailServerAnswers('RCPT TO:<dead@example.com>', 550, '550 5.1.1 <dead@example.com>: Recipient address rejected: User unknown in virtual mailbox table');

        try {
            $this->sendTo('dead@example.com');
            $this->fail('The transport failure must still reach the caller.');
        } catch (TransportExceptionInterface) {
            // Callers see exactly what they saw before.
        }

        $this->assertDatabaseHas('email_graveyard', [
            'email' => 'dead@example.com',
            'source' => EmailGraveyard::SOURCE_SMTP,
            'status_code' => '550',
        ]);

        // The mail server is never asked about this address again.
        $this->sendTo('dead@example.com');
        $this->assertSame(1, EmailGraveyard::sole()->blocked_count);
    }

    public function test_a_refusal_that_is_not_about_the_address_buries_nobody(): void
    {
        // Wrong SMTP login: the server says this about every recipient.
        $this->mailServerAnswers('RCPT TO:<alive@example.com>', 554, '554 5.7.1 <alive@example.com>: Relay access denied');
        $this->sendExpectingFailure('alive@example.com');

        // Our own sender address refused, in the words used for a missing mailbox.
        $this->mailServerAnswers('MAIL FROM:<portal@example.com>', 550, '550 5.1.1 <portal@example.com>: User unknown');
        $this->sendExpectingFailure('alive@example.com');

        // Greylisting: come back later.
        $this->mailServerAnswers('RCPT TO:<alive@example.com>', 450, '450 4.1.1 <alive@example.com>: Recipient address rejected: User unknown');
        $this->sendExpectingFailure('alive@example.com');

        $this->assertDatabaseCount('email_graveyard', 0);
    }

    public function test_every_mailer_is_watched_for_refused_recipients(): void
    {
        $this->assertInstanceOf(RejectedRecipientTransport::class, Mail::mailer()->getSymfonyTransport());
    }

    // ── Reported afterwards by a bounce email ──────────────────────────────

    public function test_outgoing_mail_carries_a_token_for_its_recipient(): void
    {
        $this->sendTo('alive@example.com');

        $header = $this->sentMessages()[0]->getOriginalMessage()->getHeaders()->get(BounceReport::TOKEN_HEADER);

        $this->assertSame(BounceReport::tokenFor('alive@example.com'), $header->getBodyAsString());
    }

    public function test_an_address_not_found_bounce_buries_the_address(): void
    {
        $mailbox = $this->bounceMailbox([
            41 => $this->gmailBounce('Dead@Example.com'),
            42 => "From: A customer <someone@example.com>\r\nSubject: Re: my order\r\n\r\nPlease bury alive@example.com\r\n",
        ]);

        $this->artisan('mail:process-bounces')->assertSuccessful();

        $this->assertDatabaseHas('email_graveyard', [
            'email' => 'dead@example.com',
            'source' => EmailGraveyard::SOURCE_BOUNCE,
            'status_code' => '5.1.1',
        ]);
        $this->assertDatabaseCount('email_graveyard', 1);
        $this->assertSame(['imap.gmail.com', 993, 'portal@gmail.com', 'app-password'], $mailbox->session);
        $this->assertStringStartsWith('SINCE ', $mailbox->searches[0]);

        // The next run starts after what this one read.
        $this->artisan('mail:process-bounces')->assertSuccessful();
        $this->assertStringStartsWith('UID 43:* ', $mailbox->searches[1]);
    }

    public function test_a_bounce_we_cannot_tie_to_our_own_mail_buries_nobody(): void
    {
        // Anyone can email the sending mailbox something shaped like a bounce,
        // and other software sending through the same mailbox gets real ones.
        $this->bounceMailbox([
            41 => $this->gmailBounce('victim@example.com', token: 'not-a-token-we-signed'),
            42 => $this->gmailBounce('victim@example.com', token: BounceReport::tokenFor('someone-else@example.com')),
            43 => $this->gmailBounce('victim@example.com', token: false),
        ]);

        $this->artisan('mail:process-bounces')->assertSuccessful();

        $this->assertDatabaseCount('email_graveyard', 0);
    }

    public function test_a_bounce_for_another_reason_buries_nobody(): void
    {
        $this->bounceMailbox([
            41 => $this->gmailBounce('full@example.com', status: '5.2.2', diagnostic: '552-5.2.2 The recipient\'s inbox is out of storage space.'),
            42 => $this->gmailBounce('slow@example.com', action: 'delayed', status: '4.4.1', diagnostic: 'The recipient server did not accept our requests to connect.'),
        ]);

        $this->artisan('mail:process-bounces')->assertSuccessful();

        $this->assertDatabaseCount('email_graveyard', 0);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $mailbox = $this->bounceMailbox([41 => $this->gmailBounce('dead@example.com')]);

        $this->artisan('mail:process-bounces --dry-run')
            ->expectsOutputToContain('would bury')
            ->assertSuccessful();

        $this->assertDatabaseCount('email_graveyard', 0);

        // No position was saved either: the real run still sees the bounce.
        $this->artisan('mail:process-bounces')->assertSuccessful();
        $this->assertStringStartsWith('SINCE ', $mailbox->searches[1]);
        $this->assertDatabaseCount('email_graveyard', 1);
    }

    public function test_a_mailbox_that_cannot_be_reached_fails_the_run_without_burying(): void
    {
        $mailbox = $this->bounceMailbox([]);
        $mailbox->refuseLogin = true;

        $this->artisan('mail:process-bounces')
            ->expectsOutputToContain('Bounce check failed')
            ->assertFailed();

        $this->assertDatabaseCount('email_graveyard', 0);
    }

    public function test_nothing_is_checked_when_mail_is_not_sent_over_smtp(): void
    {
        $mailbox = $this->bounceMailbox([41 => $this->gmailBounce('dead@example.com')]);
        config(['mail.default' => 'array']);

        $this->artisan('mail:process-bounces')->assertSuccessful();

        $this->assertNull($mailbox->session);
    }

    // ── Admin ──────────────────────────────────────────────────────────────

    public function test_an_admin_can_see_the_graveyard_and_restore_an_address(): void
    {
        $entry = EmailGraveyard::bury('fixed@example.com', EmailGraveyard::SOURCE_BOUNCE, '5.1.1', 'does not exist');
        $admin = $this->userWhoCan(['manage-email-settings']);

        $this->actingAs($admin)
            ->get('/admin/email-settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('graveyard.0.email', 'fixed@example.com'));

        $this->actingAs($admin)
            ->delete("/admin/email-graveyard/{$entry->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('email_graveyard', 0);

        $this->sendTo('fixed@example.com');
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_restoring_needs_the_email_settings_permission(): void
    {
        $entry = EmailGraveyard::bury('dead@example.com', EmailGraveyard::SOURCE_BOUNCE);

        $this->actingAs($this->userWhoCan([]))
            ->delete("/admin/email-graveyard/{$entry->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('email_graveyard', 1);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function sendTo(string $address): void
    {
        Mail::raw('Hello', fn ($message) => $message->to($address)->subject('Hi'));
    }

    private function sendExpectingFailure(string $address): void
    {
        try {
            $this->sendTo($address);
            $this->fail('The send was expected to fail.');
        } catch (TransportExceptionInterface) {
            //
        }
    }

    /**
     * @return array<int, SentMessage>
     */
    private function sentMessages(): array
    {
        return Mail::mailer()->getSymfonyTransport()->messages()->all();
    }

    /**
     * Make the mail server refuse the given SMTP command the way Symfony's
     * SMTP transport reports it: the reply in the exception, the conversation
     * so far in its debug transcript.
     */
    private function mailServerAnswers(string $refusedCommand, int $code, string $reply): void
    {
        $server = new class($refusedCommand, $code, $reply) implements TransportInterface
        {
            public function __construct(private string $command, private int $code, private string $reply) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $transcript = str_starts_with($this->command, 'RCPT')
                    ? "[2026-10-06T10:00:00.000000+00:00] > MAIL FROM:<portal@example.com>\n[2026-10-06T10:00:00.100000+00:00] < 250 2.1.0 Ok\r\n"
                    : '';

                $e = new UnexpectedResponseException(
                    sprintf('Expected response code "250/251/252" but got code "%d", with message "%s".', $this->code, $this->reply),
                    $this->code
                );
                $e->appendDebug($transcript."[2026-10-06T10:00:00.200000+00:00] > {$this->command}\n[2026-10-06T10:00:00.300000+00:00] < {$this->reply}\r\n");

                throw $e;
            }

            public function __toString(): string
            {
                return 'smtp://refusing';
            }
        };

        Mail::mailer()->setSymfonyTransport(new RejectedRecipientTransport($server));
    }

    /**
     * Stand in for the sending mailbox's IMAP server, holding the given
     * messages by UID, with mail configured the way production sends it.
     *
     * @param  array<int, string>  $messages
     */
    private function bounceMailbox(array $messages): ImapMailbox
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'portal@gmail.com',
            'mail.mailers.smtp.password' => 'app-password',
        ]);

        $mailbox = new class($messages) extends ImapMailbox
        {
            public ?array $session = null;

            public array $searches = [];

            public bool $refuseLogin = false;

            public function __construct(private array $messages) {}

            public function connect(string $host, int $port = 993, int $timeout = 20): void
            {
                $this->session = [$host, $port];
            }

            public function login(string $username, string $password): void
            {
                if ($this->refuseLogin) {
                    throw new \RuntimeException('IMAP LOGIN failed: NO [AUTHENTICATIONFAILED] Invalid credentials');
                }

                array_push($this->session, $username, $password);
            }

            public function examine(string $folder = 'INBOX'): array
            {
                return ['uidvalidity' => 7, 'uidnext' => ($this->messages ? max(array_keys($this->messages)) : 0) + 1];
            }

            public function search(string $criteria): array
            {
                $this->searches[] = $criteria;

                return array_keys($this->messages);
            }

            public function fetch(int $uid, int $maxBytes = 262144): string
            {
                return $this->messages[$uid];
            }

            public function logout(): void {}
        };

        $this->app->instance(ImapMailbox::class, $mailbox);

        return $mailbox;
    }

    /**
     * A bounce the way Gmail writes one, modelled on real ones from the
     * sending mailbox: a human-readable part, the machine-readable delivery
     * status, then the original message's headers quoted back.
     *
     * @param  string|false|null  $token  false leaves the token header out, as
     *                                    in a bounce for mail we did not send
     */
    private function gmailBounce(
        string $recipient,
        string|false|null $token = null,
        string $action = 'failed',
        string $status = '5.1.1',
        ?string $diagnostic = null,
    ): string {
        $token ??= BounceReport::tokenFor($recipient);
        $diagnostic ??= "550-5.1.1 The email account that you tried to reach does not exist. Please try\r\n"
            ." 550-5.1.1 double-checking the recipient's email address for typos or\r\n"
            ." 550-5.1.1 unnecessary spaces. For more information, go to\r\n"
            .' 550 5.1.1  https://support.google.com/mail/?p=NoSuchUser d2e1a72fcca58-gsmtp';

        return implode("\r\n", array_filter([
            'Delivered-To: portal@gmail.com',
            'Return-Path: <>',
            'Content-Type: multipart/report; boundary="000000000000a1b2c3"; report-type=delivery-status',
            'From: Mail Delivery Subsystem <mailer-daemon@googlemail.com>',
            'To: portal@gmail.com',
            'Auto-Submitted: auto-replied',
            'Subject: Delivery Status Notification (Failure)',
            "X-Failed-Recipients: {$recipient}",
            '',
            '--000000000000a1b2c3',
            'Content-Type: text/plain; charset="UTF-8"',
            '',
            '** Address not found **',
            '',
            "Your message wasn't delivered to {$recipient} because the address couldn't be found, or is unable to receive mail.",
            '',
            'The response was:',
            '',
            '550 5.1.1 The email account that you tried to reach does not exist.',
            '',
            '--000000000000a1b2c3',
            'Content-Type: message/delivery-status',
            '',
            'Reporting-MTA: dns; googlemail.com',
            'Received-From-MTA: dns; portal@gmail.com',
            'Arrival-Date: Tue, 06 Oct 2026 03:12:44 -0700 (PDT)',
            'X-Original-Message-ID: <0f5c2d9a8b7e4c1d9a8b7e4c1d9a8b7e@gmail.com>',
            '',
            "Final-Recipient: rfc822; {$recipient}",
            "Action: {$action}",
            "Status: {$status}",
            'Remote-MTA: dns; mx.example.com. (93.184.216.34, the server for the',
            ' domain example.com.)',
            "Diagnostic-Code: smtp; {$diagnostic}",
            'Last-Attempt-Date: Tue, 06 Oct 2026 03:12:45 -0700 (PDT)',
            '',
            '--000000000000a1b2c3',
            'Content-Type: text/rfc822-headers; charset="UTF-8"',
            'Content-Disposition: inline',
            '',
            'Received: by 2002:a05:6a00:1 with SMTP id abc; Tue, 06 Oct 2026 03:12:44 -0700 (PDT)',
            'From: KSA Drop <portal@gmail.com>',
            "To: {$recipient}",
            // Nothing in the quoted original may be read as a report field.
            'Subject: Final-Recipient: rfc822; decoy@example.com',
            'X-Mailer: KSA Drop',
            $token === false ? null : BounceReport::TOKEN_HEADER.": {$token}",
            'Message-ID: <0f5c2d9a8b7e4c1d9a8b7e4c1d9a8b7e@gmail.com>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/related; boundary=Qx_81hTz',
            '',
            '--000000000000a1b2c3--',
            '',
        ], fn ($line) => $line !== null));
    }

    private function userWhoCan(array $permissions): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('manage-email-settings');

        $role = Role::create(['name' => 'role-'.uniqid()]);
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
