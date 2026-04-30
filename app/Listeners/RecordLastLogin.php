<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    public function handle(Login $event): void
    {
        $event->user->timestamps = false;
        $event->user->last_login_at = now();
        $event->user->save();
        $event->user->timestamps = true;
    }
}
