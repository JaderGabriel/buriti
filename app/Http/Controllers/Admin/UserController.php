<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateAvatarRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\AuthSecurityService;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private AvatarService $avatars,
        private AuthSecurityService $security,
        private AttachmentService $attachments,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(12)->withQueryString(),
            'sessions' => $this->security->activeSessions($request->session()->getId()),
            'sessionDriver' => config('session.driver'),
            'loginActivities' => LoginActivity::query()
                ->with('user:id,name,email,username')
                ->orderByDesc('created_at')
                ->paginate(20, ['*'], 'logs_page')
                ->withQueryString(),
            'auditLogs' => AuditLog::query()
                ->with('user:id,name,email')
                ->orderByDesc('created_at')
                ->paginate(25, ['*'], 'audit_page')
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdmin();

        return view('admin.users.form', [
            'user' => new User(['is_admin' => true]),
            'sessions' => [],
            'sessionDriver' => config('session.driver'),
            'loginActivities' => collect(),
            'auditLogs' => collect(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['avatar'], $data['password_confirmation'], $data['is_admin']);

        $data['avatar_path'] = $this->avatars->store($request->file('avatar'));

        $user = new User($data);
        $user->forceFill([
            'is_admin' => $request->boolean('is_admin'),
            'is_active' => true,
        ])->save();

        $this->audit->record('user.created', $user, [
            'summary' => $user->name,
            'email' => $user->email,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário criado.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeAdmin();
        $user->load(['attachments', 'trashedAttachments.deleter']);

        $currentId = $user->is(auth()->user()) ? $request->session()->getId() : null;

        return view('admin.users.form', [
            'user' => $user,
            'sessions' => $this->security->sessionsFor($user, $currentId),
            'sessionDriver' => config('session.driver'),
            'loginActivities' => LoginActivity::query()
                ->forUser($user)
                ->orderByDesc('created_at')
                ->limit(40)
                ->get(),
            'auditLogs' => AuditLog::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere(function ($inner) use ($user) {
                            $inner->where('subject_type', $user->getMorphClass())
                                ->where('subject_id', $user->id);
                        });
                })
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(40)
                ->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        unset($data['password_confirmation'], $data['is_admin']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $willBeAdmin = $request->boolean('is_admin');
        if ($user->is_admin && ! $willBeAdmin && $this->security->adminCount() <= 1) {
            return back()->withErrors(['is_admin' => 'Não é possível remover o último administrador.']);
        }

        $user->fill($data);
        $user->forceFill(['is_admin' => $willBeAdmin])->save();

        $this->audit->record('user.updated', $user, [
            'summary' => $user->name,
            'email' => $user->email,
        ]);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Dados do usuário atualizados.');
    }

    public function updateAvatar(UpdateAvatarRequest $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($request->boolean('remove_avatar')) {
            $this->avatars->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);

            return redirect()
                ->route('admin.users.edit', $user)
                ->with('success', 'Foto de perfil removida.');
        }

        $path = $this->avatars->replace($user->avatar_path, $request->file('avatar'));
        $user->update(['avatar_path' => $path]);

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('success', 'Foto de perfil atualizada.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'Você não pode excluir a própria conta por aqui.']);
        }

        if ($user->is_admin && $this->security->adminCount() <= 1) {
            return back()->withErrors(['user' => 'Não é possível excluir o último administrador.']);
        }

        $this->avatars->delete($user->avatar_path);
        $this->attachments->deleteAllFor($user, auth()->id());
        $summary = $user->name;
        $userId = $user->id;
        $user->delete();

        $this->audit->record('user.deleted', null, [
            'summary' => $summary,
            'user_id' => $userId,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário removido.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'Você não pode desativar a própria conta.']);
        }

        if ($user->is_active && $user->is_admin && $this->security->adminCount() <= 1) {
            return back()->withErrors(['user' => 'Não é possível desativar o último administrador ativo.']);
        }

        $willBeActive = ! $user->is_active;
        $user->forceFill(['is_active' => $willBeActive])->save();

        if (! $willBeActive) {
            $this->security->destroyAllSessions($user);
        }

        $this->audit->record(
            $user->is_active ? 'user.reactivated' : 'user.deactivated',
            $user,
            ['summary' => $user->name, 'email' => $user->email],
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', $user->is_active ? 'Usuário reativado.' : 'Usuário desativado.');
    }

    public function destroySession(Request $request, User $user, string $session): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($user->is(auth()->user()) && hash_equals($request->session()->getId(), $session)) {
            return back()->withErrors(['session' => 'Não é possível revogar a sessão atual por aqui. Use Sair.']);
        }

        $this->security->destroySession($user, $session);

        return back()->with('success', 'Sessão revogada.');
    }

    public function destroyAllSessions(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($user->is(auth()->user())) {
            $count = $this->security->destroyOtherSessions($user, $request->session()->getId());

            return back()->with('success', $count > 0
                ? "{$count} outra(s) sessão(ões) revogada(s)."
                : 'Não havia outras sessões para revogar.');
        }

        $count = $this->security->destroyAllSessions($user);

        return back()->with('success', $count > 0
            ? "{$count} sessão(ões) revogada(s)."
            : 'Não havia sessões ativas.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin === true, 403);
    }
}
