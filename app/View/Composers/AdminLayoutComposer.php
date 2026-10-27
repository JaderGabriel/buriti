<?php

namespace App\View\Composers;

use App\Models\ContactMessage;
use App\Services\GoogleCalendarService;
use App\Support\RuntimeVersions;
use Illuminate\View\View;

class AdminLayoutComposer
{
    public function __construct(private GoogleCalendarService $google) {}

    public function compose(View $view): void
    {
        $view->with([
            'instantMeetUrl' => $this->google->instantMeetUrl(),
            'adminUnreadMessages' => ContactMessage::query()->unread()->count(),
            'runtimeVersions' => RuntimeVersions::labels(),
        ]);
    }
}
