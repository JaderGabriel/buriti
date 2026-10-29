<?php

namespace App\Http\Middleware;

use App\Models\Jirau;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureJirauAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()
                ->guest(route('login'))
                ->withErrors(['login' => 'Esta conta está desativada.']);
        }

        if ($user->can('viewAny', Jirau::class)) {
            return $next($request);
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()
            ->guest(route('login'))
            ->withErrors(['login' => 'Esta conta não tem acesso a um Jirau.']);
    }
}
