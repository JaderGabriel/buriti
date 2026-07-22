<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAvatarRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\LoginActivity;
use App\Services\AuthSecurityService;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private AvatarService $avatars,
        private AuthSecurityService $security,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('admin.profile.edit', [
            'user' => $user,
            'sessions' => $this->security->sessionsFor($user, $request->session()->getId()),
            'sessionDriver' => config('session.driver'),
            'loginActivities' => LoginActivity::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere(function ($inner) use ($user) {
                            $inner->whereNull('user_id')->where('email', $user->email);
                        });
                })
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
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
}
