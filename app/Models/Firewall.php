<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Firewall extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'name',
        'os_type',
        'url',
        'tls_public_key_pin',
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
        'ssh_host_key_fingerprint',
        'ssh_username',
        'ssh_password',
    ];

    /**
     * Prevent credential leakage in JSON serialization (toArray, toJson, API responses, logs).
     * Use makeVisible() explicitly when credentials are needed (e.g., backup export).
     */
    protected $hidden = [
        'api_key',
        'api_secret',
        'api_token',
        'ssh_password',
        'ssh_username',
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
        return 'id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        // Accept both numeric id and netgate_id for backwards compatibility
        return $this->where('id', $value)
            ->orWhere('netgate_id', $value)
            ->first() ?? abort(404);
    }

    public function isOpnSense(): bool
    {
        return ($this->os_type ?? 'pfsense') === 'opnsense';
    }

    public function isPfSense(): bool
    {
        return ($this->os_type ?? 'pfsense') === 'pfsense';
    }

    public function getOsDisplayNameAttribute(): string
    {
        return $this->isOpnSense() ? 'OPNsense' : 'pfSense';
    }

    public function opnsense(): \App\Services\OpnSenseApiService
    {
        return new \App\Services\OpnSenseApiService($this);
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
