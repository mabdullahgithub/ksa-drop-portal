<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    public function __construct(
        protected EmailService $emailService
    ) {}

    public function index(): Response
    {
        abort_unless(auth()->user()->can('manage-email-settings'), 403, 'Unauthorized access to email settings.');

        $settings = EmailSetting::getActive() ?? new EmailSetting([
            'is_active' => false,
            'driver' => 'smtp',
            'encryption' => 'tls',
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ]);

        // Never send actual password to frontend - use status instead
        $settingsData = $settings->toArray();
        $settingsData['password_status'] = $settings->hasPassword() ? 'set' : 'not_set';
        unset($settingsData['password']);

        return Inertia::render('Admin/EmailSettings', [
            'settings' => $settingsData,
            'recentLogs' => EmailLog::with('user')
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-email-settings'), 403, 'Unauthorized access to email settings.');

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'driver' => ['required', 'in:smtp,sendmail,mailgun,ses,postmark,log'],
            'host' => ['nullable', 'required_if:driver,smtp', 'string', 'max:255'],
            'port' => ['nullable', 'required_if:driver,smtp', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
        ]);

        $settings = EmailSetting::getActive() ?? new EmailSetting();

        // Only update password if provided (don't overwrite with empty string)
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $settings->fill($validated);
        $settings->save();

        // Log configuration change
        \Log::info('Email settings updated', [
            'user_id' => auth()->id(),
            'driver' => $validated['driver'],
            'is_active' => $validated['is_active'],
        ]);

        return back()->with('success', 'Email settings updated successfully.');
    }

    public function test(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('manage-email-settings'), 403, 'Unauthorized access to email settings.');

        $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $settings = EmailSetting::getActive();

        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Please save email settings first.',
            ], 400);
        }

        $settings->update(['test_email' => $request->test_email]);

        // Pass user ID for rate limiting
        $result = $this->emailService->testConnection($settings, auth()->id());

        // Log test attempt
        \Log::info('Email test connection attempted', [
            'user_id' => auth()->id(),
            'test_email' => $request->test_email,
            'success' => $result['success'],
        ]);

        return response()->json($result);
    }

    public function logs(Request $request): JsonResponse
    {
        abort_unless(auth()->user()->can('manage-email-settings'), 403, 'Unauthorized access to email settings.');

        $logs = EmailLog::with('user')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('email_type', $request->type))
            ->latest()
            ->paginate(20);

        return response()->json($logs);
    }

    public function statistics(): JsonResponse
    {
        abort_unless(auth()->user()->can('manage-email-settings'), 403, 'Unauthorized access to email settings.');

        $stats = [
            'total_sent' => EmailLog::where('status', 'sent')->count(),
            'total_failed' => EmailLog::where('status', 'failed')->count(),
            'today_sent' => EmailLog::where('status', 'sent')
                ->whereDate('created_at', today())->count(),
            'this_week' => EmailLog::where('status', 'sent')
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'by_type' => EmailLog::selectRaw('email_type, count(*) as count')
                ->groupBy('email_type')
                ->pluck('count', 'email_type'),
        ];

        return response()->json($stats);
    }
}
