<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyReadOnly
{
    /**
     * Block readonly users from accessing any write route.
     * Apply this middleware to route groups or individual routes
     * that a readonly user must never reach.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect('login');
        }

        if ($user->isReadOnly()) {
            abort(403, 'Read-only users cannot perform this action.');
        }

        return $next($request);
    }
}
