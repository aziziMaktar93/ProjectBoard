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
        $status = in_array($request->query('status'), ['unread', 'read'], true) ? $request->query('status') : 'all';
        $search = trim((string) $request->string('search'));

        $notifications = $request->user()->appNotifications()
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('data->actor_name', 'like', "%{$search}%")
                    ->orWhere('data->card_name', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Notifications', [
            'notifications' => $notifications,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
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
