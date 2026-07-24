<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthSecurityService;
use App\Services\Telegram\TelegramWebAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private AuthSecurityService $security,
        private TelegramWebAuthService $telegramWebAuth,
    ) {}

    public function create(): View
    {
        return view('auth.login', [
            'telegramLoginEnabled' => $this->telegramWebAuth->enabled(),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            $this->security->recordLoginAttempt($request, null, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Credenciais inválidas.']);
        }

        $user = $request->user();

        if ($user && $user->is_admin === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Esta conta não tem acesso ao painel admin.']);
        }

        if ($user && $user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Esta conta está desativada.']);
        }

        $request->session()->regenerate();
        $this->security->markSuccessfulLogin($user, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function startTelegram(Request $request): JsonResponse|RedirectResponse
    {
        if (! $this->telegramWebAuth->enabled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Login via Telegram não está configurado.',
                ], 422);
            }

            return back()->withErrors([
                'telegram' => 'Login via Telegram não está configurado.',
            ]);
        }

        $challenge = $this->telegramWebAuth->createChallenge();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                ...$challenge,
            ]);
        }

        return redirect()->away($challenge['deep_link']);
    }

    public function telegramStatus(string $token): JsonResponse
    {
        return response()->json($this->telegramWebAuth->status($token));
    }

    public function completeTelegram(Request $request, string $token): RedirectResponse
    {
        $user = $this->telegramWebAuth->consume($token);

        if (! $user) {
            return redirect()
                ->route('login')
                ->withErrors(['telegram' => 'Link de login Telegram inválido ou expirado. Tente de novo.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->security->markSuccessfulLogin($user, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function telegramWidget(Request $request): RedirectResponse
    {
        $verified = $this->telegramWebAuth->verifyWidgetAuth($request->query());

        if (! $verified) {
            $this->security->recordLoginAttempt($request, null, false);

            return redirect()
                ->route('login')
                ->withErrors(['telegram' => 'Autorização Telegram inválida ou expirada.']);
        }

        $user = $this->telegramWebAuth->findLinkedAdmin($verified['id']);

        if (! $user) {
            $this->security->recordLoginAttempt($request, null, false);

            return redirect()
                ->route('login')
                ->withErrors([
                    'telegram' => 'Conta Telegram ainda não vinculada. No bot, use /login email | senha uma vez e tente de novo.',
                ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->security->markSuccessfulLogin($user, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->security->recordLogout($user, $request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
