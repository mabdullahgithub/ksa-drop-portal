<?php

namespace App\Console\Commands;

use App\Logging\Enums\Retention;
use Illuminate\Console\Command;

/**
 * Deletes log files past their retention class. Prunable files are the
 * daily files directly in storage/logs; history files live in
 * storage/logs/history and are only pruned when LOG_HISTORY_DAYS is set.
 * Channel rotation does the same as a side effect, but only for channels
 * that are written to; this catches every file.
 */
class LogPrune extends Command
{
    protected $signature = 'log:prune {--dry-run : List what would be deleted}';

    protected $description = 'Delete prunable log files older than LOG_PRUNABLE_DAYS (history only if LOG_HISTORY_DAYS is set)';

    public function handle(): int
    {
        $deleted = $this->prune(storage_path('logs'), Retention::PRUNABLE->days())
            + $this->prune(storage_path('logs/history'), Retention::HISTORY->days());

        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ')."{$deleted} file(s).");

        return self::SUCCESS;
    }

    private function prune(string $dir, int $days): int
    {
        if ($days <= 0 || ! is_dir($dir)) {
            return 0;
        }

        $cutoff = now()->utc()->startOfDay()->subDays($days)->format('Y-m-d');
        $count = 0;

        foreach (glob($dir.'/*-????-??-??.log') ?: [] as $file) {
            // The date in the file name, not mtime: a file touched by a copy or
            // backup restore keeps its real age.
            if (preg_match('/-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m) !== 1 || $m[1] >= $cutoff) {
                continue;
            }

            $count++;

            if ($this->option('dry-run')) {
                $this->line(basename($file));
            } else {
                @unlink($file);
            }
        }

        return $count;
    }
}
