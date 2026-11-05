<?php

namespace App\Http\Middleware;

use App\Support\Attribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Remembers UTM params and the external referrer from storefront page loads. */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession() && ! $request->is('admin*', 'seller/*', 'livewire*')) {
            Attribution::capture($request);
        }

        return $next($request);
    }
}
