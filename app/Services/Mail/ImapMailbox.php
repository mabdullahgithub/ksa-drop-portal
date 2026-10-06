<?php

namespace App\Services\Mail;

use RuntimeException;

/**
 * Just enough IMAP to read bounce emails out of the sending mailbox.
 *
 * Spoken over a plain socket on purpose: PHP's imap extension is not loaded
 * everywhere this runs and was unbundled in PHP 8.4, and production installs
 * no new Composer packages on deploy. Only ever opens the folder read-only —
 * nothing here marks, moves or deletes a message.
 */
class ImapMailbox
{
    /** @var resource|null */
    private $stream = null;

    private int $tag = 0;

    public function connect(string $host, int $port = 993, int $timeout = 20): void
    {
        $stream = @stream_socket_client("ssl://{$host}:{$port}", $errno, $error, $timeout);

        if ($stream === false) {
            throw new RuntimeException("Could not connect to {$host}:{$port}: ".($error ?: 'connection failed'));
        }

        $this->attach($stream, $timeout);
    }

    /**
     * Take over an already open connection and read the server's greeting.
     *
     * @param  resource  $stream
     */
    public function attach($stream, int $timeout = 20): void
    {
        stream_set_timeout($stream, $timeout);
        $this->stream = $stream;

        if (! preg_match('/^\* (OK|PREAUTH)\b/i', $this->readLine())) {
            throw new RuntimeException('The IMAP server did not greet us.');
        }
    }

    public function login(string $username, string $password): void
    {
        $this->command('LOGIN '.$this->quote($username).' '.$this->quote($password));
    }

    /**
     * Open a folder read-only.
     *
     * @return array{uidvalidity: int|null, uidnext: int|null}
     */
    public function examine(string $folder = 'INBOX'): array
    {
        $state = ['uidvalidity' => null, 'uidnext' => null];

        foreach ($this->command('EXAMINE '.$this->quote($folder)) as $response) {
            if (preg_match('/\[(UIDVALIDITY|UIDNEXT) (\d+)\]/i', $response['line'], $m)) {
                $state[strtolower($m[1])] = (int) $m[2];
            }
        }

        return $state;
    }

    /**
     * UIDs of the messages matching the search, lowest first.
     *
     * @return array<int, int>
     */
    public function search(string $criteria): array
    {
        $uids = [];

        foreach ($this->command('UID SEARCH '.$criteria) as $response) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $response['line'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) as $uid) {
                    $uids[] = (int) $uid;
                }
            }
        }

        sort($uids);

        return $uids;
    }

    /**
     * The raw message, without marking it read. Capped because a bounce can
     * quote back an original with large attachments, and everything a bounce
     * is read for sits at the top.
     */
    public function fetch(int $uid, int $maxBytes = 262144): string
    {
        foreach ($this->command("UID FETCH {$uid} (BODY.PEEK[]<0.{$maxBytes}>)") as $response) {
            if ($response['literals'] !== [] && stripos($response['line'], 'FETCH') !== false) {
                return $response['literals'][0];
            }
        }

        return '';
    }

    public function logout(): void
    {
        if ($this->stream === null) {
            return;
        }

        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // Already gone, or never got as far as logging in.
        }

        @fclose($this->stream);
        $this->stream = null;
    }

    /**
     * Send one command and collect the untagged responses up to its tagged
     * completion line.
     *
     * @return array<int, array{line: string, literals: array<int, string>}>
     */
    private function command(string $command): array
    {
        if ($this->stream === null) {
            throw new RuntimeException('Not connected to an IMAP server.');
        }

        $tag = 'A'.(++$this->tag);

        if (@fwrite($this->stream, "{$tag} {$command}\r\n") === false) {
            throw new RuntimeException('Lost the connection to the IMAP server.');
        }

        $responses = [];

        while (true) {
            $response = $this->readResponse();

            if (str_starts_with($response['line'], $tag.' ')) {
                $status = trim(substr($response['line'], strlen($tag) + 1));

                if (! preg_match('/^OK\b/i', $status)) {
                    // Name the command by its verb only: LOGIN carries the password.
                    throw new RuntimeException('IMAP '.strtok($command, ' ').' failed: '.$status);
                }

                return $responses;
            }

            $responses[] = $response;
        }
    }

    /**
     * One logical response. A line ending in "{123}" announces 123 bytes of
     * literal data (a message body), after which the same response carries on.
     *
     * @return array{line: string, literals: array<int, string>}
     */
    private function readResponse(): array
    {
        $line = '';
        $literals = [];

        while (true) {
            $part = rtrim($this->readLine(), "\r\n");
            $line .= $part;

            if (! preg_match('/\{(\d+)\}$/', $part, $m)) {
                return ['line' => $line, 'literals' => $literals];
            }

            $literals[] = $this->readBytes((int) $m[1]);
        }
    }

    private function readLine(): string
    {
        $line = fgets($this->stream);

        if ($line === false) {
            throw new RuntimeException('The IMAP server stopped answering.');
        }

        return $line;
    }

    private function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('The IMAP server stopped answering.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function quote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }
}
