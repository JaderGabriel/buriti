<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAvatarRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\LoginActivity;
use App\Models\WebauthnCredential;
use App\Services\Auth\PasskeyService;
use App\Services\Auth\TotpService;
use App\Services\AuthSecurityService;
use App\Services\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private AvatarService $avatars,
        private AuthSecurityService $security,
        private TotpService $totp,
        private PasskeyService $passkeys,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $setupSecret = $this->pendingTotpSecret($request);

        return view('admin.profile.edit', [
            'user' => $user,
            'sessions' => $this->security->sessionsFor($user, $request->session()->getId()),
            'sessionDriver' => config('session.driver'),
            'loginActivities' => LoginActivity::query()
                ->forUser($user)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
            'totpUri' => $setupSecret ? $this->totp->otpauthUri($user, $setupSecret) : null,
            'totpSecret' => $setupSecret ? $this->totp->groupedSecret($setupSecret) : null,
            'passkeys' => $user->webauthnCredentials()->orderByDesc('last_used_at')->orderByDesc('id')->get(),
            'passkeyEnabled' => $this->passkeys->supportedOn($request),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = [
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'email' => $request->validated('email'),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        return redirect()
            ->route('admin.profile.edit')
            ->with('success', 'Dados do perfil atualizados.');
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->boolean('remove_avatar')) {
            $this->avatars->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);

            return redirect()
                ->route('admin.profile.edit')
                ->with('success', 'Foto de perfil removida.');
        }

        $path = $this->avatars->replace($user->avatar_path, $request->file('avatar'));
        $user->update(['avatar_path' => $path]);

        return redirect()
            ->route('admin.profile.edit')
            ->with('success', 'Foto de perfil atualizada.');
    }

    public function destroySession(Request $request, string $session): RedirectResponse
    {
        $user = $request->user();
        $currentId = $request->session()->getId();

        if (hash_equals($currentId, $session)) {
            return back()->withErrors(['session' => 'Não é possível encerrar a sessão atual por aqui. Use Sair.']);
        }

        $this->security->destroySession($user, $session);

        return back()->with('success', 'Sessão encerrada.');
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $count = $this->security->destroyOtherSessions(
            $request->user(),
            $request->session()->getId()
        );

        return back()->with('success', $count > 0
            ? "{$count} sessão(ões) encerrada(s)."
            : 'Nenhuma outra sessão ativa.');
    }

    public function startIosTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasIosTwoFactor()) {
            return back()->withErrors(['totp' => 'O iPhone já está vinculado. Remova o método atual para gerar outro.']);
        }

        $this->assertCurrentPassword($request, 'Informe a senha atual para vincular o iPhone.');

        $request->session()->put(TotpService::SETUP_SESSION_KEY, $this->totp->generateSecret());

        return back()->with('success', 'Adicione o código no app Senhas do iPhone e confirme os 6 dígitos.');
    }

    public function confirmIosTwoFactor(Request $request): RedirectResponse
    {
        $this->assertCurrentPassword($request, 'Informe a senha atual para confirmar o iPhone.');

        $code = preg_replace('/\D+/', '', (string) $request->input('code')) ?? '';
        $request->merge(['code' => $code]);
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Informe o código de 6 dígitos do app Senhas.',
            'code.size' => 'O código precisa ter 6 dígitos.',
        ]);

        $secret = $this->pendingTotpSecret($request);
        if ($secret === null) {
            return back()->withErrors(['totp' => 'A configuração expirou. Gere o código de novo.']);
        }

        $step = $this->totp->matchingStep($secret, $code);
        if ($step === null) {
            return back()->withErrors(['code' => 'Código inválido. Espere o próximo no app Senhas e tente de novo.']);
        }

        $request->user()->confirmIosTwoFactor($secret, $step);
        $request->session()->forget(TotpService::SETUP_SESSION_KEY);

        return back()->with('success', 'iPhone vinculado. Nos próximos acessos o app Senhas preenche o código.');
    }

    public function destroyIosTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasIosTwoFactor()) {
            return back()->with('success', 'O iPhone já não estava vinculado.');
        }

        if (! $user->hasTelegramTwoFactor()) {
            return back()->withErrors(['totp' => 'Mantenha o iPhone ou vincule o Telegram antes de remover este método.']);
        }

        $this->assertCurrentPassword($request, 'Informe a senha atual para remover o iPhone.');

        $user->unlinkIosTwoFactor();
        $request->session()->forget(TotpService::SETUP_SESSION_KEY);

        return back()->with('success', 'App Senhas desvinculado. O Telegram segue como verificação.');
    }

    public function passkeyOptions(Request $request): JsonResponse
    {
        if ($denied = $this->passkeyPasswordJson($request, 'Informe a senha atual para criar a chave de acesso.')) {
            return $denied;
        }

        try {
            $options = $this->passkeys->registrationOptions($request->user(), $request);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'publicKey' => $options['publicKey'] ?? [],
        ]);
    }

    public function storePasskey(Request $request): JsonResponse
    {
        if ($denied = $this->passkeyPasswordJson($request, 'Informe a senha atual para criar a chave de acesso.')) {
            return $denied;
        }

        try {
            $credential = $this->passkeys->register($request->user(), $request, $request->all());
        } catch (\RuntimeException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Chave de acesso salva. Neste aparelho o Safari entra com Face ID, sem o código.',
            'name' => $credential->name,
        ]);
    }

    public function destroyPasskey(Request $request, WebauthnCredential $credential): RedirectResponse
    {
        $user = $request->user();
        if ($credential->user_id !== $user->id) {
            abort(404);
        }

        $this->assertCurrentPassword($request, 'Informe a senha atual para remover a chave de acesso.');

        $credential->delete();

        return back()->with('success', 'Chave de acesso removida. Nos próximos acessos deste aparelho use senha e código.');
    }

    private function pendingTotpSecret(Request $request): ?string
    {
        $secret = $request->session()->get(TotpService::SETUP_SESSION_KEY);

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function assertCurrentPassword(Request $request, string $requiredMessage): void
    {
        $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => $requiredMessage,
        ]);

        if (! Hash::check((string) $request->input('current_password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Senha atual inválida.',
            ]);
        }
    }

    private function passkeyPasswordJson(Request $request, string $requiredMessage): ?JsonResponse
    {
        $password = (string) $request->input('current_password');
        if ($password === '') {
            return response()->json([
                'ok' => false,
                'message' => $requiredMessage,
                'errors' => ['current_password' => [$requiredMessage]],
            ], 422);
        }

        if (! Hash::check($password, (string) $request->user()->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Senha atual inválida.',
                'errors' => ['current_password' => ['Senha atual inválida.']],
            ], 422);
        }

        return null;
    }
}
