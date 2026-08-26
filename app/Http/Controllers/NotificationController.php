<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()->appNotifications()->latest()->limit(50)->get();

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function open(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->route('boards.show', [
            'board' => $notification->data['board_id'],
            'card' => $notification->data['card_id'],
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
