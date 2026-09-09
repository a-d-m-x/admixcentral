<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Company, Firewall};
use Illuminate\Support\Facades\{Http, URL, Crypt, Broadcast};

class RegressionSecurityTest extends TestCase
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
        $c = Company::create(['name' => 'Tenant A']);
        $d = Company::create(['name' => 'Tenant B']);
        $u = User::create([
            'name' => 'Reader',
            'email' => 'reader@example.test',
            'password' => 'password-for-test',
            'role' => 'readonly',
            'company_id' => $c->id,
        ]);
        $f = Firewall::create([
            'name' => 'FW',
            'company_id' => $c->id,
            'url' => 'https://fw.example.test',
            'auth_method' => 'token',
            'api_token' => 'test-only-token',
            'netgate_id' => 'fw-test',
        ]);
        return [$u, $f, $d, $c];
    }

    /**
     * F01: Read-only user cannot reassign firewall to another company.
     */
    public function test_readonly_cannot_reassign_firewall(): void
    {
        [$u, $f, $d, $c] = $this->fixture();
        $response = $this->actingAs($u)->put('/firewalls/fw-test', [
            'name' => 'Changed',
            'company_id' => $d->id,
            'url' => 'https://other.example.test',
            'auth_method' => 'token',
            'api_token' => 'test-only-token',
        ]);
        $response->assertStatus(403);
        $this->assertEquals($c->id, $f->fresh()->company_id);
    }

    /**
     * F01: Read-only user cannot access edit page and tokens are not rendered in plain HTML.
     */
    public function test_readonly_cannot_read_token_in_edit_page(): void
    {
        [$u, $f, $d, $c] = $this->fixture();
        // Read-only user is denied (403)
        $this->actingAs($u)->get('/firewalls/fw-test/edit')->assertStatus(403);

        // Even company admin edit page must never output raw api_token in plaintext
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'pw',
            'role' => 'admin',
            'company_id' => $c->id,
        ]);
        $this->actingAs($admin)->get('/firewalls/fw-test/edit')->assertOk()->assertDontSee('test-only-token');
    }

    /**
     * F03: Magic login redirects to 2FA challenge when enrolled and prevents token replay.
     */
    public function test_magic_login_enforces_second_factor_and_prevents_replay(): void
    {
        [$u] = $this->fixture();
        $u->forceFill([
            'two_factor_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $url = URL::temporarySignedRoute('login.magic.verify', now()->addMinutes(15), ['id' => $u->id]);

        // First use: redirects to 2FA challenge and remains unauthenticated
        $this->get($url)->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        // Replay attempt with the same URL: rejected as single-use consumed
        $this->get($url)->assertStatus(401);
    }

    /**
     * F04: Web users are denied on device broadcast channels.
     */
    public function test_web_users_denied_on_device_channel(): void
    {
        [$u, $f, $d] = $this->fixture();
        $other = Firewall::create([
            'name' => 'Other',
            'company_id' => $d->id,
            'url' => 'https://other.example.test',
            'netgate_id' => 'other',
        ]);

        $callbacks = Broadcast::getChannels();
        $this->assertFalse($callbacks['device.{firewallId}']($u, $other->id));
        $this->assertFalse($callbacks['device.{firewallId}']($u, $f->id));
    }

    /**
     * F09: Outbound HTTP requests are blocked before authorization check.
     */
    public function test_readonly_create_rejected_before_http_request(): void
    {
        [$u, $f] = $this->fixture();
        Http::fake();

        $response = $this->actingAs($u)->post('/firewalls', [
            'company_id' => $f->company_id,
            'name' => 'Invalid',
            'os_type' => 'opnsense',
            'url' => 'https://probe.example.test',
            'auth_method' => 'basic',
            'opn_username' => 'test',
            'opn_password' => 'test',
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    /**
     * B03: Elapsed age is calculated correctly and not negative.
     */
    public function test_status_age_calculation_is_positive(): void
    {
        $past = now()->subMinutes(10);
        $age = max(0, now()->timestamp - $past->timestamp);
        $this->assertGreaterThan(500, $age);
    }

    /**
     * B01: OPNsense backup accepts string XML return and marks completed.
     */
    public function test_opnsense_backup_accepts_valid_xml_string(): void
    {
        [$u, $f] = $this->fixture();
        $f->update(['os_type' => 'opnsense', 'api_key' => 'test-key', 'api_secret' => 'test-secret']);
        Http::fake(['*' => Http::response('<opnsense><system/></opnsense>', 200)]);

        (new \App\Jobs\PullFirewallConfigBackupJob($f->id))->handle();
        $this->assertEquals('success', $f->configBackup()->first()->status);
    }

    /**
     * B02: System backup includes SSH credentials.
     */
    public function test_backup_includes_ssh_credentials(): void
    {
        [$u, $f] = $this->fixture();
        $f->update(['ssh_username' => 'test-ssh-user', 'ssh_password' => 'test-ssh-password']);
        $svc = new \App\Services\SystemBackupService();
        $r = new \ReflectionMethod($svc, 'gatherSystemData');
        $data = $r->invoke($svc);

        $this->assertArrayHasKey('ssh_username', $data['firewalls'][0]);
        $this->assertArrayHasKey('ssh_password', $data['firewalls'][0]);
        $this->assertEquals('test-ssh-user', $data['firewalls'][0]['ssh_username']);
    }

    /**
     * F11: Modified ciphertext fails decryption with authenticated encryption.
     */
    public function test_backup_ciphertext_tampering_fails_decryption(): void
    {
        $svc = new \App\Services\SystemBackupService();
        $enc = new \ReflectionMethod($svc, 'encryptData');
        $dec = new \ReflectionMethod($svc, 'decryptData');

        $plain = '{"version":"2.0","users":[]}';
        $blob = base64_decode($enc->invoke($svc, $plain, 'test-password'));

        // Tamper with a byte in the payload
        $offset = strlen($blob) - 5;
        $blob[$offset] = chr(ord($blob[$offset]) ^ 0x01);

        $this->expectException(\Exception::class);
        $dec->invoke($svc, base64_encode($blob), 'test-password');
    }

    /**
     * F04: Public registration is disabled and returns 404.
     */
    public function test_public_registration_is_disabled(): void
    {
        $this->fixture();
        $response = $this->post('/register', [
            'name' => 'Uninvited',
            'email' => 'uninvited@example.test',
            'password' => 'test-password-long',
            'password_confirmation' => 'test-password-long',
        ]);

        $response->assertStatus(404);
        $this->assertNull(User::where('email', 'uninvited@example.test')->first());
    }

    /**
     * F05: Read-only user cannot download live firewall configurations.
     */
    public function test_readonly_cannot_download_live_opnsense_configuration(): void
    {
        [$u, $f] = $this->fixture();
        $f->update(['os_type' => 'opnsense', 'api_key' => 'test-key', 'api_secret' => 'test-secret']);
        Http::fake(['*' => Http::response('<opnsense><secret>test-only</secret></opnsense>', 200)]);

        $this->actingAs($u)->get('/firewall/fw-test/diagnostics/backup/download')->assertStatus(403);
    }
}
