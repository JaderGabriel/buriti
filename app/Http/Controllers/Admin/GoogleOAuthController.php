<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function __construct(private GoogleCalendarService $google) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->google->oauthAppConfigured()) {
            return redirect()
                ->route('admin.settings.edit')
                ->withFragment('google-integration')
                ->with('error', 'Preencha Client ID e Client Secret antes de ligar a conta Google.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        try {
            return redirect()->away($this->google->authorizationUrl($state));
        } catch (Throwable $e) {
            Log::warning('Google OAuth redirect failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('admin.settings.edit')
                ->withFragment('google-integration')
                ->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('google_oauth_state');
        $state = $request->query('state');

        if (! is_string($expected) || ! is_string($state) || ! hash_equals($expected, $state)) {
            return redirect()
                ->route('admin.settings.edit')
                ->withFragment('google-integration')
                ->with('error', 'Estado OAuth inválido. Tente ligar a conta Google novamente.');
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('admin.settings.edit')
                ->withFragment('google-integration')
                ->with('error', 'Autorização Google cancelada ou recusada: '.$request->string('error'));
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect()
                ->route('admin.settings.edit')
                ->withFragment('google-integration')
                ->with('error', 'Código de autorização Google em falta.');
        }

        try {
            $this->google->exchangeAuthorizationCode($code);
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('admin.settings.edit')
                ->withFragment('google-integration')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('google-integration')
            ->with('success', 'Conta Google ligada. Ao criar tarefas com Meet, o CRM gera o evento e guarda o link automaticamente.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->google->disconnect();

        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('google-integration')
            ->with('success', 'Conta Google desligada. O sync automático pela API fica inativo.');
    }

    public function test(): RedirectResponse
    {
        $result = $this->google->testConnection();

        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('google-integration')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
