<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateAvatarRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AttachmentService;
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
    ) {}

    public function index(): View
    {
        $this->authorizeAdmin();

        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(12),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdmin();

        return view('admin.users.form', [
            'user' => new User(['is_admin' => true]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['avatar'], $data['password_confirmation']);

        $data['avatar_path'] = $this->avatars->store($request->file('avatar'));
        $data['is_admin'] = $request->boolean('is_admin');

        User::query()->create($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário criado.');
    }

    public function edit(User $user): View
    {
        $this->authorizeAdmin();
        $user->load('attachments');

        return view('admin.users.form', [
            'user' => $user,
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
        $this->attachments->deleteAllFor($user);
        $user->delete();

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

        return redirect()
            ->route('admin.users.index')
            ->with('success', $user->is_active ? 'Usuário reativado.' : 'Usuário desativado.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin === true, 403);
    }
}
