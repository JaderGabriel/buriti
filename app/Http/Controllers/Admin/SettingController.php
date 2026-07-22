<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Services\GoogleCalendarService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private SettingService $settings,
        private GoogleCalendarService $google,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => $this->settings->all(),
            'googleIntegration' => $this->google->integrationStatus(),
            'googleApiReady' => $this->google->apiConfigured(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['google_auto_sync'] = $request->input('google_auto_sync', '0');

        $this->settings->putMany($data);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Configurações salvas.');
    }
}
