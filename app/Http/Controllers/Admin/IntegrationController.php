<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateIntegrationsRequest;
use App\Services\GoogleCalendarService;
use App\Services\IntegrationToolkitService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private IntegrationToolkitService $integrations,
        private GoogleCalendarService $google,
    ) {}

    public function edit(): View
    {
        return view('admin.integrations.edit', [
            'settings' => $this->settings->all(),
            'trello' => $this->integrations->trelloStatus(),
            'notion' => $this->integrations->notionStatus(),
            'google' => $this->google->integrationStatus(),
            'roadmap' => $this->integrations->roadmap(),
        ]);
    }

    public function update(UpdateIntegrationsRequest $request): RedirectResponse
    {
        $this->settings->putMany($request->validated());

        return redirect()
            ->route('admin.integrations.edit')
            ->with('success', 'Integrações atualizadas.');
    }
}
