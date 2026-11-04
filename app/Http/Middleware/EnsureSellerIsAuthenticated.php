<?php

namespace App\Http\Middleware;

use App\Exceptions\SellerAccessDeniedException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the seller dashboard (and any future seller-only routes) behind
 * "logged in AND linked to an approved seller" — the same rule the login
 * form enforces via {@see User::sellerOrFail()}, reused here
 * rather than duplicated. This also catches a seller whose account is
 * suspended/rejected mid-session: their existing session is logged out on
 * the next request that hits a `seller.auth`-protected route, not just
 * blocked at login time.
 */
class EnsureSellerIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        try {
            Auth::user()->sellerOrFail();
        } catch (SellerAccessDeniedException $e) {
            // Mirror of EnsureAdminIsAuthenticated: staff opening a /seller
            // URL get a 403 and stay logged in. A seller who is suspended or
            // rejected mid-session is still logged out.
            if ($e->status === null && Auth::user()->isAdmin()) {
                abort(403);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
