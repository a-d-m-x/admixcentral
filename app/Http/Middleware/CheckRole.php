<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return redirect('login');
        }

        $user = $request->user();

        foreach ($roles as $role) {
            if ($role === 'global_admin' && $user->isGlobalAdmin()) {
                return $next($request);
            }
            if ($role === 'company_admin' && $user->isCompanyAdmin()) {
                return $next($request);
            }
            if ($role === 'user' && $user->isUser()) {
                return $next($request);
            }
            if ($role === 'readonly' && $user->isReadOnly()) {
                return $next($request);
            }
            // Allow admins to access user routes if needed, or define hierarchy
            if ($role === 'admin' && ($user->isGlobalAdmin() || $user->isCompanyAdmin())) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
