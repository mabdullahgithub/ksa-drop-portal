<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use Illuminate\Console\Command;

class CleanupOldEmailLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:cleanup-logs {--days=180 : Number of days to keep logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete email logs older than specified days (default: 180 days)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be at least 1');

            return Command::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("Deleting email logs older than {$days} days (before {$cutoffDate->toDateString()})...");

        $count = EmailLog::where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('No old logs to delete.');

            return Command::SUCCESS;
        }

        if (! $this->confirm("Delete {$count} email log(s)?", true)) {
            $this->info('Cleanup cancelled.');

            return Command::SUCCESS;
        }

        $deleted = EmailLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Successfully deleted {$deleted} email log(s).");

        return Command::SUCCESS;
    }
}
