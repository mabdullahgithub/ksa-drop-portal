<?php

namespace Tests\Unit;

use App\Services\Mail\ImapMailbox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Drives the IMAP client against a scripted server on the other end of a
 * socket pair. Command tags are sequential, so the server's lines can all be
 * written before the client says anything.
 */
class ImapMailboxTest extends TestCase
{
    /** @var resource */
    private $server;

    private function mailboxAnswering(string $script): ImapMailbox
    {
        [$client, $this->server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($this->server, "* OK Gimap ready\r\n".$script);

        $mailbox = new ImapMailbox;
        $mailbox->attach($client, 1);

        return $mailbox;
    }

    public function test_it_logs_in_searches_and_fetches_a_message_without_marking_it_read(): void
    {
        // The body contains a line that looks like a tagged reply and a "{5}"
        // literal marker: both must be taken as message bytes, not protocol.
        $body = "Subject: bounce\r\n\r\nA4 OK not a real reply\r\ntrailing {5}\r\n";

        $mailbox = $this->mailboxAnswering(
            "A1 OK ali@example.com authenticated (Success)\r\n"
            ."* FLAGS (\\Answered \\Seen)\r\n"
            ."* OK [UIDVALIDITY 7] UIDs valid.\r\n"
            ."* 12 EXISTS\r\n"
            ."* OK [UIDNEXT 45] Predicted next UID.\r\n"
            ."A2 OK [READ-ONLY] INBOX selected. (Success)\r\n"
            ."* SEARCH 44 9 30\r\n"
            ."A3 OK SEARCH completed (Success)\r\n"
            .'* 12 FETCH (UID 44 BODY[]<0> {'.strlen($body)."}\r\n".$body.")\r\n"
            ."A4 OK Success\r\n"
        );

        $mailbox->login('ali@example.com', 'pass "word"');
        $this->assertSame(['uidvalidity' => 7, 'uidnext' => 45], $mailbox->examine('INBOX'));
        $this->assertSame([9, 30, 44], $mailbox->search('SINCE 3-Oct-2026 FROM "mailer-daemon"'));
        $this->assertSame($body, $mailbox->fetch(44));

        $sent = fread($this->server, 8192);
        $this->assertStringContainsString('A1 LOGIN "ali@example.com" "pass \\"word\\""'."\r\n", $sent);
        $this->assertStringContainsString("A2 EXAMINE \"INBOX\"\r\n", $sent);
        $this->assertStringContainsString("A4 UID FETCH 44 (BODY.PEEK[]<0.262144>)\r\n", $sent);
        // Read-only throughout: nothing may select the folder for writing or touch flags.
        $this->assertStringNotContainsString('SELECT', $sent);
        $this->assertStringNotContainsString('STORE', $sent);
    }

    public function test_a_refused_login_is_reported_without_the_password(): void
    {
        $mailbox = $this->mailboxAnswering("A1 NO [AUTHENTICATIONFAILED] Invalid credentials (Failure)\r\n");

        try {
            $mailbox->login('ali@example.com', 'hunter2');
            $this->fail('A refused login should throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('IMAP LOGIN failed', $e->getMessage());
            $this->assertStringContainsString('Invalid credentials', $e->getMessage());
            $this->assertStringNotContainsString('hunter2', $e->getMessage());
        }
    }

    public function test_a_server_that_goes_quiet_does_not_hang_forever(): void
    {
        $mailbox = $this->mailboxAnswering('');

        $this->expectException(RuntimeException::class);
        $mailbox->search('ALL');
    }
}
