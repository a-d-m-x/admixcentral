<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    public function index()
    {
        $currentUser = Auth::user();

        if ($currentUser->isGlobalAdmin()) {
            $users = User::with('company')->get();
        } elseif ($currentUser->isCompanyAdmin() || $currentUser->isUser()) {
            $users = User::where('company_id', $currentUser->company_id)->with('company')->get();
        } else {
            abort(403);
        }


        $stats = [
            'total' => $users->count(),
            'users' => $users->where('role', 'user')->count(),
            'admins' => $users->filter(fn($u) => $u->isCompanyAdmin())->count(),
            'global_admins' => $users->filter(fn($u) => $u->isGlobalAdmin())->count(),
        ];

        return view('users.index', compact('users', 'stats'));
    }

    public function create()
    {
        $currentUser = Auth::user();
        $companies = collect();

        if ($currentUser->isGlobalAdmin()) {
            $companies = Company::all();
        } elseif ($currentUser->isCompanyAdmin()) {
            // Standard users cannot create users
            $companies = collect([$currentUser->company]);
        } else {
            abort(403);
        }

        return view('users.create', compact('companies'));
    }

    public function edit(User $user)
    {
        $currentUser = Auth::user();

        // Access control
        if ($currentUser->isCompanyAdmin()) {
            if ($user->company_id !== $currentUser->company_id) {
                abort(403);
            }
        } elseif (!$currentUser->isGlobalAdmin()) {
            abort(403);
        }

        $companies = collect();
        if ($currentUser->isGlobalAdmin()) {
            $companies = Company::all();
        } else {
            $companies = collect([$currentUser->company]);
        }

        return view('users.edit', compact('user', 'companies'));
    }

    public function store(Request $request)
    {
        $currentUser = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'in:admin,user'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        // Role enforcement logic
        // Role enforcement logic
        // Role enforcement logic
        if ($currentUser->isCompanyAdmin()) {
            // Company Admin can only create users for their own company
            $validated['company_id'] = $currentUser->company_id;


            // Cannot create Global Admins
            if (empty($validated['company_id'])) {
                abort(403, 'Must assign users to your company.');
            }
        } elseif ($currentUser->isGlobalAdmin()) {
            // Global Admin logic
            if ($validated['role'] === 'admin' && empty($validated['company_id'])) {
                // Creating a Global Admin
            } elseif (!empty($validated['company_id'])) {
                // Creating a Company Admin or User
            } else {
                // Creating a user without company? Allow for now
            }
        } else {
            abort(403);
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'company_id' => $validated['company_id'],
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $currentUser = Auth::user();

        // Access control
        if ($currentUser->isCompanyAdmin()) {
            if ($user->company_id !== $currentUser->company_id) {
                abort(403);
            }
        } elseif (!$currentUser->isGlobalAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'string', 'in:admin,user'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        // Role enforcement logic for update
        if ($currentUser->isCompanyAdmin()) {
            $validated['company_id'] = $currentUser->company_id;

            // Regular Users cannot promote to Admin
            if ($currentUser->isUser() && $validated['role'] === 'admin') {
                abort(403, 'Users cannot create/promote to Admin.');
            }
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'company_id' => $validated['company_id'],
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $currentUser = Auth::user();

        if ($currentUser->id === $user->id) {
            return back()->with('error', 'You cannot delete yourself.');
        }

        if ($currentUser->isCompanyAdmin()) {
            if ($user->company_id !== $currentUser->company_id) {
                abort(403);
            }
        } elseif (!$currentUser->isGlobalAdmin()) {
            abort(403);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
    public function checkEmail(Request $request)
    {
        $email = $request->input('email');
        $excludeId = $request->input('exclude_id');

        $query = User::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json(['exists' => $exists]);
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'action' => 'required|string|in:delete',
        ]);

        $currentUser = Auth::user();
        $ids = $request->input('ids');
        $action = $request->input('action');

        $users = User::whereIn('id', $ids)->get();

        $count = 0;

        foreach ($users as $user) {
            // Permission check
            if ($currentUser->id === $user->id) {
                continue; // Skip self
            }

            if ($currentUser->isCompanyAdmin()) {
                if ($user->company_id !== $currentUser->company_id) {
                    continue; // Skip users from other companies
                }
            } elseif (!$currentUser->isGlobalAdmin()) {
                abort(403);
            }

            if ($action === 'delete') {
                $user->delete();
                $count++;
            }
        }

        return redirect()->route('users.index')->with('success', "$count user(s) processed successfully.");
    }
    public function geocode(Request $request)
    {
        $address = $request->input('address');
        if (!$address) {
            return response()->json(['error' => 'Address required'], 400);
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'AdmixCentral-Dashboard/1.0'
            ])->get('https://nominatim.openstreetmap.org/search', [
                        'format' => 'json',
                        'q' => $address,
                        'limit' => 1
                    ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Geocoding upstream failed'], 502);
            }

            return response($response->body())
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Geocoding internal error: ' . $e->getMessage()], 500);
        }
    }
}
