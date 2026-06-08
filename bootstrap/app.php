<?php

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\EnsureSystemIsSetup::class);
        $middleware->alias([
            'deny.readonly' => \App\Http\Middleware\DenyReadOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Swallow Reverb/WebSocket broadcast failures so they never crash
        // a user-facing HTTP request. The operation (e.g. saving a firewall)
        // still succeeds — only the real-time push is silently skipped.
        $exceptions->report(function (BroadcastException $e): bool {
            logger()->warning('BroadcastException suppressed: ' . $e->getMessage());
            return false; // mark as handled — do not propagate
        });

        $exceptions->render(function (BroadcastException $e, Request $request) {
            // For API / AJAX requests return a soft warning, not a 500
            if ($request->expectsJson()) {
                return response()->json([
                    'warning' => 'Real-time broadcast unavailable. Changes were saved.',
                ], 200);
            }
            // For web requests, redirect back with a flash notice
            return back()->with('warning', 'Changes were saved, but real-time notifications are temporarily unavailable.');
        });
    })->create();
