<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class SetupController extends Controller
{
    public function welcome()
    {
        return view('setup.welcome');
    }

    public function store(Request $request, \App\Services\SystemConfigurationService $configService)
    {
        // Fix CRIT-03: Prevent setup if admin already exists
        if (User::exists()) {
            abort(403, 'Setup has already been completed.');
        }

        $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i'], // Basic hostname validation
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Apply Hostname Configuration
        $hostname = $request->hostname;
        $configService->updateSystemHostname($hostname, 'http'); // Defaulting to http for initial setup to avoid SSL issues immediately, or detect from request?

        // If request was secure, keep it secure.
        if ($request->secure()) {
            $configService->updateSystemHostname($hostname, 'https');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        // Redirect to the new hostname
        $protocol = $request->secure() ? 'https://' : 'http://';
        return redirect($protocol . $hostname . '/login')->with('status', 'Admin account created! Please login.');
    }
}
