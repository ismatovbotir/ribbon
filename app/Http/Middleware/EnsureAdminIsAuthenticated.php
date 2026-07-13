<?php

namespace App\Http\Middleware;

use App\Exceptions\AdminAccessDeniedException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the admin/CMS panel behind "logged in AND holds an admin role" — the
 * same rule the unified login form enforces via {@see User::adminRoleOrFail()},
 * reused here rather than duplicated. Mirrors
 * {@see EnsureSellerIsAuthenticated} for the seller side: a staff member
 * whose admin role is revoked mid-session is logged out on the next request
 * that hits an `admin.auth`-protected route, not just blocked at login time.
 */
class EnsureAdminIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        try {
            Auth::user()->adminRoleOrFail();
        } catch (AdminAccessDeniedException) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
