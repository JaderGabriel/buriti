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
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private AvatarService $avatars,
        private AuthSecurityService $security,
        private AttachmentService $attachments,
        private AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $this->authorizeAdmin();

        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(12)->withQueryString(),
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
            'loginActivities' => collect(),
            'auditLogs' => collect(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['avatar'], $data['password_confirmation']);

        $data['avatar_path'] = $this->avatars->store($request->file('avatar'));
        $data['is_admin'] = $request->boolean('is_admin');

        $user = User::query()->create($data);

        $this->audit->record('user.created', $user, [
            'summary' => $user->name,
            'email' => $user->email,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário criado.');
    }

    public function edit(User $user): View
    {
        $this->authorizeAdmin();
        $user->load(['attachments', 'trashedAttachments.deleter']);

        return view('admin.users.form', [
            'user' => $user,
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
        unset($data['password_confirmation']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $willBeAdmin = $request->boolean('is_admin');
        if ($user->is_admin && ! $willBeAdmin && $this->security->adminCount() <= 1) {
            return back()->withErrors(['is_admin' => 'Não é possível remover o último administrador.']);
        }

        $data['is_admin'] = $willBeAdmin;
        $user->update($data);

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

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->record(
            $user->is_active ? 'user.reactivated' : 'user.deactivated',
            $user,
            ['summary' => $user->name, 'email' => $user->email],
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', $user->is_active ? 'Usuário reativado.' : 'Usuário desativado.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin === true, 403);
    }
}
