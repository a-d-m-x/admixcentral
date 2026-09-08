<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use App\Services\OpnSenseApiService;
use App\Services\PfSenseApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirmwarePackageManagerOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_firmware_and_package_lifecycle()
    {
        $company = Company::create(['name' => 'Acme Corp']);
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@central.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $fw = Firewall::create([
            'name' => 'OPNsense Lab FW',
            'netgate_id' => 'fw-firmware-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/core/firmware/status*' => Http::response([
                'status' => 'update',
                'status_msg' => 'There is 1 update available.',
                'product' => [
                    'product_series' => '26.7',
                    'product_nickname' => 'Xenial Xenops',
                    'product_version' => '26.7.3_11',
                    'product_mirror' => 'https://pkg.opnsense.org',
                ],
                'upgrade_packages' => [
                    [
                        'name' => 'openvpn',
                        'repository' => 'OPNsense',
                        'current_version' => '2.7.6',
                        'new_version' => '2.7.7',
                    ],
                ],
            ], 200),
            '*api/core/firmware/info*' => Http::response([
                'product' => [
                    'name' => 'OPNsense',
                    'version' => '26.7.3_11',
                ],
                'package' => [
                    [
                        'name' => 'nano',
                        'version' => '8.3',
                        'comment' => 'Nano text editor',
                        'installed' => '1',
                        'locked' => '0',
                    ],
                    [
                        'name' => 'htop',
                        'version' => '3.3.0',
                        'comment' => 'Interactive process viewer',
                        'installed' => '0',
                        'locked' => '0',
                    ],
                ],
                'plugin' => [
                    [
                        'name' => 'os-caddy',
                        'version' => '1.7.4',
                        'comment' => 'Caddy web server',
                        'installed' => '0',
                        'locked' => '0',
                    ],
                ],
            ], 200),
            '*api/core/firmware/upgradestatus*' => Http::response([
                'status' => 'done',
                'log' => "***SECURITY AUDIT COMPLETE***\n0 vulnerabilities found.",
            ], 200),
            '*api/core/firmware/check*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-check-123',
            ], 200),
            '*api/core/firmware/audit*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-audit-123',
            ], 200),
            '*api/core/firmware/update*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-update-123',
            ], 200),
            '*api/core/firmware/install/*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-install-123',
            ], 200),
            '*api/core/firmware/remove/*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-remove-123',
            ], 200),
            '*api/core/firmware/reinstall/*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-reinstall-123',
            ], 200),
            '*api/core/firmware/lock/*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-lock-123',
            ], 200),
            '*api/core/firmware/unlock/*' => Http::response([
                'status' => 'ok',
                'msg_uuid' => 'uuid-unlock-123',
            ], 200),
            '*api/core/system/status*' => Http::response([
                'system' => ['status' => 'ok'],
            ], 200),
            '*api/diagnostics/system/systemInformation*' => Http::response([
                'versions' => ['OPNsense 26.7.3_11-amd64', 'FreeBSD 15.1-RELEASE-p3'],
            ], 200),
        ]);

        // 1. Direct OpnSenseApiService tests
        $opnApi = new OpnSenseApiService($fw);
        $status = $opnApi->getFirmwareStatus();
        $this->assertEquals('update', $status['status']);
        $this->assertEquals('openvpn', $status['upgrade_packages'][0]['name']);

        $info = $opnApi->getFirmwareInfo();
        $this->assertCount(2, $info['package']);
        $this->assertCount(1, $info['plugin']);

        $upgradeStatus = $opnApi->getFirmwareUpgradeStatus();
        $this->assertEquals('done', $upgradeStatus['status']);
        $this->assertStringContainsString('SECURITY AUDIT COMPLETE', $upgradeStatus['log']);

        $checkRes = $opnApi->checkFirmwareUpdates();
        $this->assertEquals('ok', $checkRes['status']);

        $auditRes = $opnApi->auditFirmware();
        $this->assertEquals('ok', $auditRes['status']);

        $updateRes = $opnApi->upgradeFirmware();
        $this->assertEquals('ok', $updateRes['status']);

        $installRes = $opnApi->installPackage('os-caddy');
        $this->assertEquals('ok', $installRes['status']);

        $removeRes = $opnApi->removePackage('nano');
        $this->assertEquals('ok', $removeRes['status']);

        $reinstallRes = $opnApi->reinstallPackage('nano');
        $this->assertEquals('ok', $reinstallRes['status']);

        $lockRes = $opnApi->lockPackage('nano');
        $this->assertEquals('ok', $lockRes['status']);

        $unlockRes = $opnApi->unlockPackage('nano');
        $this->assertEquals('ok', $unlockRes['status']);

        // 2. PfSenseApiService compatibility translation tests
        $pfApi = new PfSenseApiService($fw);
        $pfStatus = $pfApi->getFirmwareStatus();
        $this->assertEquals('update', $pfStatus['status']);

        $pfUpgradeStatus = $pfApi->getFirmwareUpgradeStatus();
        $this->assertEquals('done', $pfUpgradeStatus['status']);

        $pfAudit = $pfApi->auditFirmware();
        $this->assertEquals('ok', $pfAudit['status']);

        $pfUpgrade = $pfApi->upgradeFirmware();
        $this->assertEquals('ok', $pfUpgrade['status']);

        $installedPkgs = $pfApi->getSystemPackages();
        $this->assertEquals(200, $installedPkgs['status']);
        $this->assertCount(1, $installedPkgs['data']);
        $this->assertEquals('nano', $installedPkgs['data'][0]['name']);

        $availPkgs = $pfApi->getSystemAvailablePackages();
        $this->assertEquals(200, $availPkgs['status']);
        $this->assertCount(2, $availPkgs['data']); // htop + os-caddy

        $installSysPkg = $pfApi->installSystemPackage('os-caddy');
        $this->assertEquals('ok', $installSysPkg['status']);

        $uninstallSysPkg = $pfApi->uninstallSystemPackage(0, 'nano');
        $this->assertEquals('ok', $uninstallSysPkg['status']);

        $reinstallSysPkg = $pfApi->reinstallSystemPackage('nano');
        $this->assertEquals('ok', $reinstallSysPkg['status']);

        $lockSysPkg = $pfApi->lockSystemPackage('nano');
        $this->assertEquals('ok', $lockSysPkg['status']);

        $unlockSysPkg = $pfApi->unlockSystemPackage('nano');
        $this->assertEquals('ok', $unlockSysPkg['status']);

        // 3. Web UI Controllers & Routes tests
        $this->actingAs($admin);

        // System Update view
        $updateResp = $this->get(route('system.update', $fw));
        $updateResp->assertStatus(200);
        $updateResp->assertSee('OPNsense Firmware Management');
        $updateResp->assertSee('There is 1 update available.');
        $updateResp->assertSee('openvpn');
        $updateResp->assertSee('2.7.6');
        $updateResp->assertSee('2.7.7');
        $updateResp->assertSee('Security Audit');
        $updateResp->assertSee('Upgrade Firmware');
        $updateResp->assertSee('Console Output');

        // Check updates action
        $checkResp = $this->post(route('system.update.check', $fw));
        $checkResp->assertRedirect();
        $checkResp->assertSessionHas('success');

        // Audit firmware action
        $auditResp = $this->post(route('system.update.audit', $fw));
        $auditResp->assertRedirect();
        $auditResp->assertSessionHas('success');

        // Upgrade firmware action
        $upgradeResp = $this->post(route('system.update.upgrade', $fw));
        $upgradeResp->assertRedirect();
        $upgradeResp->assertSessionHas('success');

        // Status log JSON endpoint
        $statusLogResp = $this->get(route('system.update.status-log', $fw));
        $statusLogResp->assertStatus(200);
        $statusLogResp->assertJsonFragment(['status' => 'done']);

        // Package Manager Views & Actions
        $pkgIndexInstalled = $this->get(route('system.package_manager.index', ['firewall' => $fw, 'tab' => 'installed']));
        $pkgIndexInstalled->assertStatus(200);
        $pkgIndexInstalled->assertSee('nano');
        $pkgIndexInstalled->assertSee('Uninstall');
        $pkgIndexInstalled->assertSee('Reinstall');
        $pkgIndexInstalled->assertSee('Lock');

        $pkgIndexAvail = $this->get(route('system.package_manager.index', ['firewall' => $fw, 'tab' => 'available']));
        $pkgIndexAvail->assertStatus(200);
        $pkgIndexAvail->assertSee('htop');
        $pkgIndexAvail->assertSee('os-caddy');
        $pkgIndexAvail->assertSee('Install');

        // Package actions
        $pkgInstallResp = $this->post(route('system.package_manager.install', $fw), ['name' => 'os-caddy']);
        $pkgInstallResp->assertRedirect();
        $pkgInstallResp->assertSessionHas('success');

        $pkgUninstallResp = $this->post(route('system.package_manager.uninstall', $fw), ['id' => 0, 'name' => 'nano']);
        $pkgUninstallResp->assertRedirect();
        $pkgUninstallResp->assertSessionHas('success');

        $pkgReinstallResp = $this->post(route('system.package_manager.reinstall', $fw), ['name' => 'nano']);
        $pkgReinstallResp->assertRedirect();
        $pkgReinstallResp->assertSessionHas('success');

        $pkgLockResp = $this->post(route('system.package_manager.lock', $fw), ['name' => 'nano']);
        $pkgLockResp->assertRedirect();
        $pkgLockResp->assertSessionHas('success');

        $pkgUnlockResp = $this->post(route('system.package_manager.unlock', $fw), ['name' => 'nano']);
        $pkgUnlockResp->assertRedirect();
        $pkgUnlockResp->assertSessionHas('success');
    }
}
