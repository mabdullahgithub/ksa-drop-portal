<?php

namespace App\Support;

use App\Models\DeletionLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Captures who performed a delete / restore / purge, from where, and on what.
 *
 * Everything here has to survive running outside an HTTP request -- scheduled
 * commands, queued jobs and tests all delete records -- so every field is
 * nullable and nothing assumes a request or a signed-in user exists.
 */
class DeletionAudit
{
    /**
     * The audit columns to stamp on a row as it is soft-deleted.
     *
     * @return array<string, mixed>
     */
    public static function stampColumns(): array
    {
        $context = self::context();

        return [
            'deleted_by' => $context['user_id'],
            'deleted_ip' => $context['ip'],
            'deleted_user_agent' => $context['user_agent'],
            'deleted_device' => $context['device'],
        ];
    }

    /** Columns to clear when a row comes back out of the bin. */
    public static function clearColumns(): array
    {
        return [
            'deleted_by' => null,
            'deleted_ip' => null,
            'deleted_user_agent' => null,
            'deleted_device' => null,
        ];
    }

    /**
     * Write a permanent audit row. Survives the record itself, which is the
     * whole point for a purge.
     */
    public static function record(Model $subject, string $action, ?string $label = null): void
    {
        $context = self::context();

        DeletionLog::create([
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'subject_label' => $label ?? self::label($subject),
            'action' => $action,
            'user_id' => $context['user_id'],
            'user_name' => $context['user_name'],
            'ip' => $context['ip'],
            'user_agent' => $context['user_agent'],
            'device' => $context['device'],
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{user_id: int|null, user_name: string|null, ip: string|null, user_agent: string|null, device: string|null}
     */
    public static function context(): array
    {
        // hasUser() rather than user(): it reports an already-resolved user
        // without trying to pull one out of a session, which is what we want
        // in a queue worker or a scheduled command.
        $user = auth()->hasUser() ? auth()->user() : null;

        $request = app()->bound('request') ? app('request') : null;
        $userAgent = $request?->userAgent();

        // Deliberately not App::runningInConsole(): the test suite and queue
        // workers both run under the CLI SAPI, yet a test that dispatches a
        // real request through the kernel should still be audited. What
        // actually distinguishes a genuine browser request is that something
        // is on the other end -- a signed-in user or a User-Agent header.
        // A scheduled command has neither, and recording its loopback IP as
        // if a person did it would be worse than recording nothing.
        $isHttp = $user !== null || ! empty($userAgent);

        return [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'ip' => $isHttp ? $request?->ip() : null,
            'user_agent' => $userAgent ?: null,
            'device' => ClientDevice::describe($userAgent),
        ];
    }

    /**
     * A human label for the subject, captured while the record still exists so
     * the log stays readable after a purge.
     */
    private static function label(Model $subject): ?string
    {
        foreach (['order_number', 'company_name', 'title', 'name'] as $attribute) {
            if (! empty($subject->getAttribute($attribute))) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return null;
    }
}
