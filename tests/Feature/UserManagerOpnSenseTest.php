<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Firewall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserManagerOpnSenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('x', 32))]);
        $this->withoutVite();
    }

    public function test_opnsense_user_and_group_manager_lifecycle()
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
            'netgate_id' => 'fw-user-test',
            'url' => 'https://192.168.240.11',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'os_type' => 'opnsense',
            'company_id' => $company->id,
            'is_online' => true,
        ]);

        Http::fake([
            '*api/auth/user/search*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'u-root-uuid',
                        'uid' => '0',
                        'name' => 'root',
                        'disabled' => '0',
                        'descr' => 'System Administrator',
                        '%group_memberships' => 'admins',
                    ],
                    [
                        'uuid' => 'u-test-uuid',
                        'uid' => '2001',
                        'name' => 'jdoe',
                        'disabled' => '0',
                        'descr' => 'John Doe',
                        '%group_memberships' => 'staff',
                    ],
                ],
            ], 200),
            '*api/auth/group/search*' => Http::response([
                'rows' => [
                    [
                        'uuid' => 'g-admins-uuid',
                        'gid' => '1999',
                        'name' => 'admins',
                        'description' => 'System Administrators',
                        '%member' => 'root',
                    ],
                    [
                        'uuid' => 'g-staff-uuid',
                        'gid' => '2001',
                        'name' => 'staff',
                        'description' => 'Staff Members',
                        '%member' => 'jdoe',
                    ],
                ],
            ], 200),
            '*api/auth/user/add*' => Http::response([
                'result' => 'saved',
                'uuid' => 'u-new-uuid',
            ], 200),
            '*api/auth/user/get/u-test-uuid*' => Http::response([
                'user' => [
                    'name' => 'jdoe',
                    'descr' => 'John Doe',
                    'disabled' => '0',
                ],
            ], 200),
            '*api/auth/user/set/u-test-uuid*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/auth/user/del/u-test-uuid*' => Http::response([
                'result' => 'deleted',
            ], 200),
            '*api/auth/group/add*' => Http::response([
                'result' => 'saved',
                'uuid' => 'g-new-uuid',
            ], 200),
            '*api/auth/group/get/g-staff-uuid*' => Http::response([
                'group' => [
                    'name' => 'staff',
                    'description' => 'Staff Members',
                ],
            ], 200),
            '*api/auth/group/set/g-staff-uuid*' => Http::response([
                'result' => 'saved',
            ], 200),
            '*api/auth/group/del/g-staff-uuid*' => Http::response([
                'result' => 'deleted',
            ], 200),
        ]);

        // 1. Users list
        $resUsers = $this->actingAs($admin)->get("/firewall/{$fw->id}/system/user-manager?tab=users");
        $resUsers->assertStatus(200);
        $resUsers->assertSee('root');
        $resUsers->assertSee('jdoe');
        $resUsers->assertSee('System Administrator');
        $resUsers->assertSee('John Doe');

        // 2. Groups list
        $resGroups = $this->actingAs($admin)->get("/firewall/{$fw->id}/system/user-manager?tab=groups");
        $resGroups->assertStatus(200);
        $resGroups->assertSee('admins');
        $resGroups->assertSee('staff');
        $resGroups->assertSee('System Administrators');
        $resGroups->assertSee('Staff Members');

        // 3. User Create Page
        $resCreateUser = $this->actingAs($admin)->get("/firewall/{$fw->id}/system/user-manager/users/create");
        $resCreateUser->assertStatus(200);
        $resCreateUser->assertSee('Username');

        // 4. Store User
        $resStoreUser = $this->actingAs($admin)->post("/firewall/{$fw->id}/system/user-manager/users", [
            'name' => 'newuser',
            'password' => 'Pass1234!',
            'descr' => 'New User Descr',
            'groups' => ['staff'],
        ]);
        $resStoreUser->assertRedirect("/firewall/{$fw->id}/system/user-manager?tab=users");

        // 5. User Edit Page
        $resEditUser = $this->actingAs($admin)->get("/firewall/{$fw->id}/system/user-manager/users/u-test-uuid/edit");
        $resEditUser->assertStatus(200);
        $resEditUser->assertSee('jdoe');

        // 6. Update User
        $resUpdateUser = $this->actingAs($admin)->patch("/firewall/{$fw->id}/system/user-manager/users/u-test-uuid", [
            'name' => 'jdoe',
            'descr' => 'John Doe Updated',
        ]);
        $resUpdateUser->assertRedirect("/firewall/{$fw->id}/system/user-manager?tab=users");

        // 7. Delete User
        $resDelUser = $this->actingAs($admin)->delete("/firewall/{$fw->id}/system/user-manager/users/u-test-uuid");
        $resDelUser->assertRedirect("/firewall/{$fw->id}/system/user-manager?tab=users");

        // 8. Group Create Page
        $resCreateGrp = $this->actingAs($admin)->get("/firewall/{$fw->id}/system/user-manager/groups/create");
        $resCreateGrp->assertStatus(200);
        $resCreateGrp->assertSee('Group Name');

        // 9. Store Group
        $resStoreGrp = $this->actingAs($admin)->post("/firewall/{$fw->id}/system/user-manager/groups", [
            'name' => 'devops',
            'description' => 'DevOps Team',
        ]);
        $resStoreGrp->assertRedirect("/firewall/{$fw->id}/system/user-manager?tab=groups");

        // 10. Group Edit Page
        $resEditGrp = $this->actingAs($admin)->get("/firewall/{$fw->id}/system/user-manager/groups/g-staff-uuid/edit");
        $resEditGrp->assertStatus(200);
        $resEditGrp->assertSee('staff');

        // 11. Update Group
        $resUpdateGrp = $this->actingAs($admin)->patch("/firewall/{$fw->id}/system/user-manager/groups/g-staff-uuid", [
            'description' => 'Updated Staff Members',
        ]);
        $resUpdateGrp->assertRedirect("/firewall/{$fw->id}/system/user-manager?tab=groups");

        // 12. Delete Group
        $resDelGrp = $this->actingAs($admin)->delete("/firewall/{$fw->id}/system/user-manager/groups/g-staff-uuid");
        $resDelGrp->assertRedirect("/firewall/{$fw->id}/system/user-manager?tab=groups");
    }
}
