<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function __construct(
        private GoogleCalendarService $google,
        private GoogleDriveService $drive,
    ) {}

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
        $force = $request->boolean('force') || ! $this->google->apiConfigured();

        try {
            return redirect()->away($this->google->authorizationUrl($state, $force));
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
                ->with('error', 'Código de autorização Google ausente.');
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
            ->with('success', 'Conta Google ligada (Agenda + Drive). O CRM reutiliza o refresh token até o Google o invalidar — em modo Teste do Cloud Console isso acontece aos 7 dias; publique a app para evitar religar toda a semana.');
    }

    public function disconnect(): RedirectResponse
    {
        $this->google->disconnect();

        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('google-integration')
            ->with('success', 'Conta Google desconectada. O sync automático pela API fica inativo.');
    }

    public function test(): RedirectResponse
    {
        $calendar = $this->google->testConnection();
        $drive = $this->drive->testFolders();

        $ok = $calendar['ok'];
        $message = $calendar['message'];
        if ($drive['ok']) {
            $message .= ' '.$drive['message'];
        } elseif (filled($this->drive->templatesFolderId()) || filled($this->drive->contractsFolderId())) {
            $ok = false;
            $message .= ' Drive: '.$drive['message'];
        }

        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('google-integration')
            ->with($ok ? 'success' : 'error', $message);
    }
}
