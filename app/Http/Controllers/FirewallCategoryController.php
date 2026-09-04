<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class FirewallCategoryController extends Controller
{
    public function index(Firewall $firewall)
    {
        $api = new PfSenseApiService($firewall);
        $categories = [];

        try {
            $categories = $api->getCategories()['data'] ?? [];
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to retrieve firewall categories: ' . $e->getMessage());
        }

        if (request()->wantsJson()) {
            return response()->json($categories);
        }

        return view('firewall.categories', compact('firewall', 'categories'));
    }

    public function store(Request $request, Firewall $firewall)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:10',
            'auto' => 'nullable|boolean',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $color = ltrim($request->input('color', '336699'), '#');
            $data = [
                'name' => $request->input('name'),
                'color' => $color,
                'auto' => $request->has('auto') ? '1' : '0',
            ];
            $api->createCategory($data);

            return redirect()->route('firewall.categories.index', $firewall)
                ->with('success', 'Firewall category created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create category: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Firewall $firewall, string $uuid)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:10',
            'auto' => 'nullable|boolean',
        ]);

        try {
            $api = new PfSenseApiService($firewall);
            $color = ltrim($request->input('color', '336699'), '#');
            $data = [
                'name' => $request->input('name'),
                'color' => $color,
                'auto' => $request->has('auto') ? '1' : '0',
            ];
            $api->updateCategory($uuid, $data);

            return redirect()->route('firewall.categories.index', $firewall)
                ->with('success', 'Firewall category updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update category: ' . $e->getMessage()]);
        }
    }

    public function destroy(Firewall $firewall, string $uuid)
    {
        try {
            $api = new PfSenseApiService($firewall);
            $api->deleteCategory($uuid);

            return redirect()->route('firewall.categories.index', $firewall)
                ->with('success', 'Firewall category deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete category: ' . $e->getMessage()]);
        }
    }
}
