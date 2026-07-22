<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private AuthSecurityService $security) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            $this->security->recordLoginAttempt($request, null, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Credenciais inválidas.']);
        }

        $user = $request->user();

        if ($user && $user->is_admin === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Esta conta não tem acesso ao painel admin.']);
        }

        if ($user && $user->is_active === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->security->recordLoginAttempt($request, $user, false);

            return back()
                ->withInput($request->only('login', 'remember'))
                ->withErrors(['login' => 'Esta conta está desativada.']);
        }

        $request->session()->regenerate();
        $this->security->markSuccessfulLogin($user, $request);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
