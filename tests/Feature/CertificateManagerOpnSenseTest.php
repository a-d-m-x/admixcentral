<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CertificateManagerOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_certificate_manager_lifecycle()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'OPNsense Firewall',
            'netgate_id' => 'fw-cert-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/trust/ca/search*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'ca-uuid-1',
                        'refid' => 'caref-1',
                        'descr' => 'Test Internal CA',
                        'prv' => 'private-key-data',
                        'caref' => '',
                        'refcount' => 1,
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/trust/ca/add*' => Http::response([
                'result' => 'saved',
                'uuid' => 'ca-uuid-new',
            ], 200),
            '*api/trust/ca/del/ca-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),

            '*api/trust/cert/search*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'cert-uuid-1',
                        'refid' => 'certref-1',
                        'descr' => 'Test Web Certificate',
                        'caref' => 'caref-1',
                        'prv' => 'cert-key-data',
                        'cert_type' => 'server_cert',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/trust/cert/add*' => Http::response([
                'result' => 'saved',
                'uuid' => 'cert-uuid-new',
            ], 200),
            '*api/trust/cert/del/cert-uuid-1*' => Http::response([
                'result' => 'deleted',
            ], 200),

            '*api/trust/crl/search*' => Http::response([
                'rows' => [
                    [
                        'refid' => 'caref-1',
                        'descr' => 'Test Internal CA',
                        'crl_descr' => 'Revocation List 1',
                    ],
                ],
                'total' => 1,
            ], 200),
            '*api/trust/crl/set/caref-1*' => Http::response([
                'status' => 'saved',
            ], 200),
            '*api/trust/crl/del/caref-1*' => Http::response([
                'status' => 'deleted',
            ], 200),
        ]);

        // 1. List CAs
        $resCaList = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=cas");
        $resCaList->assertStatus(200);
        $resCaList->assertSee('Test Internal CA');

        // 2. Generate Internal CA
        $resCaStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/certificate-manager/cas", [
            'method' => 'internal',
            'descr' => 'New Central CA',
            'keylen' => 2048,
            'digest_alg' => 'sha256',
            'lifetime' => 3650,
            'dn_country' => 'US',
            'dn_state' => 'CA',
            'dn_city' => 'San Francisco',
            'dn_organization' => 'Acme Corp',
            'dn_commonname' => 'Acme Root CA',
        ]);
        $resCaStore->assertRedirect("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=cas");
        $resCaStore->assertSessionHas('success');

        // 3. Delete CA (by refid resolving to uuid)
        $resCaDel = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/system/certificate-manager/cas/caref-1");
        $resCaDel->assertRedirect("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=cas");
        $resCaDel->assertSessionHas('success');

        // 4. List Certificates
        $resCertList = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=certificates");
        $resCertList->assertStatus(200);
        $resCertList->assertSee('Test Web Certificate');

        // 5. Generate Internal Certificate
        $resCertStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/certificate-manager/certificates", [
            'method' => 'internal',
            'descr' => 'New Web Cert',
            'caref' => 'caref-1',
            'keylen' => 2048,
            'digest_alg' => 'sha256',
            'lifetime' => 397,
            'type' => 'server',
            'dn_country' => 'US',
            'dn_commonname' => 'web.acme.local',
        ]);
        $resCertStore->assertRedirect("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=certificates");
        $resCertStore->assertSessionHas('success');

        // 6. Delete Certificate (by refid resolving to uuid)
        $resCertDel = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/system/certificate-manager/certificates/certref-1");
        $resCertDel->assertRedirect("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=certificates");
        $resCertDel->assertSessionHas('success');

        // 7. List CRLs
        $resCrlList = $this->actingAs($admin)->get("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=crls");
        $resCrlList->assertStatus(200);
        $resCrlList->assertSee('Revocation List 1');

        // 8. Create CRL
        $resCrlStore = $this->actingAs($admin)->post("/firewall/{$fw->netgate_id}/system/certificate-manager/crls", [
            'method' => 'internal',
            'descr' => 'New CRL',
            'caref' => 'caref-1',
        ]);
        $resCrlStore->assertRedirect("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=crls");
        $resCrlStore->assertSessionHas('success');

        // 9. Delete CRL
        $resCrlDel = $this->actingAs($admin)->delete("/firewall/{$fw->netgate_id}/system/certificate-manager/crls/caref-1");
        $resCrlDel->assertRedirect("/firewall/{$fw->netgate_id}/system/certificate-manager?tab=crls");
        $resCrlDel->assertSessionHas('success');
    }
}
