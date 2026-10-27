<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\PasswordTwoFactorService;
use App\Services\AuthSecurityService;
use App\Services\Telegram\TelegramWebAuthService;
use App\Support\AuthHome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private AuthSecurityService $security,
        private TelegramWebAuthService $telegramWebAuth,
        private PasswordTwoFactorService $twoFactor,
    ) {}

    public function create(Request $request): View
    {
        return view('auth.login', [
            'telegramLoginEnabled' => $this->telegramWebAuth->enabled(),
            'twoFactorPending' => $this->twoFactor->hasChallenge($request),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $field = $request->loginField();
        $user = User::query()->where($field, $request->input('login'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Credenciais inválidas.']);
        }

        if ($user->is_active === false) {
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Esta conta está desativada.']);
        }

        if ($user->is_admin === false) {
            if (! $user->canEnterJirau()) {
                $this->security->recordLoginAttempt($request, $user, false);

                return back()
                    ->withInput($request->only('login', 'remember'))
                    ->withErrors(['login' => 'Esta conta não tem acesso ao painel nem a um Jirau.']);
            }

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            $this->security->markSuccessfulLogin($user, $request);

            return redirect()->intended(AuthHome::url($user));
        }

        $started = $this->twoFactor->start($user, $request->boolean('remember'), $request);

        if (! ($started['ok'] ?? false)) {
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => $started['message'] ?? 'Não foi possível iniciar a verificação.']);
        }

        return redirect()->route('login.two-factor');
    }

    public function twoFactor(Request $request): View|RedirectResponse
    {
        if (! $this->twoFactor->hasChallenge($request)) {
            return redirect()
                ->route('login')
                ->withErrors(['login' => 'A verificação expirou. Entre com usuário e senha de novo.']);
        }

        $payload = $this->twoFactor->payload($request) ?? [];

        return view('auth.two-factor', [
            'resendWait' => $this->twoFactor->resendWait($payload),
        ]);
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $code = preg_replace('/\D+/', '', (string) $request->input('code')) ?? '';
        $request->merge(['code' => $code]);

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Informe o código de 6 dígitos.',
            'code.size' => 'O código precisa ter 6 dígitos.',
        ]);

        $result = $this->twoFactor->verify($request, $code);

        if (! ($result['ok'] ?? false)) {
            $this->security->recordLoginAttempt($request, null, false);

            if ($result['locked'] ?? false) {
                return redirect()
                    ->route('login')
                    ->withErrors(['login' => $result['message'] ?? 'A verificação falhou.']);
            }

            return back()->withErrors(['code' => $result['message'] ?? 'Código inválido.']);
        }

        /** @var User $user */
        $user = $result['user'];

        Auth::login($user, (bool) ($result['remember'] ?? false));
        $request->session()->regenerate();
        $this->security->markSuccessfulLogin($user, $request);

        return redirect()->intended(AuthHome::url($user));
    }

    public function resendTwoFactor(Request $request): RedirectResponse
    {
        $result = $this->twoFactor->resend($request);

        if (! ($result['ok'] ?? false)) {
            if (! $this->twoFactor->hasChallenge($request)) {
                return redirect()
                    ->route('login')
                    ->withErrors(['login' => $result['message'] ?? 'A verificação expirou.']);
            }

            return back()->withErrors(['code' => $result['message'] ?? 'Não foi possível reenviar.']);
        }

        return back()->with('status', 'Enviamos um novo código no Telegram.');
    }

    public function cancelTwoFactor(Request $request): RedirectResponse
    {
        $this->twoFactor->forget($request);

        return redirect()->route('login');
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

        return redirect()->intended(AuthHome::url($user));
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

        return redirect()->intended(AuthHome::url($user));
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
