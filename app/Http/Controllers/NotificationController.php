<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ResolvesNotifiableUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    use ResolvesNotifiableUser;

    /**
     * The bell is a permission for the team. Clients, and staff looking at a
     * client's portal, always have it: these routes are shared with the
     * portal, where there are no staff permissions.
     */
    private function staffMay(Request $request, string $permission): bool
    {
        $user = $request->user();

        return ! $user
            || $user->hasRole('client')
            || $request->session()->has('impersonate.admin_id')
            || $user->can($permission);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->staffMay($request, 'view notifications'), 403);

        $perPage = $request->input('per_page', 20);

        $notifications = $this->resolveNotifiableUser($request)
            ?->notifications()
            ->paginate($perPage);

        return response()->json($notifications ?? ['data' => []]);
    }

    /**
     * Cached, short-TTL unread count. This is also shared on every Inertia
     * page visit (see HandleInertiaRequests), so the badge is normally kept
     * fresh for free by navigation alone — this endpoint only exists as a
     * fallback for staying accurate while a user sits on one page.
     */
    public function unread(Request $request): JsonResponse
    {
        // Polled from every page, so no bell is a zero and not an error.
        if (! $this->staffMay($request, 'view notifications')) {
            return response()->json(['count' => 0]);
        }

        $user = $this->resolveNotifiableUser($request);

        return response()->json(['count' => $user ? $this->cachedUnreadCount($user) : 0]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        abort_unless($this->staffMay($request, 'mark notifications read'), 403);

        $user = $this->resolveNotifiableUser($request);

        if (! $user) {
            return response()->json(['success' => false], 401);
        }

        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();
        $this->forgetUnreadCount($user);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        abort_unless($this->staffMay($request, 'mark notifications read'), 403);

        $user = $this->resolveNotifiableUser($request);

        if (! $user) {
            return response()->json(['success' => false], 401);
        }

        $user->unreadNotifications()->update(['read_at' => now()]);
        $this->forgetUnreadCount($user);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        abort_unless($this->staffMay($request, 'delete notifications'), 403);

        $user = $this->resolveNotifiableUser($request);

        if (! $user) {
            return response()->json(['success' => false], 401);
        }

        $notification = $user->notifications()->findOrFail($id);
        $notification->delete();
        $this->forgetUnreadCount($user);

        return response()->json(['success' => true]);
    }

    public function page(Request $request): Response
    {
        abort_unless($this->staffMay($request, 'view notifications'), 403);

        $perPage = $request->input('per_page', 20);

        $notifications = $this->resolveNotifiableUser($request)
            ?->notifications()
            ->paginate($perPage);

        return Inertia::render('Notifications', [
            'notifications' => $notifications ?? ['data' => []],
        ]);
    }
}
