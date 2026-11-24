<?php

namespace App\Listeners;

use App\Logging\AppLog;
use App\Logging\Enums\ModelType;
use App\Logging\Enums\Reason;
use App\Logging\Events\AccessEvent;
use App\Logging\Events\AuthEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * History for access: sign-in/out, failed sign-ins, password resets, and role
 * and permission changes (Spatie fires these with permission.events_enabled).
 */
class LogAuthEvents
{
    public static function register(): void
    {
        Event::listen(Login::class, fn (Login $e) => self::user(AuthEvent::SIGNED_IN, $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? self::user(AuthEvent::SIGNED_OUT, $e->user) : null);
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => self::user(AuthEvent::PASSWORD_RESET_COMPLETED, $e->user));
        Event::listen(Verified::class, fn (Verified $e) => self::user(AuthEvent::EMAIL_VERIFIED, $e->user));
        Event::listen(Registered::class, fn (Registered $e) => self::user(AuthEvent::REGISTERED, $e->user));

        Event::listen(Failed::class, fn (Failed $e) => AppLog::emit(
            AuthEvent::SIGN_IN_FAILED,
            rsn: Reason::CREDENTIALS_INVALID,
            usr: is_int($e->user?->getAuthIdentifier()) ? $e->user->getAuthIdentifier() : null,
        ));
        Event::listen(Lockout::class, fn () => AppLog::emit(AuthEvent::SIGN_IN_LOCKED_OUT, rsn: Reason::RATE_LIMITED));

        Event::listen(RoleAttachedEvent::class, fn (RoleAttachedEvent $e) => self::grants(AccessEvent::ROLE_ATTACHED, $e->model, $e->rolesOrIds, 'rol'));
        Event::listen(RoleDetachedEvent::class, fn (RoleDetachedEvent $e) => self::grants(AccessEvent::ROLE_DETACHED, $e->model, $e->rolesOrIds, 'rol'));
        Event::listen(PermissionAttachedEvent::class, fn (PermissionAttachedEvent $e) => self::grants(AccessEvent::PERMISSION_ATTACHED, $e->model, $e->permissionsOrIds, 'prm'));
        Event::listen(PermissionDetachedEvent::class, fn (PermissionDetachedEvent $e) => self::grants(AccessEvent::PERMISSION_DETACHED, $e->model, $e->permissionsOrIds, 'prm'));
    }

    private static function user(AuthEvent $event, mixed $user): void
    {
        $id = $user?->getAuthIdentifier();

        if (is_int($id)) {
            AppLog::emit($event, usr: $id);
        }
    }

    /** One line per role/permission, so each grant can be found on its own. */
    private static function grants(AccessEvent $event, Model $holder, mixed $items, string $key): void
    {
        $type = ModelType::of($holder);

        if ($type === null || ! is_int($holder->getKey())) {
            return;
        }

        $items = $items instanceof \Illuminate\Support\Collection ? $items->all() : Arr::wrap($items);

        foreach ($items as $item) {
            $id = $item instanceof Model ? $item->getKey() : $item;

            if (is_numeric($id)) {
                AppLog::emit($event, ...['mdl' => $type, 'rid' => $holder->getKey(), $key => (int) $id]);
            }
        }
    }
}
