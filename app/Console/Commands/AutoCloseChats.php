<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatWorkflow;
use Illuminate\Console\Command;

/**
 * Close support chats resolved long enough ago (config chat.auto_close_days)
 * that the client is not coming back.
 */
class AutoCloseChats extends Command
{
    protected $signature = 'chats:auto-close';

    protected $description = 'Close support chats that were resolved and left unanswered';

    public function handle(ChatWorkflow $workflow): int
    {
        $closed = $workflow->autoClose();

        $this->info("Closed {$closed} chat(s).");

        return self::SUCCESS;
    }
}
