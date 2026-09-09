<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class SetupController extends Controller
{
    public function welcome()
    {
        if (file_exists(storage_path('app/setup.lock')) || User::exists()) {
            return redirect()->route('login');
        }
        return view('setup.welcome');
    }

    public function store(Request $request, \App\Services\SystemConfigurationService $configService)
    {
        if (file_exists(storage_path('app/setup.lock')) || User::exists()) {
            abort(403, 'Setup has already been completed.');
        }

        $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i'], // Basic hostname validation
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Atomic lock and user creation inside transaction before external system changes
        DB::transaction(function () use ($request) {
            if (file_exists(storage_path('app/setup.lock')) || User::lockForUpdate()->exists()) {
                abort(403, 'Setup has already been completed.');
            }

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'company_id' => null,
            ]);

            @file_put_contents(storage_path('app/setup.lock'), json_encode([
                'completed_at' => now()->toIso8601String(),
                'admin_email' => $request->email,
            ]));
        });

        // Apply Hostname Configuration AFTER atomic creation succeeds
        $hostname = $request->hostname;
        $protocol = $request->secure() ? 'https' : 'http';
        $configService->updateSystemHostname($hostname, $protocol);

        // Redirect to the new hostname
        $fullProtocol = $request->secure() ? 'https://' : 'http://';
        return redirect($fullProtocol . $hostname . '/login')->with('status', 'Admin account created! Please login.');
    }
}
