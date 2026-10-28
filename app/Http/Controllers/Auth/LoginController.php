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
    private const LOGIN_FAILED = 'Não foi possível entrar. Verifique os dados e tente de novo.';

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
                ->withErrors(['login' => self::LOGIN_FAILED]);
        }

        if ($user->is_active === false) {
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => self::LOGIN_FAILED]);
        }

        if ($user->is_admin === false) {
            if (! $user->canEnterJirau()) {
                $this->security->recordLoginAttempt($request, $user, false);

                return back()
                    ->withInput($request->only('login', 'remember'))
                    ->withErrors(['login' => self::LOGIN_FAILED]);
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
        $telegramAvailable = $this->twoFactor->telegramChannelAvailable($payload);

        return view('auth.two-factor', [
            'resendWait' => $this->twoFactor->resendWait($payload),
            'preferIos' => $this->twoFactor->prefersIos($payload),
            'telegramAvailable' => $telegramAvailable,
            'telegramSent' => $this->twoFactor->telegramSent($payload),
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

        return back()->with('status', 'Código enviado no Telegram.');
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

        $challenge = $this->telegramWebAuth->createChallenge($request);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'ok' => true,
                ...$challenge,
            ]);
        }

        return redirect()->away($challenge['deep_link']);
    }

    public function telegramStatus(Request $request, string $token): JsonResponse
    {
        return response()->json($this->telegramWebAuth->status($request, $token));
    }

    public function completeTelegram(Request $request, string $token): RedirectResponse
    {
        $user = $this->telegramWebAuth->consume($request, $token);

        if (! $user) {
            return redirect()
                ->route('login')
                ->withErrors(['telegram' => 'Link de login Telegram inválido ou expirado. Tente de novo.']);
        }

        return $this->finishTelegramLogin($request, $user);
    }

    public function telegramWidget(Request $request): RedirectResponse
    {
        $token = (string) $request->session()->get(TelegramWebAuthService::SESSION_KEY, '');
        $verified = $this->telegramWebAuth->verifyWidgetAuth($request->query());

        if ($token === '' || ! $verified) {
            $this->security->recordLoginAttempt($request, null, false);

            return redirect()
                ->route('login')
                ->withErrors(['telegram' => 'Inicie o login nesta página com Continuar com Telegram.']);
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

        if (! $this->telegramWebAuth->approve($token, $user)) {
            $this->security->recordLoginAttempt($request, $user, false);

            return redirect()
                ->route('login')
                ->withErrors(['telegram' => 'Link de login Telegram inválido ou expirado. Tente de novo.']);
        }

        $consumed = $this->telegramWebAuth->consume($request, $token);
        if (! $consumed) {
            return redirect()
                ->route('login')
                ->withErrors(['telegram' => 'Link de login Telegram inválido ou expirado. Tente de novo.']);
        }

        return $this->finishTelegramLogin($request, $consumed);
    }

    private function finishTelegramLogin(Request $request, User $user): RedirectResponse
    {
        if ($user->hasIosTwoFactor()) {
            $started = $this->twoFactor->start($user, false, $request);
            if (! ($started['ok'] ?? false)) {
                $this->security->recordLoginAttempt($request, $user, false);

                return redirect()
                    ->route('login')
                    ->withErrors(['telegram' => $started['message'] ?? 'Não foi possível iniciar a verificação.']);
            }

            return redirect()->route('login.two-factor');
        }

        Auth::login($user, false);
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
