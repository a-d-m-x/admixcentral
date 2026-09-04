<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Firewall;
use App\Models\Company;

class EnsureTenantScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect('login');
        }

        // Global admins can see everything
        if ($user->isGlobalAdmin()) {
            return $next($request);
        }

        // Non-global admins MUST have an assigned company
        if (!$user->company_id) {
            abort(403, 'Unauthorized access: user has no assigned company.');
        }

        // If accessing a firewall, ensure it belongs to their company
        $firewallParam = $request->route('firewall');
        if ($firewallParam) {
            if ($firewallParam instanceof Firewall) {
                $firewall = $firewallParam;
            } else {
                $firewall = Firewall::where('netgate_id', $firewallParam)
                    ->orWhere('id', $firewallParam)
                    ->first();
            }

            if (!$firewall || (int) $firewall->company_id !== (int) $user->company_id) {
                abort(403, 'Unauthorized access to this firewall.');
            }
        }

        // If accessing a company, ensure it's their company
        $companyParam = $request->route('company');
        if ($companyParam) {
            $companyId = $companyParam instanceof Company
                ? $companyParam->id
                : (int) $companyParam;

            if ((int) $companyId !== (int) $user->company_id) {
                abort(403, 'Unauthorized access to this company.');
            }
        }

        return $next($request);
    }
}
