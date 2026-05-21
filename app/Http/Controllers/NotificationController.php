<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private function resolveUser(Request $request): User
    {
        if ($request->session()->has('impersonate.admin_id')) {
            $client = Client::find($request->session()->get('impersonate.client_id'));
            if ($client?->user_id) {
                return User::findOrFail($client->user_id);
            }
        }

        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $notifications = $this->resolveUser($request)
            ->notifications()
            ->paginate($perPage);

        return response()->json($notifications);
    }

    public function unread(Request $request): JsonResponse
    {
        $count = $this->resolveUser($request)
            ->unreadNotifications()
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->resolveUser($request)
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->resolveUser($request)
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $this->resolveUser($request)
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['success' => true]);
    }

    public function page(Request $request): Response
    {
        $perPage = $request->input('per_page', 20);

        $notifications = $this->resolveUser($request)
            ->notifications()
            ->paginate($perPage);

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
        ]);
    }
}
