<?php

namespace App\Observers;

use App\Observers\Concerns\RecordsDeletionAudit;

/**
 * The catalog has no files of its own to clean on purge -- ProductImage stores
 * a remote `src` URL from the CSV import, not a path on disk -- so this
 * observer exists purely for the deletion audit trail.
 */
class ProductObserver
{
    use RecordsDeletionAudit;
}
