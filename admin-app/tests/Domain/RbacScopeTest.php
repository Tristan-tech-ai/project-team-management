<?php

namespace Tests\Domain;

use App\Services\Scope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RbacScopeTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_role_permissions_match_source_sql(): void
    {
        $n = fn ($r) => DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->where('roles.slug', $r)->count();
        $this->assertSame(39, DB::table('permissions')->count());
        $this->assertSame([39, 27, 11], [$n('super_admin'), $n('manager'), $n('member')]);
    }

    public function test_gate_checks_permissions_on_the_server(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $this->assertTrue(Gate::forUser($admin)->allows('settings.roles.manage'));
        $this->assertTrue(Gate::forUser($manager)->allows('projects.create'));
        $this->assertFalse(Gate::forUser($manager)->allows('settings.menus.manage'));
        $this->assertFalse(Gate::forUser($manager)->allows('companies.manage'));
        $this->assertTrue(Gate::forUser($member)->allows('tasks.submit'));
        $this->assertFalse(Gate::forUser($member)->allows('companies.view'));
        $this->assertFalse(Gate::forUser($member)->allows('management.view'));
        $this->assertFalse(Gate::forUser($member)->allows('tasks.review'));
    }

    public function test_member_cannot_see_foreign_projects_and_manager_sees_own_company(): void
    {
        $c = $this->company();
        $other = $this->company('Company Lain');
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $otherPerson = $this->user('manager', $other);
        $ownedByMember = $this->project($manager, $manager, $c, [$member]);
        $withoutMember = $this->project($manager, $manager, $c);
        $foreign = $this->project($otherPerson, $otherPerson, $other);

        $memberView = Scope::projects($member)->pluck('id')->all();
        $this->assertContains($ownedByMember->id, $memberView);
        $this->assertNotContains($withoutMember->id, $memberView);
        $this->assertNotContains($foreign->id, $memberView);
        $this->assertFalse($member->can('view', $foreign));

        $managerView = Scope::projects($manager)->pluck('id')->all();
        $this->assertContains($withoutMember->id, $managerView);
        $this->assertNotContains($foreign->id, $managerView);
        $this->assertFalse($manager->can('update', $foreign));
    }

    public function test_member_cannot_approve_deadline_without_explicit_permission(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $member, $c, [$manager]);
        // A Member who becomes the project PIC still does not get projects.approve_schedule.
        $this->assertFalse($member->can('approveSchedule', $p));
        $this->assertTrue($manager->can('approveSchedule', $p));
    }
}
