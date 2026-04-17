<?php

namespace App\Events;

use App\Models\Firewall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceStatusUpdateEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Firewall $firewall;
    public array $statusData;

    /**
     * Create a new event instance.
     */
    public function __construct(Firewall $firewall, array $statusData)
    {
        $this->firewall = $firewall;
        $this->statusData = $statusData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        // Broadcast to firewall dashboard channel so users can see real-time updates
        return new PrivateChannel('firewall.' . $this->firewall->id);
    }

    /**
     * Get the data to broadcast.
     *
     * Returns a flat, explicit payload. Listeners access e.status.* directly —
     * no e.status.data.* nesting. This must be deployed atomically with the
     * matching frontend listener changes in dashboard.blade.php and index.blade.php.
     */
    public function broadcastWith(): array
    {
        // refreshSystemStatus() returns a fully-flat array stored at statusData['data'].
        // Fields like product_version, gateways, interfaces are at statusData['data'][key] directly.
        // There is no statusData['data']['data'] nesting.
        $flat = is_array($this->statusData['data'] ?? null) ? $this->statusData['data'] : [];

        return [
            'firewall_id' => $this->firewall->id,
            'status'      => [
                'online'               => $this->statusData['online']                 ?? false,
                'api_version'          => $this->statusData['api_version'] ?? $flat['api_version'] ?? null,
                'updated_at'           => $this->statusData['updated_at']              ?? now()->toIso8601String(),
                'error'                => $this->statusData['error']                    ?? null,
                'product_version'      => $flat['product_version']   ?? $flat['version'] ?? null,
                'update_available'     => $flat['update_available']                     ?? false,
                'api_update_available' => $flat['api_update_available']                 ?? false,
                'cpu_load_avg'         => $flat['cpu_load_avg']                         ?? null,
                'interfaces'           => $flat['interfaces']                           ?? null,
                'gateways'             => $flat['gateways']                             ?? null,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'firewall.status.update';
    }
}
