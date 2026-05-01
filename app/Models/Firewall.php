<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Firewall extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'url',
        'auth_method',
        'api_key',
        'api_secret',
        'api_token',
        'description',
        'is_dirty',
        'netgate_id',
        'address',
        'latitude',
        'longitude',
        'ssh_port',
        'ssh_username',
        'ssh_password',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'api_token' => 'encrypted',
        'ssh_password' => 'encrypted',
        'is_dirty' => 'boolean',
    ];

    public function getRouteKeyName()
    {
        return 'netgate_id';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all device connections for this firewall.
     */
    public function deviceConnections()
    {
        return $this->hasMany(DeviceConnection::class);
    }

    /**
     * Get the most recent active connection for this firewall.
     */
    public function activeConnection()
    {
        return $this->hasOne(DeviceConnection::class)
            ->whereNull('disconnected_at')
            ->latest('connected_at');
    }

    /**
     * Check if this firewall has an active WebSocket connection.
     */
    public function isConnectedViaWebSocket(): bool
    {
        return $this->activeConnection()->exists();
    }

    /**
     * Get the config backup for this firewall.
     */
    public function configBackup()
    {
        return $this->hasOne(FirewallConfigBackup::class);
    }
}
