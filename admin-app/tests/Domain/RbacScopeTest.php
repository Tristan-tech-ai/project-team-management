<?php

namespace Tests\Domain;

use App\Services\Scope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacScopeTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_izin_role_sesuai_sql_sumber(): void
    {
        $n = fn ($r) => DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->where('roles.slug', $r)->count();
        $this->assertSame(39, DB::table('permissions')->count());
        $this->assertSame([39, 27, 11], [$n('super_admin'), $n('manager'), $n('member')]);
    }

    public function test_gate_memeriksa_permission_di_server(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        $manager = $this->pengguna('manager', $c);
        $member = $this->pengguna('member', $c);
        $this->assertTrue(Gate::forUser($admin)->allows('settings.roles.manage'));
        $this->assertTrue(Gate::forUser($manager)->allows('projects.create'));
        $this->assertFalse(Gate::forUser($manager)->allows('settings.menus.manage'));
        $this->assertFalse(Gate::forUser($manager)->allows('companies.manage'));
        $this->assertTrue(Gate::forUser($member)->allows('tasks.submit'));
        $this->assertFalse(Gate::forUser($member)->allows('companies.view'));
        $this->assertFalse(Gate::forUser($member)->allows('management.view'));
        $this->assertFalse(Gate::forUser($member)->allows('tasks.review'));
    }

    public function test_member_tidak_melihat_project_asing_dan_manager_melihat_company_sendiri(): void
    {
        $c = $this->company();
        $lain = $this->company('Company Lain');
        $manager = $this->pengguna('manager', $c);
        $member = $this->pengguna('member', $c);
        $orangLain = $this->pengguna('manager', $lain);
        $milikMember = $this->project($manager, $manager, $c, [$member]);
        $tanpaMember = $this->project($manager, $manager, $c);
        $asing = $this->project($orangLain, $orangLain, $lain);

        $lihatMember = Scope::projects($member)->pluck('id')->all();
        $this->assertContains($milikMember->id, $lihatMember);
        $this->assertNotContains($tanpaMember->id, $lihatMember);
        $this->assertNotContains($asing->id, $lihatMember);
        $this->assertFalse($member->can('view', $asing));

        $lihatManager = Scope::projects($manager)->pluck('id')->all();
        $this->assertContains($tanpaMember->id, $lihatManager);
        $this->assertNotContains($asing->id, $lihatManager);
        $this->assertFalse($manager->can('update', $asing));
    }

    public function test_member_tidak_bisa_menyetujui_deadline_tanpa_izin_eksplisit(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $member = $this->pengguna('member', $c);
        $p = $this->project($manager, $member, $c, [$manager]);
        // Member menjadi PIC project pun tetap tidak punya projects.approve_schedule.
        $this->assertFalse($member->can('approveSchedule', $p));
        $this->assertTrue($manager->can('approveSchedule', $p));
    }
}
