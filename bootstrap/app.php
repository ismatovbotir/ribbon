<?php

use App\Http\Middleware\EnsureAdminIsAuthenticated;
use App\Http\Middleware\EnsureSellerIsAuthenticated;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', SetLocale::class);

        // Per-route-group aliases (not global) — applied only to seller
        // dashboard / admin panel routes going forward. See
        // EnsureSellerIsAuthenticated / EnsureAdminIsAuthenticated.
        $middleware->alias([
            'seller.auth' => EnsureSellerIsAuthenticated::class,
            'admin.auth' => EnsureAdminIsAuthenticated::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
