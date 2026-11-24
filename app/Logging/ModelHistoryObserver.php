<?php

namespace App\Logging;

use App\Logging\Enums\ModelType;
use App\Logging\Events\AuditEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the history of every model listed in ModelType: created, updated
 * (which columns, never their values), deleted, restored, purged. Registered
 * for all of them in AppServiceProvider. saveQuietly()/withoutEvents() writes
 * are not recorded, which is how heartbeat columns stay out of history.
 */
class ModelHistoryObserver
{
    /** Columns whose change alone is not history. */
    private const IGNORED = ['updated_at', 'created_at', 'deleted_at', 'last_seen_at', 'last_ip', 'remember_token'];

    public function created(Model $model): void
    {
        $this->emit(AuditEvent::RECORD_CREATED, $model);
    }

    public function updated(Model $model): void
    {
        $fields = array_values(array_diff(array_keys($model->getChanges()), self::IGNORED));

        if ($fields === []) {
            return;
        }

        $this->emit(AuditEvent::RECORD_UPDATED, $model, self::fields($fields));
    }

    public function deleted(Model $model): void
    {
        // A force delete also fires `deleted`; `forceDeleted` records it as a purge.
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            return;
        }

        $this->emit(AuditEvent::RECORD_DELETED, $model);
    }

    public function restored(Model $model): void
    {
        $this->emit(AuditEvent::RECORD_RESTORED, $model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->emit(AuditEvent::RECORD_PURGED, $model);
    }

    /**
     * Column names joined by "."; past 128 characters the rest is counted as
     * "#N" so the value stays a valid identifier.
     *
     * @param  list<string>  $fields
     */
    public static function fields(array $fields): string
    {
        $out = '';

        foreach ($fields as $i => $field) {
            $next = $out === '' ? $field : $out.'.'.$field;
            $rest = count($fields) - $i;

            if (strlen($next) > 120) {
                return $out.'#'.$rest;
            }

            $out = $next;
        }

        return $out;
    }

    private function emit(AuditEvent $event, Model $model, ?string $fields = null): void
    {
        $type = ModelType::of($model);
        $key = $model->getKey();

        if ($type === null || ! is_int($key)) {
            return;
        }

        $client = $type === ModelType::CLIENT ? $key : $model->getAttribute('client_id');

        AppLog::emit($event, mdl: $type, rid: $key, fld: $fields, cli: is_numeric($client) ? (int) $client : null);
    }
}
