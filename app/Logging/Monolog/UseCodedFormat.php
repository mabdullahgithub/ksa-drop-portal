<?php

namespace App\Logging\Monolog;

use Illuminate\Log\Logger;
use Monolog\Handler\FormattableHandlerInterface;

/** `tap` for channels in config/logging.php: coded JSON lines plus the ambient trace/actor. */
final class UseCodedFormat
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new CodedJsonFormatter());
            }
        }

        $monolog->pushProcessor(new AmbientContextProcessor());
    }
}
