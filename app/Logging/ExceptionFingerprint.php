<?php

namespace App\Logging;

use Throwable;

/** A short stable identity for an exception: same class at the same line is the same fingerprint. */
final class ExceptionFingerprint
{
    public static function of(Throwable $e): string
    {
        return substr(sha1($e::class.'|'.$e->getFile().'|'.$e->getLine()), 0, 12);
    }

    /** @return array{cls: string, msg: string, loc: string, trace: string} */
    public static function detail(Throwable $e): array
    {
        $causes = [];
        for ($c = $e->getPrevious(); $c !== null; $c = $c->getPrevious()) {
            $causes[] = $c::class.': '.$c->getMessage();
        }

        return [
            'cls' => $e::class,
            'msg' => mb_substr($e->getMessage(), 0, 2000),
            'loc' => str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine(),
            'causes' => $causes,
            'trace' => mb_substr($e->getTraceAsString(), 0, 8000),
        ];
    }
}
