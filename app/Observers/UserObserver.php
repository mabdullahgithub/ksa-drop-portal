<?php

namespace App\Observers;

use App\Observers\Concerns\RecordsDeletionAudit;

/**
 * Deletion audit trail for users sent to (and brought back from) the recycle
 * bin. Spatie's HasRoles already skips detaching roles on a soft delete, so a
 * restored user comes back with the roles they had.
 */
class UserObserver
{
    use RecordsDeletionAudit;
}
