<?php

namespace App\Http\Controllers\Jirau;

use App\Enums\JirauStaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jirau\StoreStaffRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Jirau\JirauAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private JirauAccess $access,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->access->assertManage($request->user());

        return view('jirau.staff', [
            'jirau' => null,
            'isAdmin' => true,
            'staff' => User::query()
                ->where(function (Builder $query) {
                    $query->where('is_admin', true)
                        ->orWhereNotNull('jirau_role');
                })
                ->orderByDesc('is_admin')
                ->orderBy('name')
                ->get(),
            'roles' => JirauStaffRole::options(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $this->access->assertManage($request->user());

        $data = $request->validated();
        $user = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill([
            'is_admin' => false,
            'is_active' => true,
            'jirau_role' => JirauStaffRole::from($data['jirau_role']),
        ])->save();

        $this->audit->record('jirau.staff_created', $user, [
            'summary' => $user->name,
            'role' => $user->jirau_role?->value,
        ]);

        return redirect()
            ->route('jirau.staff')
            ->with('success', $user->name.' entrou na equipe de TI como '.$user->jirau_role?->label().'. Usuário: '.$user->username.' — envie a senha por um canal seguro.');
    }
}
