<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates admin sections meant for Super Admin specifically (e.g. Commercial
 * Offers), stacked on top of `admin.auth` rather than replacing it — this
 * only ever runs after that middleware, so Auth::user() is already
 * guaranteed to be a logged-in staffer with *some* admin role. A staffer
 * with a lesser admin role gets a 403, not a redirect to login: they're
 * legitimately authenticated, just not privileged enough for this section
 * — same "authenticated but forbidden" shape as Sellers\Employees\Index's
 * Owner-only check.
 */
class EnsureUserIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);

        return $next($request);
    }
}
