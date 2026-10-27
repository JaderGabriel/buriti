<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private UserNotificationService $notifications) {}

    public function index(Request $request): View
    {
        $user = $this->guard($request);

        return view('account.notifications.index', [
            'layout' => $user->is_admin ? 'layouts.admin' : 'layouts.jirau',
            'notifications' => $user->inboxNotifications()
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'unreadCount' => $this->notifications->unreadCountFor($user),
        ]);
    }

    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        $user = $this->guard($request);
        $this->assertOwner($user, $notification);

        $notification->markAsRead();

        $target = $notification->destinationUrl();

        return $target
            ? redirect()->to($target)
            : redirect()->route('account.notifications.index');
    }

    public function markRead(Request $request, UserNotification $notification): RedirectResponse
    {
        $user = $this->guard($request);
        $this->assertOwner($user, $notification);

        $notification->markAsRead();

        return redirect()->back(fallback: route('account.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $user = $this->guard($request);
        $this->notifications->markAllRead($user);

        return redirect()
            ->back(fallback: route('account.notifications.index'))
            ->with('success', 'Notificações marcadas como lidas.');
    }

    private function guard(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && ($user->is_admin || $user->canEnterJirau()), 403);

        return $user;
    }

    private function assertOwner(User $user, UserNotification $notification): void
    {
        abort_unless((int) $notification->user_id === (int) $user->id, 404);
    }
}
