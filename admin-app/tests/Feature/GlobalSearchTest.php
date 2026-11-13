<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Header search of prototypes 7.1-7.3: projects, tasks, and members, each limited to the user's data scope. */
class GlobalSearchTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_super_admin_finds_projects_tasks_and_members(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $manager = $this->user('manager', $c);
        $manager->update(['name' => 'Zebrina Manager']);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $p->update(['name' => 'Zebrina Campaign']);
        Task::query()->where('project_id', $p->id)->where('title', 'Analisis')->update(['title' => 'Zebrina Riset']);

        $html = $this->actingAs($admin)->get('/search?keyword=zebrina')->assertOk()->getContent();
        $this->assertStringContainsString('Zebrina Campaign', $html);
        $this->assertStringContainsString('Zebrina Riset', $html);
        $this->assertStringContainsString('Zebrina Manager', $html);
        $this->assertStringContainsString(route('members.edit', $manager), $html);
    }

    public function test_results_stay_inside_the_data_scope(): void
    {
        $own = $this->company();
        $other = $this->company('Company Lain');
        $manager = $this->user('manager', $own);
        $outsider = $this->user('manager', $other);
        $outsider->update(['name' => 'Quokka Outsider']);
        $foreign = $this->project($outsider, $outsider, $other, [], [], 'one_time', $this->template());
        $foreign->update(['name' => 'Quokka Foreign Project']);
        Task::query()->where('project_id', $foreign->id)->where('title', 'Rilis')->update(['title' => 'Quokka Foreign Task']);

        $html = $this->actingAs($manager)->get('/search?keyword=quokka')->assertOk()->getContent();
        $this->assertStringNotContainsString('Quokka Foreign Project', $html);
        $this->assertStringNotContainsString('Quokka Foreign Task', $html);
        $this->assertStringNotContainsString('Quokka Outsider', $html);
    }

    public function test_member_finds_teammates_by_name_only_and_never_sees_emails(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $teammate = $this->user('member', $c);
        $teammate->update(['name' => 'Kasuari Teammate', 'email' => 'kasuari.teammate@example.test']);
        $this->project($manager, $manager, $c, [$member, $teammate]);

        $byName = $this->actingAs($member)->get('/search?keyword=kasuari')->assertOk()->getContent();
        $this->assertStringContainsString('Kasuari Teammate', $byName);
        $this->assertStringNotContainsString('kasuari.teammate@example.test', $byName);
        $byEmail = $this->actingAs($member)->get('/search?keyword=teammate%40example')->assertOk()->getContent();
        $this->assertStringNotContainsString('Kasuari Teammate', $byEmail);
    }

    public function test_short_keywords_only_show_the_hint(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->get('/search?keyword=a')->assertOk()->assertSee('Ketik minimal 2 huruf');
        $this->get('/search')->assertOk()->assertSee('Cari project, task, atau anggota', false);
    }
}
