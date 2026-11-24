<?php

namespace App\Console\Commands;

use App\Logging\Enums\LogDomain;
use App\Logging\LineDecoder;
use Illuminate\Console\Command;

class LogDecode extends Command
{
    protected $signature = 'log:decode
        {file? : Log file to read; default is the newest laravel-*.log (or history-*.log with --history)}
        {--history : Read the history log instead of the operational log}
        {--event= : Only this event ID}
        {--domain= : Only this domain (ID or name, e.g. 40 or SHOPIFY)}
        {--trace= : Only lines of this trace ID}
        {--client= : Only lines of this client ID}
        {--min-level= : Only this severity number or more severe (0 emergency … 7 debug)}
        {--follow : Keep reading as the file grows}';

    protected $description = 'Decode coded JSON log lines into readable text using the event enums';

    public function handle(): int
    {
        $path = $this->argument('file') ?: $this->newest();

        if (! $path || ! is_file($path)) {
            $this->error('No log file found.');

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        while (true) {
            while (($raw = fgets($handle)) !== false) {
                $line = LineDecoder::parse($raw);

                if ($line !== null && $this->wanted($line)) {
                    $this->line(LineDecoder::render($line));
                }
            }

            if (! $this->option('follow')) {
                break;
            }

            usleep(300_000);
            clearstatcache();
        }

        fclose($handle);

        return self::SUCCESS;
    }

    private function newest(): ?string
    {
        $files = glob($this->option('history') ? storage_path('logs/history/history-*.log') : storage_path('logs/laravel-*.log')) ?: [];
        rsort($files);

        return $files[0] ?? null;
    }

    /** @param array<string, mixed> $line */
    private function wanted(array $line): bool
    {
        if ($this->option('event') !== null && (int) $line['e'] !== (int) $this->option('event')) {
            return false;
        }

        if ($this->option('domain') !== null && intdiv((int) $line['e'], 1000) !== $this->domainId((string) $this->option('domain'))) {
            return false;
        }

        if ($this->option('trace') !== null && ($line['r'] ?? null) !== $this->option('trace')) {
            return false;
        }

        if ($this->option('client') !== null && (int) ($line['c'] ?? 0) !== (int) $this->option('client')) {
            return false;
        }

        if ($this->option('min-level') !== null && (int) $line['l'] > (int) $this->option('min-level')) {
            return false;
        }

        return true;
    }

    private function domainId(string $value): int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }

        foreach (LogDomain::cases() as $domain) {
            if (strcasecmp($domain->name, $value) === 0) {
                return $domain->value;
            }
        }

        return -1;
    }
}
