<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Only Global Admins should see the full list.
        // If Company Admin accesses this, redirect to their own company page.
        // If Company Admin or User accesses this, redirect to their own company page.
        $user = auth()->user();
        if ($user->isCompanyAdmin() || $user->isUser()) {
            return redirect()->route('companies.show', $user->company_id);
        }
        if (!$user->isGlobalAdmin()) {
            abort(403);
        }

        $companies = \App\Models\Company::withCount([
            'users',
            'firewalls',
            'users as admins_count' => function ($query) {
                $query->where('role', 'admin');
            }
        ])->get();

        $stats = [
            'total' => $companies->count(),
            'no_users' => $companies->where('users_count', 0)->count(),
            'no_firewalls' => $companies->where('firewalls_count', 0)->count(),
            'no_address' => $companies->whereNull('address')->count(),
        ];

        return view('companies.index', compact('companies', 'stats'));
    }

    public function create()
    {
        if (!auth()->user()->isGlobalAdmin()) {
            abort(403);
        }
        return view('companies.create');
    }

    public function store(Request $request)
    {
        if (!auth()->user()->isGlobalAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        \App\Models\Company::create($validated);

        return redirect()->route('companies.index')->with('success', 'Company created successfully.');
    }

    public function show(\App\Models\Company $company)
    {
        $user = auth()->user();
        if (($user->isCompanyAdmin() || $user->isUser()) && $user->company_id !== $company->id) {
            abort(403);
        }
        if (!$user->isGlobalAdmin() && !$user->isCompanyAdmin() && !$user->isUser()) {
            abort(403);
        }

        $company->load(['users', 'firewalls']);
        return view('companies.show', compact('company'));
    }

    public function edit(\App\Models\Company $company)
    {
        if (!auth()->user()->isGlobalAdmin()) {
            abort(403);
        }
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, \App\Models\Company $company)
    {
        if (!auth()->user()->isGlobalAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $company->update($validated);

        return redirect()->route('companies.index')->with('success', 'Company updated successfully.');
    }

    public function destroy(\App\Models\Company $company)
    {
        if (!auth()->user()->isGlobalAdmin()) {
            abort(403);
        }

        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted successfully.');
    }
}
