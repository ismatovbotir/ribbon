<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response. Inline scripts need the per-request nonce
 * (Vite::cspNonce()). 'unsafe-eval' is required by Alpine, which Livewire ships,
 * and 'unsafe-inline' for styles by Livewire and the style attributes in views.
 *
 * frame-ancestors must gain https://web.telegram.org when the Telegram Web Apps
 * are built, on those routes only.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // The Vite dev server is another origin and needs inline scripts.
        $hot = Vite::isRunningHot();
        $nonce = $hot ? null : Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if ($nonce) {
            $headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://www.googletagmanager.com https://mc.yandex.ru https://mc.yandex.com https://yastatic.net",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://*.google-analytics.com https://www.googletagmanager.com https://mc.yandex.ru https://mc.yandex.com",
            "frame-src 'self' https://mc.yandex.ru https://mc.yandex.com",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
