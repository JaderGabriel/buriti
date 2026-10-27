<?php

namespace App\View\Composers;

use App\Models\ContactMessage;
use App\Services\UserNotificationService;
use App\Support\InstantMeetings;
use App\Support\RuntimeVersions;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLayoutComposer
{
    public function __construct(
        private UserNotificationService $notifications,
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with([
            'instantMeetUrl' => InstantMeetings::meet(),
            'instantTeamsUrl' => InstantMeetings::teams(),
            'instantJitsiUrl' => InstantMeetings::jitsi(),
            'instantJitsiHost' => InstantMeetings::jitsiHost(),
            'adminUnreadMessages' => ContactMessage::query()->unread()->count(),
            'runtimeVersions' => RuntimeVersions::labels(),
            'adminUnreadNotifications' => $user ? $this->notifications->unreadCountFor($user) : 0,
            'adminRecentNotifications' => $user ? $this->notifications->recentFor($user) : collect(),
        ]);
    }
}
