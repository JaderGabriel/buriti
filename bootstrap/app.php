<?php

use App\Http\Middleware\EnsureJirauAccess;
use App\Http\Middleware\EnsureUserCanManageMail;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Support\AuthHome;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => AuthHome::url(Auth::user()));
        $middleware->validateCsrfTokens(except: [
            'webhooks/telegram/*',
        ]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'mail.admin' => EnsureUserCanManageMail::class,
            'jirau' => EnsureJirauAccess::class,
        ]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
