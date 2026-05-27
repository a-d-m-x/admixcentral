<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\SslManagerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

use App\Models\SystemSetting;

class SystemSslController extends Controller
{
    public function store(Request $request, SslManagerService $sslManager)
    {
        $request->validate([
            'domain'           => 'required|string|regex:/^(?!:\/\/)(?=.{1,255}$)((.{1,63}\.){1,127}(?![0-9]*$)[a-z0-9-]+\.?)$/i',
            'email'            => 'required|email',
            'challenge_method' => 'required|in:http,cloudflare',
            'cf_token'         => 'nullable|string',
            'cf_zone_id'       => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/'],
        ]);

        try {
            $domain        = $request->input('domain');
            $method        = $request->input('challenge_method', 'http');
            $cfToken       = null;

            // Persist challenge method choice regardless of outcome
            SystemSetting::updateOrCreate(
                ['key' => 'ssl_challenge_method'],
                ['value' => $method]
            );

            if ($method === 'cloudflare') {
                // Validate CF-specific fields
                if ($request->filled('cf_zone_id')) {
                    SystemSetting::updateOrCreate(
                        ['key' => 'cf_zone_id'],
                        ['value' => $request->input('cf_zone_id')]
                    );
                }

                if ($request->filled('cf_token')) {
                    // Encrypt token before storing — never stored in plaintext
                    SystemSetting::updateOrCreate(
                        ['key' => 'cf_api_token'],
                        ['value' => encrypt($request->input('cf_token'))]
                    );
                }

                // Resolve token from DB (use newly saved or previously saved)
                $encryptedToken = SystemSetting::where('key', 'cf_api_token')->value('value');
                if (!$encryptedToken) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A Cloudflare API token is required. Please enter your token.',
                    ], 422);
                }

                try {
                    $cfToken = decrypt($encryptedToken);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stored Cloudflare token is invalid or the app key has changed. Please re-enter your token.',
                    ], 422);
                }
            }

            $result = $sslManager->install($domain, $request->input('email'), $method, $cfToken);

            if ($result['success']) {
                // Update database settings to reflect the change in UI
                SystemSetting::updateOrCreate(['key' => 'site_url'],      ['value' => $domain]);
                SystemSetting::updateOrCreate(['key' => 'site_protocol'], ['value' => 'https']);
                SystemSetting::updateOrCreate(['key' => 'ssl_email'],     ['value' => $request->input('email')]);

                return response()->json([
                    'success'  => true,
                    'message'  => 'SSL Certificate installed successfully. Reloading...',
                    'redirect' => 'https://' . $domain . '/system/settings',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 500);

        } catch (\Exception $e) {
            Log::error('SSL Install Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, SslManagerService $sslManager)
    {
        try {
            // Retrieve domain from settings if not provided in request (safer)
            $domain = SystemSetting::where('key', 'site_url')->value('value');

            if (!$domain) {
                return response()->json(['success' => false, 'message' => 'Domain not found in settings.'], 404);
            }

            $result = $sslManager->uninstall($domain);

            if ($result['success']) {
                // Update database settings to revert to HTTP
                SystemSetting::updateOrCreate(['key' => 'site_protocol'], ['value' => 'http']);

                return redirect('http://' . $domain . '/system/settings');
            }

            return redirect()->back()->withErrors(['message' => $result['message']]);

        } catch (\Exception $e) {
            Log::error('SSL Uninstall Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Returns non-sensitive SSL/Cloudflare status for the settings modal.
     * The API token is NEVER included — only a boolean indicating it exists.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'challenge_method'    => SystemSetting::where('key', 'ssl_challenge_method')->value('value') ?? 'http',
            'cf_token_configured' => SystemSetting::where('key', 'cf_api_token')->exists(),
            'cf_zone_id'          => SystemSetting::where('key', 'cf_zone_id')->value('value') ?? '',
            'ssl_active'          => (SystemSetting::where('key', 'site_protocol')->value('value') === 'https'),
            'ssl_email'           => SystemSetting::where('key', 'ssl_email')->value('value') ?? '',
        ]);
    }
}
