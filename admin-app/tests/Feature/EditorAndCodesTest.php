<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Support\RichText;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/**
 * Pembina feedback 17 Nov: the code field is filled in automatically and can still be changed, the project description has
 * simple formatting (what you see is what you get), and the Dashboard parts can be chosen with a checklist.
 */
class EditorAndCodesTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_description_keeps_only_simple_formatting(): void
    {
        $dirty = '<p onclick="steal()"><b>Tebal</b> <script>alert(1)</script><a href="javascript:x()">tautan</a> <span style="color:red">merah</span></p>'
            .'<ul><li><i>satu</i></li><li><u>dua</u></li></ul><img src=x onerror=alert(1)><div>baris</div><div><br></div>';
        $this->assertSame('<p><strong>Tebal</strong> tautan merah</p><ul><li><em>satu</em></li><li><u>dua</u></li></ul><p>baris</p>', RichText::clean($dirty));
        $this->assertSame('<p>Tom &amp; Jerry &lt;3</p>', RichText::clean('<p>Tom &amp; Jerry &lt;3</p>'));
        // What Chrome's editor sends for "Target: bold, Enter, bullet list": the list is not left inside a paragraph.
        $this->assertSame('Target: <strong>500 pendaftar</strong><ul><li>Instagram</li><li>TikTok</li></ul>',
            RichText::clean('Target: <b>500 pendaftar</b><div><ul><li>Instagram</li><li>TikTok</li></ul></div>'));
        $this->assertNull(RichText::clean('<p><br></p>'), 'an editor left empty saves nothing');
        $this->assertNull(RichText::clean('   '));
        $this->assertSame('teks biasa', RichText::clean('teks biasa'), 'without the editor the textarea still works');
        // Older descriptions are plain text: shown escaped with their line breaks.
        $this->assertSame("baris 1<br>\nharga &lt; 5", RichText::toHtml("baris 1\nharga < 5"));
        $this->assertSame('A b c', RichText::plain('<p><strong>A</strong></p><ul><li>b</li><li>c</li></ul>'));
    }

    public function test_project_description_is_saved_clean_and_shown_formatted(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $this->actingAs($manager)->get(route('projects.create'))->assertOk()
            ->assertSee('data-rich-text', false)->assertSee('data-command="bold"', false)->assertSee('data-code-url=', false);

        $this->actingAs($manager)->post(route('projects.store'), [
            'company_id' => $c->id, 'name' => 'Promo Akhir Tahun', 'code' => 'PROMO-AKHIR-TAHUN', 'code_auto' => '1', 'project_type' => 'one_time',
            'status' => 'active', 'priority' => 'normal', 'pic_user_id' => $manager->id,
            'description' => '<p><b>Target</b>: 500 pendaftar</p><ul><li>Instagram</li></ul><script>alert(1)</script>',
        ])->assertRedirect();
        $project = Project::query()->where('name', 'Promo Akhir Tahun')->firstOrFail();
        $this->assertSame('<p><strong>Target</strong>: 500 pendaftar</p><ul><li>Instagram</li></ul>', $project->description);
        $this->assertSame('PROMO-AKHIR-TAHUN', $project->code, 'a project code is made from its name');

        $page = $this->actingAs($manager)->get(route('projects.show', $project))->assertOk()->getContent();
        $this->assertStringContainsString('<strong>Target</strong>: 500 pendaftar', $page);
        $this->assertStringNotContainsString('alert(1)', $page);

        // The same name again: the automatic code is made unique, an own code is kept as typed.
        $this->actingAs($manager)->post(route('projects.store'), ['company_id' => $c->id, 'name' => 'Promo Akhir Tahun', 'code' => 'PROMO-AKHIR-TAHUN',
            'code_auto' => '1', 'project_type' => 'one_time', 'pic_user_id' => $manager->id])->assertRedirect();
        $this->assertSame(['PROMO-AKHIR-TAHUN', 'PROMO-AKHIR-TAHUN-2'], Project::query()->where('name', 'Promo Akhir Tahun')->orderBy('id')->pluck('code')->all());
        $this->actingAs($manager)->post(route('projects.store'), ['company_id' => $c->id, 'name' => 'Promo Akhir Tahun', 'code' => 'PROMO-AKHIR-TAHUN',
            'code_auto' => '0', 'project_type' => 'one_time', 'pic_user_id' => $manager->id])->assertSessionHasErrors('code');
    }

    public function test_code_suggestion_follows_the_name_and_the_permission(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        Client::create(['code' => 'KOPI-SENJA', 'name' => 'Kopi Senja', 'is_active' => 1]);
        $this->actingAs($admin)->getJson(route('codes.suggest', ['for' => 'clients', 'name' => 'Kopi Senja']))->assertOk()->assertExactJson(['code' => 'KOPI-SENJA-2']);
        $this->actingAs($admin)->getJson(route('codes.suggest', ['for' => 'companies', 'name' => 'PT Kreasi Energi Kita']))->assertExactJson(['code' => 'KREASI-ENERGI-KITA']);
        $this->actingAs($admin)->getJson(route('codes.suggest', ['for' => 'clients', 'name' => '']))->assertExactJson(['code' => '']);
        $this->actingAs($admin)->get(route('clients.create'))->assertOk()->assertSee('Terisi otomatis dari nama. Boleh diganti.');

        // The field showed KOPI-SENJA-2; saving with code_auto=1 keeps it, an own code typed over it is kept as well.
        $this->actingAs($admin)->post('/master/clients', ['name' => 'Kopi Senja', 'code' => 'KOPI-SENJA-2', 'code_auto' => '1'])->assertRedirect();
        $this->actingAs($admin)->post('/master/clients', ['name' => 'Kopi Senja', 'code' => 'KS-BALI', 'code_auto' => '0'])->assertRedirect();
        $this->assertSame(['KOPI-SENJA', 'KOPI-SENJA-2', 'KS-BALI'], Client::query()->where('name', 'Kopi Senja')->orderBy('id')->pluck('code')->all());

        $manager = $this->user('manager', $c);
        $this->actingAs($manager)->getJson(route('codes.suggest', ['for' => 'clients', 'name' => 'Kopi']))->assertForbidden();
        $this->actingAs($manager)->getJson(route('codes.suggest', ['for' => 'projects', 'name' => 'Kopi']))->assertOk()->assertJsonStructure(['code']);
        $member = $this->user('member', $c);
        $this->actingAs($member)->getJson(route('codes.suggest', ['for' => 'projects', 'name' => 'Kopi']))->assertForbidden();
    }

    public function test_whole_kanban_card_is_the_drag_area(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $project = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $html = $this->actingAs($manager)->get(route('projects.board', $project))->assertOk()->getContent();
        // The card itself is marked movable (resources/js/kanban.js drags it from anywhere); its title link is not natively draggable.
        $this->assertMatchesRegularExpression('/<article class="task-card" data-task-id="\d+" data-status="backlog"\s+data-movable/', $html);
        $this->assertStringContainsString('data-task-panel draggable="false"', $html);
    }

    public function test_dashboard_parts_can_be_chosen(): void
    {
        $c = $this->company();
        $html = $this->actingAs($this->user('manager', $c))->get(route('dashboard.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Atur tampilan', $html);
        foreach (['stats', 'todo', 'overdue', 'projects', 'activities'] as $part) {
            $this->assertStringContainsString('data-widget="'.$part.'"', $html, "part $part can be hidden");
            $this->assertStringContainsString('value="'.$part.'" data-widget-toggle', $html, "part $part is in the checklist");
        }
        // A refreshed region sits inside its part, so hiding survives the in-place refresh after the side panel.
        $this->assertMatchesRegularExpression('/data-widget="stats"><div class="row g-3 mb-3" data-refresh-region="stats">/', $html);
    }
}
