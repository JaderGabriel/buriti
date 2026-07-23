<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Garante que a sessão continue válida apenas para admins ativos.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin || ! $user->is_active) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->wantsJson()) {
                abort(403, 'Acesso ao painel restrito a administradores ativos.');
            }

            return redirect()
                ->guest(route('login'))
                ->withErrors([
                    'login' => 'Acesso ao painel restrito a administradores ativos.',
                ]);
        }

        return $next($request);
    }
}
