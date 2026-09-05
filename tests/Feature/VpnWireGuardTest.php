<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Company, Firewall};
use Illuminate\Support\Facades\Http;

class VpnWireGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function fixture(): array
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin@acme.test',
            'password' => 'password',
            'role' => 'admin',
            'company_id' => $company->id,
        ]);
        $reader = User::create([
            'name' => 'Readonly User',
            'email' => 'reader@acme.test',
            'password' => 'password',
            'role' => 'readonly',
            'company_id' => $company->id,
        ]);
        $firewall = Firewall::create([
            'name' => 'OPNsense Core',
            'company_id' => $company->id,
            'url' => 'https://opnsense.acme.test',
            'os_type' => 'opnsense',
            'auth_method' => 'token',
            'api_token' => 'test-key',
            'api_secret' => 'test-secret',
            'netgate_id' => 'fw-opn-1',
        ]);

        return [$admin, $reader, $firewall];
    }

    public function test_wireguard_index_renders_for_company_admin(): void
    {
        [$admin, $reader, $firewall] = $this->fixture();

        Http::fake([
            'https://opnsense.acme.test/api/wireguard/server/searchServer*' => Http::response(['rows' => []], 200),
            'https://opnsense.acme.test/api/wireguard/client/searchClient*' => Http::response(['rows' => []], 200),
            'https://opnsense.acme.test/api/wireguard/general/get*' => Http::response(['general' => ['enabled' => '1']], 200),
            'https://opnsense.acme.test/api/wireguard/service/status*' => Http::response(['status' => 'running'], 200),
            'https://opnsense.acme.test/api/wireguard/service/show*' => Http::response(['rows' => []], 200),
        ]);

        $response = $this->actingAs($admin)->get("/firewall/{$firewall->id}/vpn/wireguard");

        $response->assertOk();
        $response->assertSee('WireGuard Instances');
        $response->assertSee('WireGuard Endpoints (Peers)');
        $response->assertSee('Active Sessions');
    }

    public function test_readonly_user_is_denied_on_wireguard_mutation_routes(): void
    {
        [$admin, $reader, $firewall] = $this->fixture();

        // General settings update
        $this->actingAs($reader)->post("/firewall/{$firewall->id}/vpn/wireguard/general", [
            'enabled' => 1
        ])->assertStatus(403);

        // Service action
        $this->actingAs($reader)->post("/firewall/{$firewall->id}/vpn/wireguard/service/restart")
            ->assertStatus(403);

        // Tunnel creation
        $this->actingAs($reader)->post("/firewall/{$firewall->id}/vpn/wireguard/tunnels", [
            'name' => 'wg0',
            'tunneladdress' => '10.0.0.1/24',
            'pubkey' => 'test-pub',
            'privkey' => 'test-priv',
        ])->assertStatus(403);

        // Tunnel toggle
        $this->actingAs($reader)->post("/firewall/{$firewall->id}/vpn/wireguard/tunnels/test-uuid/toggle")
            ->assertStatus(403);

        // Tunnel delete
        $this->actingAs($reader)->delete("/firewall/{$firewall->id}/vpn/wireguard/tunnels/test-uuid")
            ->assertStatus(403);

        // Peer creation
        $this->actingAs($reader)->post("/firewall/{$firewall->id}/vpn/wireguard/peers", [
            'name' => 'peer1',
            'pubkey' => 'test-pub',
            'tunneladdress' => '10.0.0.2/32',
        ])->assertStatus(403);

        // Peer toggle
        $this->actingAs($reader)->post("/firewall/{$firewall->id}/vpn/wireguard/peers/test-uuid/toggle")
            ->assertStatus(403);

        // Peer delete
        $this->actingAs($reader)->delete("/firewall/{$firewall->id}/vpn/wireguard/peers/test-uuid")
            ->assertStatus(403);
    }

    public function test_keypair_generation_returns_keys_for_opnsense(): void
    {
        [$admin, $reader, $firewall] = $this->fixture();

        Http::fake([
            'https://opnsense.acme.test/api/wireguard/server/keyPair*' => Http::response([
                'pubkey' => 'pub-key-1234567890=',
                'privkey' => 'priv-key-1234567890=',
                'status' => 'ok',
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson("/firewall/{$firewall->id}/vpn/wireguard/keypair");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'pubkey' => 'pub-key-1234567890=',
                'privkey' => 'priv-key-1234567890=',
            ]);
    }

    public function test_tunnel_creation_and_update_lifecycle(): void
    {
        [$admin, $reader, $firewall] = $this->fixture();

        Http::fake([
            'https://opnsense.acme.test/api/wireguard/server/addServer*' => Http::response([
                'result' => 'saved',
                'uuid' => 'test-uuid-tunnel',
            ], 200),
            'https://opnsense.acme.test/api/wireguard/server/setServer/test-uuid-tunnel*' => Http::response([
                'result' => 'saved',
            ], 200),
            'https://opnsense.acme.test/api/wireguard/server/delServer/test-uuid-tunnel*' => Http::response([
                'result' => 'deleted',
            ], 200),
            'https://opnsense.acme.test/api/wireguard/service/reconfigure*' => Http::response(['result' => 'ok'], 200),
        ]);

        // Create
        $response = $this->actingAs($admin)->post("/firewall/{$firewall->id}/vpn/wireguard/tunnels", [
            'name' => 'wg0',
            'listenport' => 51820,
            'tunneladdress' => '10.10.10.1/24',
            'pubkey' => 'testpubkey',
            'privkey' => 'testprivkey',
            'enabled' => 1,
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Update
        $updateResp = $this->actingAs($admin)->put("/firewall/{$firewall->id}/vpn/wireguard/tunnels/test-uuid-tunnel", [
            'name' => 'wg0_updated',
            'listenport' => 51821,
            'tunneladdress' => '10.10.10.1/24',
            'pubkey' => 'testpubkey',
            'privkey' => 'testprivkey',
            'enabled' => 1,
        ]);
        $updateResp->assertRedirect();
        $updateResp->assertSessionHas('success');

        // Delete
        $delResp = $this->actingAs($admin)->delete("/firewall/{$firewall->id}/vpn/wireguard/tunnels/test-uuid-tunnel");
        $delResp->assertRedirect();
        $delResp->assertSessionHas('success');
    }

    public function test_peer_lifecycle_and_service_actions(): void
    {
        [$admin, $reader, $firewall] = $this->fixture();

        Http::fake([
            'https://opnsense.acme.test/api/wireguard/client/addClient*' => Http::response([
                'result' => 'saved',
                'uuid' => 'test-uuid-peer',
            ], 200),
            'https://opnsense.acme.test/api/wireguard/client/setClient/test-uuid-peer*' => Http::response([
                'result' => 'saved',
            ], 200),
            'https://opnsense.acme.test/api/wireguard/client/delClient/test-uuid-peer*' => Http::response([
                'result' => 'deleted',
            ], 200),
            'https://opnsense.acme.test/api/wireguard/general/set*' => Http::response(['result' => 'saved'], 200),
            'https://opnsense.acme.test/api/wireguard/service/restart*' => Http::response(['result' => 'ok'], 200),
            'https://opnsense.acme.test/api/wireguard/service/reconfigure*' => Http::response(['result' => 'ok'], 200),
        ]);

        // Store peer
        $createPeer = $this->actingAs($admin)->post("/firewall/{$firewall->id}/vpn/wireguard/peers", [
            'name' => 'peer-alice',
            'pubkey' => 'alice-pubkey',
            'tunneladdress' => '10.10.10.5/32',
            'enabled' => 1,
        ]);
        $createPeer->assertRedirect();
        $createPeer->assertSessionHas('success');

        // Update peer
        $updatePeer = $this->actingAs($admin)->put("/firewall/{$firewall->id}/vpn/wireguard/peers/test-uuid-peer", [
            'name' => 'peer-alice-modified',
            'pubkey' => 'alice-pubkey',
            'tunneladdress' => '10.10.10.5/32',
            'enabled' => 1,
        ]);
        $updatePeer->assertRedirect();
        $updatePeer->assertSessionHas('success');

        // Delete peer
        $delPeer = $this->actingAs($admin)->delete("/firewall/{$firewall->id}/vpn/wireguard/peers/test-uuid-peer");
        $delPeer->assertRedirect();
        $delPeer->assertSessionHas('success');

        // General update
        $genUpdate = $this->actingAs($admin)->post("/firewall/{$firewall->id}/vpn/wireguard/general", [
            'enabled' => 1,
        ]);
        $genUpdate->assertRedirect();
        $genUpdate->assertSessionHas('success');

        // Service restart
        $svcRestart = $this->actingAs($admin)->post("/firewall/{$firewall->id}/vpn/wireguard/service/restart");
        $svcRestart->assertRedirect();
        $svcRestart->assertSessionHas('success');
    }
}
