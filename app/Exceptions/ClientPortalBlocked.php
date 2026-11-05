<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Raised when the client portal is switched off (config app.client_portal_blocked):
 * a client signing in, an admin starting an impersonation, or anyone already
 * inside the portal. Rendered as a 500 with a fixed message — see bootstrap/app.php.
 */
class ClientPortalBlocked extends HttpException
{
    public const MESSAGE = 'Unexpected error, please contact KSA Drop admin.';

    public function __construct()
    {
        parent::__construct(500, self::MESSAGE);
    }

    public static function enabled(): bool
    {
        return (bool) config('app.client_portal_blocked');
    }
}
