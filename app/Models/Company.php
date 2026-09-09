<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'description', 'address', 'latitude', 'longitude'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function firewalls()
    {
        return $this->hasMany(Firewall::class);
    }
}
