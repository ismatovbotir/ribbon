<?php

use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\EnsureAdminIsAuthenticated;
use App\Http\Middleware\EnsureSellerIsAuthenticated;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportComputed\CannotCallComputedDirectlyException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->appendToGroup('web', SetLocale::class);
        $middleware->appendToGroup('web', CaptureAttribution::class);

        // Per-route-group aliases (not global) — applied only to seller
        // dashboard / admin panel routes going forward. See
        // EnsureSellerIsAuthenticated / EnsureAdminIsAuthenticated.
        $middleware->alias([
            'seller.auth' => EnsureSellerIsAuthenticated::class,
            'admin.auth' => EnsureAdminIsAuthenticated::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
        ]);

        // Telegram POSTs webhook updates with no Laravel CSRF token —
        // TelegramWebhookController verifies the request is genuinely from
        // Telegram itself via the X-Telegram-Bot-Api-Secret-Token header
        // instead (see Setting::telegram_webhook_secret).
        $middleware->validateCsrfTokens(except: ['telegram/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tampered Livewire requests: answer 400 and keep them out of the error log.
        $tampering = [
            CannotUpdateLockedPropertyException::class,
            CannotCallComputedDirectlyException::class,
            CorruptComponentPayloadException::class,
            MethodNotFoundException::class,
        ];
        $exceptions->dontReport($tampering);
        $exceptions->render(function (Throwable $e) use ($tampering) {
            return in_array($e::class, $tampering, true) ? response('', 400) : null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
