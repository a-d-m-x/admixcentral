<?php

namespace Tests\Feature;

use App\Jobs\PullFirewallConfigBackupJob;
use App\Models\{Company, Firewall, User};
use App\Services\{FirewallHttpOptions, PfSenseApiService, OpnSenseApiService, SshHostKeyVerifier};
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Http, Queue};
use phpseclib3\Net\SFTP;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecurityAuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function fixture(string $role = 'user'): array
    {
        $company = Company::create(['name' => 'Security test tenant']);
        $user = User::create(['name' => 'Test user', 'email' => 'security@example.test',
            'password' => 'test-password', 'role' => $role, 'company_id' => $company->id]);
        $firewall = Firewall::create(['name' => 'Test firewall', 'company_id' => $company->id,
            'url' => 'https://fw.example.test', 'netgate_id' => 'security-fw', 'os_type' => 'pfsense',
            'auth_method' => 'token', 'api_token' => 'test-token']);

        return [$user, $firewall];
    }

    public function test_ipsec_shell_and_php_layers_preserve_untrusted_arguments(): void
    {
        [$user] = $this->fixture();
        $commands = [];
        Http::fake(function ($request) use (&$commands) {
            $commands[] = $request['command'];
            return Http::response(['data' => []]);
        });
        // Harmless markers: a vulnerable shell changes these strings before PHP runs.
        $payload = '$(printf EXPANDED)' . chr(96) . 'printf EXPANDED' . chr(96) . '"\'\\';
        $cases = [
            ['connect', ['type' => 'p1', 'conid' => $payload], ['all', $payload]],
            ['connect', ['type' => 'p2', 'name' => $payload], ['child', $payload]],
            ['disconnect', ['type' => 'p1', 'conid' => $payload, 'uniqueid' => $payload], ['ike', $payload, $payload]],
            ['disconnect', ['type' => 'p2', 'name' => $payload, 'uniqueid' => $payload], ['child', $payload, $payload]],
        ];

        $directory = sys_get_temp_dir() . '/admix-ipsec-test-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        // Only stub functions execute locally. No firewall or system state is changed.
        file_put_contents($directory . '/ipsec.inc', '<?php
            function ipsec_initiate_by_conid(...$args) { echo json_encode($args); }
            function ipsec_terminate_by_conid(...$args) { echo json_encode($args); }');
        try {
            foreach ($cases as [$action, $input, $expected]) {
                $this->actingAs($user)->post('/firewall/security-fw/status/ipsec/' . $action, $input)
                    ->assertSessionHas('success');
                $command = array_pop($commands);
                $process = Process::fromShellCommandline($command, $directory);
                $process->mustRun();
                $this->assertSame($expected, json_decode($process->getOutput(), true));
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_sad_rejects_shell_and_setkey_injection_before_outbound_request(): void
    {
        [$user] = $this->fixture();
        foreach ([
            ['src' => "'; printf PROBE; #"],
            ['proto' => "esp\nflush"],
            ['spi' => 'abc;flush'],
        ] as $attack) {
            $this->actingAs($user)->postJson('/firewall/security-fw/status/ipsec/sad/delete',
                $attack + ['src' => '192.0.2.1', 'dst' => '198.51.100.1', 'proto' => 'esp', 'spi' => 'abc'])
                ->assertUnprocessable();
        }
        Http::assertNothingSent();
    }

    public function test_readonly_cannot_modify_limiters_or_virtual_ips(): void
    {
        [$user] = $this->fixture('readonly');
        foreach (['limiters', 'virtual_ips'] as $resource) {
            $base = '/firewall/security-fw/firewall/' . $resource;
            $this->actingAs($user)->postJson($base, [])->assertForbidden();
            $this->actingAs($user)->patchJson($base . '/1', [])->assertForbidden();
            $this->actingAs($user)->deleteJson($base . '/1')->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public static function restrictedUrls(): array
    {
        return array_map(fn ($url) => [$url], [
            'https://127.0.0.1', 'https://169.254.169.254', 'https://[::1]',
            'https://[0:0:0:0:0:0:0:1]', 'https://[::ffff:127.0.0.1]',
            'https://[::ffff:169.254.169.254]', 'https://[fe90::1]', 'https://[::]',
            'https://localhost.', 'https://metadata.google.internal',
            'https://user:password@192.0.2.1', 'https://192.0.2.1?target=other', 'http://192.0.2.1',
        ]);
    }

    #[DataProvider('restrictedUrls')]
    public function test_restricted_firewall_endpoints_are_rejected_before_http(string $url): void
    {
        [$user, $firewall] = $this->fixture('admin');
        $this->actingAs($user)->postJson('/firewalls', [
            'company_id' => $firewall->company_id, 'name' => 'Probe',
            'url' => $url, 'auth_method' => 'token', 'api_token' => 'test-token',
        ])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_changing_ssh_destination_clears_stored_password(): void
    {
        [$user, $firewall] = $this->fixture('admin');
        foreach ([
            ['url' => 'https://203.0.113.20'],
            ['ssh_port' => 2222],
            ['ssh_username' => 'different-account'],
            ['ssh_host_key_fingerprint' => 'SHA256:' . str_repeat('B', 43)],
        ] as $change) {
            $firewall->update(['url' => 'https://192.0.2.1', 'ssh_port' => 22,
                'ssh_username' => 'root', 'ssh_password' => 'previous-secret',
                'ssh_host_key_fingerprint' => 'SHA256:' . str_repeat('A', 43)]);
            $this->actingAs($user)->put('/firewalls/security-fw', $change + [
                'company_id' => $firewall->company_id, 'name' => 'Updated',
                'url' => 'https://192.0.2.1', 'auth_method' => 'token', 'api_token' => 'new-token',
            ])->assertSessionHas('success');
            $this->assertNull($firewall->fresh()->ssh_password);
        }
        Http::assertNothingSent();
    }

    public function test_api_clients_verify_tls_and_do_not_follow_redirects(): void
    {
        [, $firewall] = $this->fixture();
        $optionsSeen = [];
        Http::fake(function ($request, $options) use (&$optionsSeen) {
            $optionsSeen[] = $options;
            return Http::response(['data' => []]);
        });
        (new PfSenseApiService($firewall))->get('/status/system');
        $firewall->update(['os_type' => 'opnsense', 'api_key' => 'key', 'api_secret' => 'secret']);
        (new OpnSenseApiService($firewall))->get('/api/core/system/status');
        foreach ($optionsSeen as $options) {
            $this->assertTrue($options['verify']);
            $this->assertFalse($options['allow_redirects']);
        }
        $this->assertCount(2, $optionsSeen);
    }

    public function test_ssh_host_key_must_match_a_trusted_fingerprint(): void
    {
        $raw = 'test-public-host-key';
        $fingerprint = 'SHA256:' . rtrim(base64_encode(hash('sha256', $raw, true)), '=');
        $sftp = \Mockery::mock(SFTP::class);
        $sftp->shouldReceive('getServerPublicHostKey')->twice()->andReturn('ssh-ed25519 ' . base64_encode($raw));
        $sftp->shouldNotReceive('login');
        $this->assertTrue(SshHostKeyVerifier::verify($sftp, $fingerprint));
        $this->assertFalse(SshHostKeyVerifier::verify($sftp, 'SHA256:' . str_repeat('A', 43)));
        $this->assertFalse(SshHostKeyVerifier::verify($sftp, ''));
    }

    public function test_backup_with_missing_ssh_pin_fails_before_authentication(): void
    {
        [, $firewall] = $this->fixture();
        $firewall->update(['ssh_username' => 'root', 'ssh_password' => 'secret']);
        (new PullFirewallConfigBackupJob($firewall->id))->handle();
        $backup = $firewall->configBackup()->first();
        $this->assertSame('failed', $backup->status);
        $this->assertStringContainsString('fingerprint', $backup->error_message);
    }

    public function test_tunable_description_is_encoded_as_javascript_data(): void
    {
        [$user] = $this->fixture();
        $payload = "', probe: (globalThis.ADMX_XSS_PROBE=1), tail: '";
        Http::fake(['*' => Http::response(['data' => [[
            'id' => 1, 'tunable' => 'net.example', 'value' => '1', 'descr' => $payload,
        ]]])]);
        $html = $this->actingAs($user)->get('/firewall/security-fw/system/advanced?tab=tunables')
            ->assertOk()->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $expression = null;
        foreach ($dom->getElementsByTagName('button') as $button) {
            $candidate = $button->getAttribute('@click');
            if (str_contains($candidate, 'ADMX_XSS_PROBE')) $expression = $candidate;
        }
        $this->assertNotNull($expression);
        $this->assertStringContainsString('\u0027', $expression);
        $this->assertStringNotContainsString($payload, $expression);
    }

    public function test_cross_tenant_firewall_routes_are_blocked_before_api_calls(): void
    {
        [$user] = $this->fixture();
        $other = Company::create(['name' => 'Other tenant']);
        $user->update(['company_id' => $other->id]);
        $this->actingAs($user)->get('/firewall/security-fw/status/ipsec')->assertForbidden();
        $this->actingAs($user)->postJson('/firewall/security-fw/status/ipsec/connect',
            ['type' => 'p1', 'conid' => 'con1'])->assertForbidden();
        $this->actingAs($user)->postJson('/firewall/security-fw/firewall/virtual_ips', [])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_readonly_cannot_read_smtp_or_ha_passwords_from_html(): void
    {
        [$user, $firewall] = $this->fixture('readonly');
        Http::fake(['*' => Http::response(['data' => ['password' => 'smtp-private-probe']])]);
        $this->actingAs($user)->get('/firewall/security-fw/system/advanced?tab=notifications')
            ->assertOk()->assertDontSee('smtp-private-probe');

        $firewall->update(['os_type' => 'opnsense', 'api_key' => 'key', 'api_secret' => 'secret']);
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake([
            '*api/core/hasync/get*' => Http::response(['hasync' => ['password' => 'ha-private-probe']]),
            '*' => Http::response(['data' => [], 'rows' => [], 'statistics' => []]),
        ]);
        $this->actingAs($user)->get('/firewall/security-fw/system/high-avail-sync')
            ->assertOk()->assertDontSee('ha-private-probe');
    }

    public function test_magic_login_email_uses_configured_origin_despite_host_header(): void
    {
        [$user] = $this->fixture();
        \Illuminate\Support\Facades\Mail::fake();
        $this->post('http://attacker.example.test/login/magic', ['email' => $user->email])->assertRedirect();
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MagicLoginLink::class,
            fn ($mail) => str_starts_with($mail->url, rtrim(config('app.url'), '/') . '/login/magic/'));
    }

    public function test_clearing_custom_ssh_port_does_not_reuse_password_on_default_port(): void
    {
        [$user, $firewall] = $this->fixture('admin');
        $firewall->update(['url' => 'https://192.0.2.1', 'ssh_port' => 2222,
            'ssh_username' => 'root', 'ssh_password' => 'private-password']);
        $this->actingAs($user)->put('/firewalls/security-fw', [
            'company_id' => $firewall->company_id, 'name' => 'Updated', 'ssh_port' => null,
            'url' => 'https://192.0.2.1', 'auth_method' => 'token',
        ])->assertSessionHas('success');
        $this->assertNull($firewall->fresh()->ssh_password);
    }

    private function publicCertificate(): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'native-firewall.local'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);
        openssl_x509_export($cert, $pem);

        return $pem;
    }

    public function test_native_certificate_enrollment_requires_credentials_when_trust_changes(): void
    {
        [$user, $firewall] = $this->fixture('admin');
        $firewall->update(['url' => 'https://192.0.2.1']);
        $pem = $this->publicCertificate();
        $input = ['company_id' => $firewall->company_id, 'name' => 'Updated',
            'url' => $firewall->url, 'auth_method' => 'token'];

        $this->actingAs($user)->put('/firewalls/security-fw', $input + [
            'tls_certificate' => UploadedFile::fake()->createWithContent('firewall.crt', $pem),
        ])->assertSessionHasErrors('api_token');
        $this->assertNull($firewall->fresh()->tls_public_key_pin);

        $this->put('/firewalls/security-fw', $input + ['api_token' => 'new-token',
            'tls_certificate' => UploadedFile::fake()->createWithContent('firewall.crt', $pem),
        ])->assertSessionHas('success');
        $this->assertSame(FirewallHttpOptions::pinFromCertificate($pem), $firewall->fresh()->tls_public_key_pin);
        $this->assertSame('new-token', $firewall->fresh()->api_token);
        Http::assertNothingSent();
    }

    public function test_native_certificate_is_used_for_initial_firewall_connection(): void
    {
        [$user, $firewall] = $this->fixture('admin');
        $pem = $this->publicCertificate();
        $pin = FirewallHttpOptions::pinFromCertificate($pem);
        Http::fake(function ($request, $options) use ($pin) {
            $this->assertSame($pin, $options['curl'][CURLOPT_PINNEDPUBLICKEY]);
            $this->assertFalse($options['allow_redirects']);
            return Http::response(['data' => ['netgate_id' => 'native-cert-fw']]);
        });
        $this->actingAs($user)->post('/firewalls', [
            'company_id' => $firewall->company_id, 'name' => 'Native cert firewall',
            'url' => 'https://192.0.2.1', 'auth_method' => 'token', 'api_token' => 'test-token',
            'tls_certificate' => UploadedFile::fake()->createWithContent('firewall.crt', $pem),
        ])->assertSessionHas('success');
        $this->assertSame($pin, Firewall::where('netgate_id', 'native-cert-fw')->firstOrFail()->tls_public_key_pin);
    }

    public function test_invalid_certificate_or_pin_is_rejected_without_network_calls(): void
    {
        [$user, $firewall] = $this->fixture('admin');
        $base = ['company_id' => $firewall->company_id, 'name' => 'Invalid cert',
            'url' => 'https://192.0.2.1', 'auth_method' => 'token', 'api_token' => 'test-token'];
        foreach (['not a certificate', '-----BEGIN PRIVATE KEY-----'] as $content) {
            $this->actingAs($user)->post('/firewalls', $base + [
                'tls_certificate' => UploadedFile::fake()->createWithContent('firewall.pem', $content),
            ])->assertSessionHasErrors('tls_certificate');
        }
        $this->post('/firewalls', $base + ['tls_public_key_pin' => '/etc/passwd'])
            ->assertSessionHasErrors('tls_public_key_pin');
        Http::assertNothingSent();
    }

    public function test_both_clients_and_opnsense_pools_enforce_the_enrolled_key(): void
    {
        [, $firewall] = $this->fixture();
        $pin = 'sha256//' . base64_encode(hash('sha256', 'test-key', true));
        $firewall->update(['tls_public_key_pin' => $pin]);
        $count = 0;
        Http::fake(function ($request, $options) use ($pin, &$count) {
            $count++;
            $this->assertSame($pin, $options['curl'][CURLOPT_PINNEDPUBLICKEY]);
            $this->assertFalse($options['verify']);
            $this->assertFalse($options['allow_redirects']);
            return Http::response(['data' => [], 'rows' => [], 'statistics' => []]);
        });
        (new PfSenseApiService($firewall))->get('/status/system');
        $firewall->update(['os_type' => 'opnsense', 'api_key' => 'key', 'api_secret' => 'secret']);
        $api = new OpnSenseApiService($firewall);
        $api->get('/api/core/system/status');
        $api->getInterfacesStatus();
        $this->assertSame(4, $count);
    }

    public function test_opnsense_pool_connection_failure_is_a_catchable_exception(): void
    {
        [, $firewall] = $this->fixture();
        $firewall->update(['os_type' => 'opnsense', 'api_key' => 'key', 'api_secret' => 'secret']);
        Http::fake(['*' => Http::failedConnection()]);
        $this->expectException(\RuntimeException::class);
        (new OpnSenseApiService($firewall))->getInterfacesStatus();
    }

    public function test_firewall_errors_do_not_flash_credentials_into_the_session(): void
    {
        [$user, $firewall] = $this->fixture('admin');
        $input = ['company_id' => $firewall->company_id, 'name' => 'New firewall',
            'url' => 'https://192.0.2.1', 'auth_method' => 'token', 'api_token' => 'private-api-probe',
            'api_secret' => 'private-secret-probe', 'ssh_password' => 'private-ssh-probe'];
        $this->actingAs($user)->post('/firewalls', $input + ['tls_public_key_pin' => 'invalid'])
            ->assertSessionHasErrors('tls_public_key_pin')
            ->assertSessionMissing('_old_input.api_token')
            ->assertSessionMissing('_old_input.api_secret')
            ->assertSessionMissing('_old_input.ssh_password');

        Http::fake(['*' => Http::failedConnection()]);
        $this->post('/firewalls', $input)->assertSessionHas('error')
            ->assertSessionMissing('_old_input.api_token')
            ->assertSessionMissing('_old_input.api_secret')
            ->assertSessionMissing('_old_input.ssh_password');
    }

    public function test_native_certificate_enrollment_is_available_on_both_forms(): void
    {
        [$user] = $this->fixture('admin');
        foreach (['/firewalls/create', '/firewalls/security-fw/edit'] as $path) {
            $this->actingAs($user)->get($path)->assertOk()
                ->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('name="tls_certificate"', false)
                ->assertSee('name="ssh_host_key_fingerprint"', false);
        }
        Http::assertNothingSent();
    }

    public function test_ssl_configuration_redirects_plaintext_to_the_canonical_host(): void
    {
        $service = app(\App\Services\SslManagerService::class);
        $stub = (new \ReflectionMethod($service, 'getNginxSslStub'))->invoke($service, 'central.example.test');
        [$http, $https] = explode('listen 443', $stub, 2);
        $this->assertStringContainsString('return 301 https://central.example.test$request_uri;', $http);
        $this->assertStringContainsString('/.well-known/acme-challenge/', $http);
        $this->assertStringNotContainsString('fastcgi_pass', $http);
        $this->assertStringContainsString('ssl_certificate', $https);
    }
}
