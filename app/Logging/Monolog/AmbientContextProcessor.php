<?php

namespace App\Logging\Monolog;

use App\Logging\AmbientContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Adds the trace, actor and client to every record, coded or not. */
final class AmbientContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['ambient'] = ['r' => AmbientContext::trace()] + AmbientContext::actor();

        return $record;
    }
}
