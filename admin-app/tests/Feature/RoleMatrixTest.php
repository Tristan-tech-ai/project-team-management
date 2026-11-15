<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** U7: permissions as a tick table with plain names; every permission appears exactly once; no permission codes on screen. */
class RoleMatrixTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_every_permission_appears_once_without_codes(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $html = $this->actingAs($admin)->get('/settings/roles?role=manager')->assertOk()->assertSee('Simpan hak akses Manager')->assertSee('Aksi khusus')->getContent();
        foreach (Permission::query()->get() as $p) {
            $this->assertSame(1, preg_match_all('/name="permissions\[\]" value="'.$p->id.'"/', $html), "{$p->slug} must appear exactly once");
            $this->assertStringNotContainsString($p->slug, strip_tags(substr($html, strpos($html, '<main'))), 'no permission code as visible text');
        }
        $all = collect(PermissionMatrix::build(Permission::query()->get()))->flatten(1);
        $this->assertArrayNotHasKey('Lainnya', PermissionMatrix::build(Permission::query()->get()), 'all 39 SQL permissions have a place in the table');
        $this->assertGreaterThanOrEqual(14, $all->count());
    }

    public function test_unknown_permission_lands_in_other(): void
    {
        $sections = PermissionMatrix::build(Permission::query()->get()->push((object) ['id' => 999, 'slug' => 'reports.extra', 'name' => 'Izin tambahan']));
        $this->assertSame('Izin tambahan', $sections['Lainnya'][0]['extra'][0][1]);
    }

    public function test_saving_ticks_for_a_role(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $member = Role::query()->where('slug', 'member')->firstOrFail();
        $ids = Permission::query()->whereIn('slug', ['dashboard.view', 'tasks.view', 'tasks.submit'])->pluck('id')->all();
        $this->actingAs($admin)->put("/settings/roles/{$member->id}", ['permissions' => $ids])->assertRedirect('/settings/roles?role=member');
        $this->assertEqualsCanonicalizing($ids, $member->permissions()->pluck('permissions.id')->all());
        $this->actingAs($admin)->get('/settings/roles?role=member')->assertOk()->assertSee('3 izin');
    }

    public function test_super_admin_row_is_locked_and_manager_only_reads(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $html = $this->actingAs($admin)->get('/settings/roles?role=super_admin')->assertOk()->assertSee('selalu memegang semua izin')->getContent();
        $this->assertStringNotContainsString('Simpan hak akses', $html);
        $this->assertSame(Permission::query()->count(), substr_count($html, ' checked disabled') + substr_count($html, 'checked="checked" disabled="disabled"'));
    }
}
