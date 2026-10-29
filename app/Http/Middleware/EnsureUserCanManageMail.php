<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanManageMail
{
    /**
     * Correio e configuração SMTP/IMAP ficam só com o admin.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->canManageMail() === true) {
            return $next($request);
        }

        abort(403, 'O serviço de e-mail é exclusivo do admin.');
    }
}
