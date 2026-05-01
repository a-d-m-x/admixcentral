<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirewallConfigBackup extends Model
{
    protected $fillable = [
        'firewall_id',
        'path',
        'sha256_hash',
        'size_bytes',
        'pulled_at',
        'last_attempted_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'pulled_at' => 'datetime',
        'last_attempted_at' => 'datetime',
    ];

    public function firewall()
    {
        return $this->belongsTo(Firewall::class);
    }
}
