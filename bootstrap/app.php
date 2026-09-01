<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        ]);

        // Trust reverse-proxy headers (X-Forwarded-Proto / -For / -Host) so
        // HTTPS detection, signed-URL verification and client IP for rate
        // limits work correctly behind Cloudflare / load balancers.
        // In a fully locked-down deployment, swap '*' for an explicit allowlist.
        $middleware->trustProxies(at: '*');

        // Security response headers on every HTML document.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
