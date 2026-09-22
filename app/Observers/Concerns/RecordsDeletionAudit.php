<?php

namespace App\Observers\Concerns;

use App\Support\DeletionAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Stamps who/where/what onto every soft delete, restore and purge.
 *
 * Lives in an observer so it covers every code path that deletes -- the admin
 * API, the client portal, the recycle bin and anything added later -- rather
 * than depending on each controller remembering to record it.
 */
trait RecordsDeletionAudit
{
    public function deleted(Model $model): void
    {
        // forceDelete() fires deleting/deleted as well as forceDeleted. Without
        // this guard a purge would log twice and try to stamp a row that no
        // longer exists.
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            return;
        }

        // SoftDeletes::runSoftDelete() writes only deleted_at/updated_at, so
        // attributes set during the event would be discarded. Update directly.
        $model->newQuery()
            ->withTrashed()
            ->whereKey($model->getKey())
            ->update(DeletionAudit::stampColumns());

        DeletionAudit::record($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $model->newQuery()
            ->whereKey($model->getKey())
            ->update(DeletionAudit::clearColumns());

        DeletionAudit::record($model, 'restored');
    }

    public function forceDeleted(Model $model): void
    {
        // The row is gone, but the in-memory model still carries its
        // attributes, so the log keeps a readable label for it.
        DeletionAudit::record($model, 'purged');
    }
}
