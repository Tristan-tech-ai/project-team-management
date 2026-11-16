<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Position;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** U5.1: a missing division or position is added from the member form (dialog, JSON) and can be used in the same save. */
class MemberOrgQuickAddTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_division_and_position_are_added_from_the_member_form(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin');
        $form = $this->actingAs($admin)->get(route('members.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-member-form', $form);
        $this->assertStringContainsString('data-org-add="division"', $form);
        $this->assertStringContainsString('data-org-add="position"', $form);

        $division = $this->actingAs($admin)->postJson(route('divisions.store'), ['company_id' => $c->id, 'name' => 'Tim Konten'])
            ->assertCreated()->assertJson(['name' => 'Tim Konten', 'company_id' => $c->id, 'message' => 'Divisi Tim Konten ditambahkan.'])->json();
        $position = $this->actingAs($admin)->postJson(route('positions.store'), ['company_id' => $c->id, 'name' => 'Koordinator'])
            ->assertCreated()->assertJson(['name' => 'Koordinator', 'company_id' => $c->id])->json();
        $this->assertTrue((bool) Division::query()->findOrFail($division['id'])->is_active, 'new records start active (U4)');
        $this->assertTrue((bool) Position::query()->findOrFail($position['id'])->is_active);

        $this->actingAs($admin)->postJson(route('positions.store'), ['company_id' => $c->id, 'name' => 'Koordinator'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        // The same save as the browser does after the dialog: the new division ticked with the new position.
        $this->actingAs($admin)->post(route('members.store'), ['name' => 'Rina Kusuma', 'email' => 'rina.kusuma@uji.test', 'role' => 'member', 'is_active' => 1,
            'companies' => [$c->id], 'divisions' => [$division['id'] => ['included' => 1, 'position_id' => $position['id']]]])->assertRedirect(route('members.index'));
        $userId = DB::table('users')->where('email', 'rina.kusuma@uji.test')->value('id');
        $this->assertSame($position['id'], (int) DB::table('division_members')->where('user_id', $userId)->where('division_id', $division['id'])->value('position_id'));
    }

    public function test_plain_forms_still_redirect_and_members_cannot_add(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->post(route('divisions.store'), ['company_id' => $c->id, 'name' => 'Tim Video', 'is_active' => 0])
            ->assertRedirect(route('divisions.index'));
        $this->assertFalse((bool) Division::query()->where('name', 'Tim Video')->value('is_active'), 'the switch on the full form still decides');

        $member = $this->user('member', $c);
        $this->actingAs($member)->postJson(route('divisions.store'), ['company_id' => $c->id, 'name' => 'Tim Liar'])->assertForbidden();
    }
}
