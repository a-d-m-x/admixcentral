<?php

namespace App\Http\Controllers\WebSocket;

use App\Events\DeviceConnectedEvent;
use App\Events\DeviceDisconnectedEvent;
use App\Events\DeviceStatusUpdateEvent;
use App\Http\Controllers\Controller;
use App\Models\Firewall;
use App\Services\WebSocketConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceWebSocketController extends Controller
{
    protected WebSocketConnectionManager $connectionManager;

    public function __construct(WebSocketConnectionManager $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    /**
     * Authenticate a device and return WebSocket connection credentials.
     */
    public function authenticate(Request $request): JsonResponse
    {
        // Check if WebSocket is enabled
        if (!config('app.websocket_enabled', false)) {
            return response()->json([
                'error' => 'WebSocket is not enabled',
                'fallback' => 'rest',
            ], 503);
        }

        $validated = $request->validate([
            'firewall_id' => 'required|exists:firewalls,id',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        // Find and authenticate firewall
        $firewall = Firewall::find($validated['firewall_id']);

        // Verify credentials
        if (
            $firewall->api_key !== $validated['api_key'] ||
            $firewall->api_secret !== $validated['api_secret']
        ) {
            return response()->json([
                'error' => 'Invalid credentials',
            ], 401);
        }

        // Generate short-lived auth token for the connect endpoint
        $authToken = \Illuminate\Support\Str::random(60);
        \Illuminate\Support\Facades\Cache::put("device_auth_{$firewall->id}", $authToken, 60); // 60 seconds validity

        // Return WebSocket connection details
        return response()->json([
            'success' => true,
            'websocket' => [
                'enabled' => true,
                'host' => config('broadcasting.connections.pusher.options.host'),
                'port' => config('broadcasting.connections.pusher.options.port'),
                'app_key' => config('broadcasting.connections.pusher.key'),
                'app_cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'scheme' => config('broadcasting.connections.pusher.options.scheme'),
                'channel' => 'private-device.' . $firewall->id,
            ],
            'firewall_id' => $firewall->id,
            'auth_token' => $authToken,
        ]);
    }

    /**
     * Handle device connection event.
     */
    public function connect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firewall_id' => 'required|exists:firewalls,id',
            'socket_id' => 'required|string',
            'auth_token' => 'required|string',
        ]);

        // Verify auth token
        $storedToken = \Illuminate\Support\Facades\Cache::get("device_auth_{$validated['firewall_id']}");
        if (!$storedToken || hash_equals($storedToken, $validated['auth_token']) === false) {
            return response()->json(['error' => 'Invalid or expired auth token'], 401);
        }
        \Illuminate\Support\Facades\Cache::forget("device_auth_{$validated['firewall_id']}");

        $firewall = Firewall::find($validated['firewall_id']);

        // Register connection
        $connection = $this->connectionManager->registerConnection(
            $firewall,
            $validated['socket_id'],
            $request->ip(),
            $request->userAgent()
        );

        // Fire event
        event(new DeviceConnectedEvent($connection));

        Log::info("Device connected via WebSocket", [
            'firewall_id' => $firewall->id,
            'connection_id' => $connection->connection_id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'connection_id' => $connection->connection_id,
        ]);
    }

    /**
     * Handle incoming messages from devices (status updates, command responses).
     */
    public function handleMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firewall_id' => 'required|exists:firewalls,id',
            'connection_id' => 'required|string',
            'type' => 'required|string|in:status_update,command_response,heartbeat',
            'data' => 'nullable|array',
        ]);

        $firewall = Firewall::find($validated['firewall_id']);

        // Update heartbeat
        $this->connectionManager->updateHeartbeat($validated['connection_id']);

        // Handle different message types
        switch ($validated['type']) {
            case 'status_update':
                // Broadcast status update to dashboard
                event(new DeviceStatusUpdateEvent($firewall, $validated['data'] ?? []));
                Log::debug("Status update received from firewall {$firewall->id}");
                break;

            case 'command_response':
                // TODO: Handle command response (store in cache for retrieval)
                Log::debug("Command response received from firewall {$firewall->id}", [
                    'data' => $validated['data'],
                ]);
                break;

            case 'heartbeat':
                // Heartbeat already updated above
                break;
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle device disconnection.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'required|string',
        ]);

        $success = $this->connectionManager->disconnectDevice($validated['connection_id']);

        if ($success) {
            Log::info("Device disconnected", [
                'connection_id' => $validated['connection_id'],
            ]);
        }

        return response()->json(['success' => $success]);
    }

    /**
     * Get WebSocket server info.
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'enabled' => config('app.websocket_enabled', false),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'scheme' => config('broadcasting.connections.pusher.options.scheme'),
        ]);
    }
}
