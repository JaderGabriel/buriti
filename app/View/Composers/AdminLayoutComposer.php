<?php

namespace App\View\Composers;

use App\Services\GoogleCalendarService;
use Illuminate\View\View;

class AdminLayoutComposer
{
    public function __construct(private GoogleCalendarService $google) {}

    public function compose(View $view): void
    {
        $view->with('instantMeetUrl', $this->google->instantMeetUrl());
    }
}
