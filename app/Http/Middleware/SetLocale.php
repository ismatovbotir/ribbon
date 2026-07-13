<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Determine the active locale for this request.
     *
     * Order of precedence:
     *  (a) `?lang=` query string, if present and one of the supported
     *      locales — also persisted to the session so it sticks across
     *      subsequent requests without the query param.
     *  (b) `session('locale')`, if present and one of the supported
     *      locales.
     *  (c) otherwise, fall through to the app's configured default
     *      locale (`config('app.locale')`) by not calling setLocale at
     *      all.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('ribbon.locales');

        $queryLocale = $request->query('lang');

        if (is_string($queryLocale) && in_array($queryLocale, $locales, true)) {
            $request->session()->put('locale', $queryLocale);

            App::setLocale($queryLocale);
        } elseif (in_array($sessionLocale = $request->session()->get('locale'), $locales, true)) {
            App::setLocale($sessionLocale);
        }

        return $next($request);
    }
}
