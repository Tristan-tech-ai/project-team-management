<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** LV-01: Team Members (user + role + company + division + position) and friendly error pages (LV-05). */
class MemberErrorTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_new_member_with_role_company_division_position_can_log_in(): void
    {
        $c = $this->company();
        $dev = $this->division($c, 'Development');
        $creative = $this->division($c, 'Creative');
        $positions = Position::create(['company_id' => $c->id, 'name' => 'Web Developer Uji', 'is_active' => 1]);
        $admin = $this->user('super_admin', $c);
        $this->actingAs($admin)->post('/master/members', [
            'name' => 'Sinta Pratama', 'email' => 'sinta.uji@example.test', 'password' => 'StrongPass12345', 'role' => 'member', 'is_active' => 1,
            'companies' => [$c->id], 'divisions' => [$dev->id => ['included' => 1, 'position_id' => $positions->id], $creative->id => ['included' => 1]],
        ])->assertRedirect('/master/members');
        $u = User::query()->where('email', 'sinta.uji@example.test')->firstOrFail();
        $this->assertSame(['member'], $u->roleSlugs());
        $this->assertSame(2, DB::table('division_members')->where('user_id', $u->id)->where('is_active', 1)->count());
        $this->assertSame($positions->id, DB::table('division_members')->where('user_id', $u->id)->where('division_id', $dev->id)->value('position_id'));
        $this->post('/logout');
        $this->post('/login', ['email' => 'sinta.uji@example.test', 'password' => 'StrongPass12345'])->assertRedirect(route('home'));
    }

    public function test_divisions_from_other_companies_are_rejected(): void
    {
        $c = $this->company();
        $other = $this->division($this->company('Lain'), 'Asing');
        $admin = $this->user('super_admin', $c);
        $this->actingAs($admin)->post('/master/members', ['name' => 'X', 'email' => 'x.uji@example.test', 'password' => 'StrongPass12345', 'role' => 'member',
            'companies' => [$c->id], 'divisions' => [$other->id => ['included' => 1]]])->assertSessionHasErrors('divisions');
        $this->assertFalse(User::query()->where('email', 'x.uji@example.test')->exists());
    }

    public function test_error_pages_are_friendly(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->get('/projects/99999999')->assertNotFound()->assertSee('Halaman tidak ditemukan');
        $member = $this->user('member', $this->company());
        $this->actingAs($member)->get('/settings/roles')->assertForbidden()->assertSee('bukan untuk role-mu');
    }
}
