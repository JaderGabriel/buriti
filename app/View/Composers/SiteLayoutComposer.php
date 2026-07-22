<?php

namespace App\View\Composers;

use App\Services\SettingService;
use Illuminate\View\View;

class SiteLayoutComposer
{
    public function __construct(private SettingService $settings) {}

    public function compose(View $view): void
    {
        $all = $this->settings->all();

        $view->with([
            'contactEmail' => $all['contact_email'],
            'contactPhone' => $all['contact_phone'],
            'contactWhatsapp' => $all['contact_whatsapp'],
            'linkedinUrl' => $all['linkedin_url'],
            'githubUrl' => $all['github_url'],
            'telegramUrl' => $all['telegram_url'],
            'telegramHandle' => $all['telegram_handle'] ?? '@JaderGabriel',
        ]);
    }
}
