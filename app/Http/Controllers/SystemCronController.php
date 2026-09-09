<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class SystemCronController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $jobs = [];
        $commands = [];

        try {
            $jobs = $api->getCronJobs()['data'] ?? [];
            $meta = $api->getCronJob();
            $rawCommands = $meta['job']['command'] ?? [];
            foreach ($rawCommands as $cmdKey => $cmdData) {
                $commands[$cmdKey] = is_array($cmdData) ? ($cmdData['value'] ?? $cmdKey) : $cmdData;
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to retrieve cron jobs: ' . $e->getMessage());
        }

        if (request()->wantsJson()) {
            return response()->json(['jobs' => $jobs, 'commands' => $commands]);
        }

        return view('system.cron', compact('firewall', 'jobs', 'commands'));
    }

    public function store(Request $request, Firewall $firewall)
    {
        $request->validate([
            'command' => 'required|string',
            'description' => 'nullable|string',
            'minutes' => 'nullable|string',
            'hours' => 'nullable|string',
            'days' => 'nullable|string',
            'months' => 'nullable|string',
            'weekdays' => 'nullable|string',
            'who' => 'nullable|string',
            'parameters' => 'nullable|string',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $data = $request->all();
            $data['enabled'] = $request->has('enabled') && $request->input('enabled') ? '1' : '0';
            $api->createCronJob($data);

            return redirect()->route('firewall.system.cron', $firewall)
                ->with('success', 'Cron job created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create cron job: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Firewall $firewall, string $uuid)
    {
        $request->validate([
            'command' => 'required|string',
            'description' => 'nullable|string',
            'minutes' => 'nullable|string',
            'hours' => 'nullable|string',
            'days' => 'nullable|string',
            'months' => 'nullable|string',
            'weekdays' => 'nullable|string',
            'who' => 'nullable|string',
            'parameters' => 'nullable|string',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $data = $request->all();
            $data['enabled'] = $request->has('enabled') && $request->input('enabled') ? '1' : '0';
            $api->updateCronJob($uuid, $data);

            return redirect()->route('firewall.system.cron', $firewall)
                ->with('success', 'Cron job updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update cron job: ' . $e->getMessage()]);
        }
    }

    public function destroy(Firewall $firewall, string $uuid)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteCronJob($uuid);

            return redirect()->route('firewall.system.cron', $firewall)
                ->with('success', 'Cron job deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete cron job: ' . $e->getMessage()]);
        }
    }

    public function toggle(Firewall $firewall, string $uuid)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $jobData = $api->getCronJob($uuid);
            $currentEnabled = $jobData['job']['enabled'] ?? '0';
            $newEnabled = ($currentEnabled === '1' || $currentEnabled === 1) ? '0' : '1';

            $payload = [
                'enabled' => $newEnabled,
                'minutes' => $jobData['job']['minutes'] ?? '*',
                'hours' => $jobData['job']['hours'] ?? '*',
                'days' => $jobData['job']['days'] ?? '*',
                'months' => $jobData['job']['months'] ?? '*',
                'weekdays' => $jobData['job']['weekdays'] ?? '*',
                'who' => $jobData['job']['who'] ?? 'root',
                'command' => is_array($jobData['job']['command'] ?? '') ? array_key_first(array_filter($jobData['job']['command'], fn($v) => !empty($v['selected']))) : ($jobData['job']['command'] ?? ''),
                'parameters' => $jobData['job']['parameters'] ?? '',
                'description' => $jobData['job']['description'] ?? '',
            ];

            $api->updateCronJob($uuid, $payload);

            return back()->with('success', 'Cron job status updated.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to toggle cron job: ' . $e->getMessage()]);
        }
    }
}
