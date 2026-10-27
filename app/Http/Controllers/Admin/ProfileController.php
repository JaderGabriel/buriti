<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAvatarRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\LoginActivity;
use App\Services\Auth\TotpService;
use App\Services\AuthSecurityService;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private AvatarService $avatars,
        private AuthSecurityService $security,
        private TotpService $totp,
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

        $request->session()->put(TotpService::SETUP_SESSION_KEY, $this->totp->generateSecret());

        return back()->with('success', 'Adicione o código no app Senhas do iPhone e confirme os 6 dígitos.');
    }

    public function confirmIosTwoFactor(Request $request): RedirectResponse
    {
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

        $request->validate([
            'current_password' => ['required', 'string'],
        ], [
            'current_password.required' => 'Informe a senha atual para remover o iPhone.',
        ]);

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            return back()->withErrors(['current_password' => 'Senha atual inválida.']);
        }

        $user->unlinkIosTwoFactor();
        $request->session()->forget(TotpService::SETUP_SESSION_KEY);

        return back()->with('success', 'App Senhas desvinculado. O Telegram segue como verificação.');
    }

    private function pendingTotpSecret(Request $request): ?string
    {
        $secret = $request->session()->get(TotpService::SETUP_SESSION_KEY);

        return is_string($secret) && $secret !== '' ? $secret : null;
    }
}
