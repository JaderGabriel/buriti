<?php

namespace App\Http\Controllers\Jirau;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\StoreMemberRequest;
use App\Models\Jirau;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Jirau\JirauAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private AuditLogger $audit,
    ) {}

    public function store(StoreMemberRequest $request, Jirau $jirau): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertWritable($request->user(), $jirau);

        $data = $request->validated();
        $user = $data['mode'] === 'attach'
            ? $this->findAttachable($data['login'])
            : $this->createMemberUser($data);

        if ($user->is_admin) {
            throw ValidationException::withMessages([
                'login' => 'Admins já veem todos os Jiraus. Convide quem só deve ver este quadro.',
            ]);
        }

        if ($jirau->members()->whereKey($user->id)->exists()) {
            return redirect()
                ->route('jirau.show', $jirau)
                ->with('warning', $user->name.' já faz parte deste Jirau.');
        }

        $jirau->members()->attach($user->id, ['invited_by' => $request->user()->id]);

        $this->audit->record('jirau.member_added', $jirau, [
            'summary' => $user->username,
            'user_id' => $user->id,
        ]);

        $hint = $data['mode'] === 'create'
            ? ' Usuário: '.$user->username.' — envie a senha por um canal seguro.'
            : '';

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', $user->name.' pode entrar só neste Jirau.'.$hint);
    }

    public function destroy(Request $request, Jirau $jirau, User $user): RedirectResponse
    {
        $this->access->assertManage($request->user());
        $this->access->assertWritable($request->user(), $jirau);

        $jirau->members()->detach($user->id);

        $this->audit->record('jirau.member_removed', $jirau, [
            'summary' => $user->username,
            'user_id' => $user->id,
        ]);

        return redirect()
            ->route('jirau.show', $jirau)
            ->with('success', $user->name.' saiu deste Jirau.');
    }

    private function findAttachable(string $login): User
    {
        $user = User::query()
            ->where(function ($query) use ($login) {
                $query->where('username', strtolower($login))
                    ->orWhere('email', $login);
            })
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => 'Não encontramos esse usuário.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Esta conta está desativada.',
            ]);
        }

        return $user;
    }

    /** @param  array<string, mixed>  $data */
    private function createMemberUser(array $data): User
    {
        $user = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill([
            'is_admin' => false,
            'is_active' => true,
        ])->save();

        return $user;
    }
}
