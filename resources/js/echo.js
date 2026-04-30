/**
 * Laravel Echo Configuration for Reverb WebSocket
 * 
 * This file initializes Laravel Echo to connect to the Reverb WebSocket server
 * for real-time updates from devices.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Echo
window.Pusher = Pusher;

const reverbKey = window.AdmixConfig?.reverb?.key || import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = window.AdmixConfig?.reverb?.host || import.meta.env.VITE_REVERB_HOST;
const reverbPort = window.AdmixConfig?.reverb?.port || import.meta.env.VITE_REVERB_PORT || 8080;
const reverbScheme = window.AdmixConfig?.reverb?.scheme || import.meta.env.VITE_REVERB_SCHEME || 'https';

if (reverbKey) {
    // Initialize Echo with Reverb configuration
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: (reverbScheme === 'https'),
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        // Stable connection timeouts — previously set to 1s which caused
        // constant reconnect storms and made WS health detection unreliable.
        activityTimeout: 120000,      // 2 min idle before sending ping (was 1s)
        pongTimeout: 30000,           // 30s to wait for pong before disconnect (was 1s)
        unavailableTimeout: 10000,    // 10s before marking connection unavailable (was 1s)
    });

    // Connection Status Handling
    const updateStatus = (status, color, message) => {
        // New Alpine hook
        if (window.updateSystemStatus) {
            window.updateSystemStatus(status);
        }
        
        if (import.meta.env.DEV) {
            // console.log(`[WebSocket] ${status}: ${message}`);
        }
    };

    // Stable flat boolean for coordinator/card state detection.
    // Avoids deep property chains like Echo?.connector?.pusher?.connection?.state.
    window.wsConnected = false;

    window.Echo.connector.pusher.connection.bind('connected', () => {
        window.wsConnected = true;
        updateStatus('connected', 'bg-green-500', 'Connected');
    });

    window.Echo.connector.pusher.connection.bind('unavailable', () => {
        window.wsConnected = false;
        updateStatus('disconnected', 'bg-red-500', 'Disconnected');
    });

    window.Echo.connector.pusher.connection.bind('failed', () => {
        window.wsConnected = false;
        updateStatus('disconnected', 'bg-red-600', 'Connection Failed');
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        window.wsConnected = false;
        updateStatus('disconnected', 'bg-gray-400', 'Disconnected');
    });

    window.Echo.connector.pusher.connection.bind('connecting', () => {
        updateStatus('connecting', 'bg-yellow-400', 'Connecting...');
    });

} else {
    console.warn('Reverb App Key not found. Real-time updates disabled.');
    // Mock Echo to prevent crashes in other components
    window.Echo = {
        private: () => ({ listen: () => {} }),
        channel: () => ({ listen: () => {} }),
        connector: { pusher: { connection: { bind: () => {} } } }
    };
}

export default window.Echo;
