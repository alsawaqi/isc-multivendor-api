<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Behind Nginx Proxy Manager + Cloudflare: trust forwarded headers so
        // Laravel detects HTTPS / the real host (needed for secure cookies,
        // correct URL generation, and CSRF/session handling).
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();

        $middleware->alias([
            'vendor.active' => \App\Http\Middleware\EnsureVendorIsActive::class,
            'vendor.approved' => \App\Http\Middleware\EnsureVendorIsApproved::class,
        ]);
 
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
