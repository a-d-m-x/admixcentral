<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Releases the PHP session write-lock before executing the controller action.
 *
 * PHP (and Laravel's database session driver) holds an exclusive write-lock on
 * the session for the entire duration of each request. When controllers make
 * slow outbound HTTP calls (e.g. to pfSense firewalls), this lock blocks every
 * other concurrent request from the same user — including simple page loads
 * and the login page — until the slow request finishes.
 *
 * This middleware writes any already-queued session data and releases the lock
 * early, allowing other requests to proceed in parallel. Session data that was
 * already read at the start of the request pipeline (auth, CSRF, flash) is
 * unaffected. New writes after this point are flushed by Laravel's own session
 * termination middleware as normal.
 *
 * Apply this to any route group that makes outbound network calls — specifically
 * all firewall-specific routes that proxy to pfSense API endpoints.
 */
class ReleaseSession
{
    public function handle(Request $request, Closure $next)
    {
        // Write and close the session as early as possible so that the
        // session lock is released before any blocking I/O begins.
        session_write_close();

        return $next($request);
    }
}
