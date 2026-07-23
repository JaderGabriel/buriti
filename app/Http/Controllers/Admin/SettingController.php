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
        $connection = $this->google->connectionStatus();

        return view('admin.settings.edit', [
            'settings' => $this->settings->all(),
            'googleIntegration' => $this->google->integrationStatus(),
            'googleApiReady' => $this->google->apiConfigured(),
            'googleOauthAppReady' => $this->google->oauthAppConfigured(),
            'googleHasClientSecret' => $connection['has_secret'],
            'googleConnected' => $connection['has_refresh'],
            'googleConnection' => $connection,
            'googleRedirectUri' => $this->google->redirectUri(),
            'googleClientIdValue' => $this->google->clientId(),
            'googleCalendars' => $this->google->listWritableCalendars(),
            'googleResolvedCalendarId' => $this->google->resolvedCalendarId(),
            'googleEventColors' => \App\Enums\GoogleEventColor::palette(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['google_auto_sync'] = $request->input('google_auto_sync', '0');

        unset($data['google_client_secret']);

        $this->settings->putMany($data);

        $secret = $request->input('google_client_secret');
        if (is_string($secret) && trim($secret) !== '') {
            $this->settings->putSecret('google_client_secret', trim($secret));
        }

        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('google-integration')
            ->with('success', 'Configurações salvas.');
    }
}
