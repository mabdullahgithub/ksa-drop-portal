<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatGeoIp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use MaxMind\Db\Reader;

/**
 * Download DB-IP's free city and network databases, which live chat uses to
 * say where a message was sent from. A new edition comes out each month.
 * Only these two public files are fetched; nothing about anyone is sent.
 */
class UpdateChatGeoIp extends Command
{
    protected $signature = 'chat:update-geoip {--if-stale : Only when a database is missing or over 35 days old}';

    protected $description = 'Download the IP location databases used by live chat';

    private const DATABASES = [ChatGeoIp::CITY, ChatGeoIp::ASN];

    public function handle(): int
    {
        File::ensureDirectoryExists(config('chat.geoip.directory'));
        $failed = false;

        foreach (self::DATABASES as $database) {
            $target = ChatGeoIp::path($database);

            if ($this->option('if-stale') && is_file($target) && filemtime($target) > now()->subDays(35)->getTimestamp()) {
                continue;
            }

            // The month's edition may not be out yet on its first days.
            $done = $this->fetch($database, now()->format('Y-m'), $target)
                || $this->fetch($database, now()->subMonthNoOverflow()->format('Y-m'), $target);

            if (! $done) {
                $failed = true;
                Log::warning('chat.geoip_update_failed', ['database' => $database]);
                $this->error("Could not download the {$database} database.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Download, unpack and check one database; the old one stays until the new one is good. */
    private function fetch(string $database, string $month, string $target): bool
    {
        $url = str_replace(['{database}', '{month}'], [$database, $month], config('chat.geoip.url'));
        $packed = $target . '.gz.tmp';
        $unpacked = $target . '.tmp';

        try {
            if (! Http::timeout(900)->sink($packed)->get($url)->successful()) {
                return false;
            }

            // In pieces: the city database is far larger than PHP's memory limit.
            $in = gzopen($packed, 'rb');
            $out = fopen($unpacked, 'wb');
            while (! gzeof($in)) {
                fwrite($out, gzread($in, 1 << 20));
            }
            gzclose($in);
            fclose($out);

            (new Reader($unpacked))->close();
            rename($unpacked, $target);
            $this->info("Updated the {$database} database ({$month}).");

            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            File::delete([$packed, $unpacked]);
        }
    }
}
