<?php

namespace App\Http\Controllers;

use App\Models\Firewall;
use App\Services\PfSenseApiService;
use Illuminate\Http\Request;

class ServicesFreeradiusController extends Controller
{
    protected function getApi(Firewall $firewall)
    {
        return new PfSenseApiService($firewall);
    }

    public function index(Firewall $firewall)
    {
        return redirect()->route('services.freeradius.users.index', $firewall);
    }

    // --- Users ---

    public function users(Firewall $firewall)
    {
        // API does not support listing users (MODEL_REQUIRES_ID)
        // We will pass an empty list and a flag to the view.
        $users = [];
        $listingSupported = false;

        return view('services.freeradius.index', [
            'firewall' => $firewall,
            'tab' => 'users',
            'users' => $users,
            'listingSupported' => $listingSupported,
        ]);
    }

    public function createUser(Firewall $firewall)
    {
        return view('services.freeradius.edit_user', [
            'firewall' => $firewall,
            'user' => null,
        ]);
    }

    public function storeUser(Request $request, Firewall $firewall)
    {
        $api = $this->getApi($firewall);
        try {
            $data = $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                // Add more specific validations as needed
            ]);

            $api->post('/services/freeradius/user', $request->except(['_token']));
            session()->flash('success', 'User created successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create user: ' . $e->getMessage());
        }

        return redirect()->route('services.freeradius.users.index', $firewall);
    }

    public function editUser(Firewall $firewall, $username) // Using username as ID might be tricky if API uses index, but assuming key
    {
        abort_if(auth()->user()->isReadOnly(), 403);
        $api = $this->getApi($firewall);
        $user = null;
        try {
            // Try fetching specific user by username if supported?
            // Usually API uses 'id' parameter which matches the username for FreeRADIUS users
            $response = $api->get('/services/freeradius/user', ['username' => $username]);
            $user = $response['data'] ?? null;
        } catch (\Exception $e) {
            // Handle error
        }

        if (!$user) {
            // If fetch fails, we can't edit.
            session()->flash('error', 'User not found or API does not support fetching by username.');
            return redirect()->route('services.freeradius.users.index', $firewall);
        }

        return view('services.freeradius.edit_user', [
            'firewall' => $firewall,
            'user' => $user,
        ]);
    }

    public function updateUser(Request $request, Firewall $firewall, $username)
    {
        $api = $this->getApi($firewall);
        try {
            // Check if username change is allowed or if it deletes/creates
            $payload = $request->except(['_token', '_method']);
            // Add identifying field if needed, depends on API. 
            // Often 'username' in payload is enough if it matches unique key.
            $api->patch('/services/freeradius/user', $payload);
            session()->flash('success', 'User updated successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user: ' . $e->getMessage());
        }
        return redirect()->route('services.freeradius.users.index', $firewall);
    }

    public function destroyUser(Firewall $firewall, $username)
    {
        $api = $this->getApi($firewall);
        try {
            $api->delete('/services/freeradius/user', ['username' => $username]);
            session()->flash('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete user: ' . $e->getMessage());
        }
        return redirect()->route('services.freeradius.users.index', $firewall);
    }

    // --- Clients ---

    public function clients(Firewall $firewall)
    {
        // API does not support listing clients (MODEL_REQUIRES_ID)
        $clients = [];
        $listingSupported = false;

        return view('services.freeradius.index', [
            'firewall' => $firewall,
            'tab' => 'clients',
            'clients' => $clients,
            'listingSupported' => $listingSupported,
        ]);
    }

    public function createClient(Firewall $firewall)
    {
        return view('services.freeradius.edit_client', [
            'firewall' => $firewall,
            'client' => null,
        ]);
    }

    public function storeClient(Request $request, Firewall $firewall)
    {
        $api = $this->getApi($firewall);
        try {
            $data = $request->validate([
                'client' => 'required|string', // IP address usually
                'secret' => 'required|string',
                'shortname' => 'nullable|string',
            ]);

            $api->post('/services/freeradius/client', $request->except(['_token']));
            session()->flash('success', 'Client created successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create client: ' . $e->getMessage());
        }
        return redirect()->route('services.freeradius.clients.index', $firewall);
    }

    public function editClient(Firewall $firewall, $id) // ID acts as IP or index
    {
        abort_if(auth()->user()->isReadOnly(), 403);
        $api = $this->getApi($firewall);
        $client = null;
        try {
            $response = $api->get('/services/freeradius/client', ['client' => $id]);
            $client = $response['data'] ?? null;
        } catch (\Exception $e) { /* ... */
        }

        if (!$client) {
            session()->flash('error', 'Client not found via API.');
            return redirect()->route('services.freeradius.clients.index', $firewall);
        }

        return view('services.freeradius.edit_client', [
            'firewall' => $firewall,
            'client' => $client,
            'id' => $id,
        ]);
    }

    public function updateClient(Request $request, Firewall $firewall, $id)
    {
        $api = $this->getApi($firewall);
        try {
            $payload = $request->except(['_token', '_method']);
            // For now assuming ID is key.
            $api->patch('/services/freeradius/client', $payload);
            session()->flash('success', 'Client updated successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update client: ' . $e->getMessage());
        }
        return redirect()->route('services.freeradius.clients.index', $firewall);
    }

    public function destroyClient(Firewall $firewall, $id)
    {
        $api = $this->getApi($firewall);
        try {
            $api->delete('/services/freeradius/client', ['client' => $id]);
            session()->flash('success', 'Client deleted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete client: ' . $e->getMessage());
        }
        return redirect()->route('services.freeradius.clients.index', $firewall);
    }

    // --- Interfaces / Settings ---

    public function interfaces(Firewall $firewall)
    {
        $api = $this->getApi($firewall);
        try {
            // Interfaces might strictly require ID too, checking that assumption or just allow fail
            $response = $api->get('/services/freeradius/interface');
            $interfaces = $response['data'] ?? [];
            $listingSupported = true;
        } catch (\Exception $e) {
            $interfaces = [];
            $listingSupported = false;
        }

        return view('services.freeradius.index', [
            'firewall' => $firewall,
            'tab' => 'interfaces',
            'interfaces' => $interfaces,
            'listingSupported' => $listingSupported,
        ]);
    }

    // Stub for Interface create/store if needed

    public function settings(Firewall $firewall)
    {
        // Settings endpoint 404s on this API version.
        $settings = [];
        $settingsSupported = false; // Flag to hide form

        return view('services.freeradius.index', [
            'firewall' => $firewall,
            'tab' => 'settings',
            'settings' => $settings,
            'settingsSupported' => $settingsSupported,
        ]);
    }

    public function updateSettings(Request $request, Firewall $firewall)
    {
        session()->flash('error', 'Global settings update not supported by API.');
        return redirect()->route('services.freeradius.settings', $firewall);
    }
}
