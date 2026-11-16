<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** I9 command palette: actions come from the user's own menu (never more than the role allows); search answers as JSON in the same scope. */
class CommandPaletteTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    /** @return list<array{label: string, url: string, group: string}> */
    private function actions(string $html): array
    {
        $this->assertMatchesRegularExpression('/id="command-palette"/', $html);
        preg_match('/data-actions="([^"]*)"/', $html, $m);

        return json_decode(html_entity_decode($m[1]), true);
    }

    public function test_actions_follow_the_menu_of_the_role(): void
    {
        $c = $this->company();
        $admin = $this->actions($this->actingAs($this->user('super_admin'))->get(route('dashboard.index'))->assertOk()->getContent());
        $member = $this->actions($this->actingAs($this->user('member', $c))->get(route('dashboard.index'))->assertOk()->getContent());

        // Checked by address, not by title: menu titles depend on whether MenuLabelSeeder ran on this database.
        $urls = fn (array $a) => array_column($a, 'url');
        $this->assertContains(route('settings.menus.index'), $urls($admin));
        $this->assertContains(route('projects.create'), $urls($admin));
        $this->assertNotContains(route('settings.menus.index'), $urls($member), 'a member never gets settings in the palette');
        $this->assertNotContains(route('projects.create'), $urls($member));
        $dashboard = MenuItem::query()->where('route_name', 'dashboard.index')->firstOrFail();
        $group = MenuItem::query()->findOrFail($dashboard->parent_id);
        if (MenuItem::query()->where('parent_id', $group->id)->where('is_active', 1)->count() === 1) {
            $this->assertSame($group->title, collect($member)->firstWhere('url', route('dashboard.index'))['label'], 'a one-item group is named like in the sidebar');
        }
        foreach ($member as $a) {
            $path = parse_url($a['url'], PHP_URL_PATH) ?: '/';
            $this->actingAs($this->user('member', $c))->get($path)->assertOk();
        }
    }

    public function test_search_answers_json_within_scope(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $project = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $project->update(['name' => 'Kampanye Ramadan Toko Sinar']);
        $other = $this->user('manager', $this->company('Company Lain'));
        $this->project($other, $other, $other->companies()->first() ?? $this->company('Company Lain 2'), [], [], 'one_time', $this->template())
            ->update(['name' => 'Kampanye Ramadan Rahasia']);

        $json = $this->actingAs($manager)->getJson(route('search', ['keyword' => 'Kampanye Ramadan']))->assertOk()->json();
        $this->assertSame(['Kampanye Ramadan Toko Sinar'], array_column($json['projects'], 'label'), 'only projects in scope');
        // Words are matched one by one, in any order (people rarely type a name exactly).
        $words = $this->actingAs($manager)->getJson(route('search', ['keyword' => 'sinar kampanye']))->json('projects');
        $this->assertSame(['Kampanye Ramadan Toko Sinar'], array_column($words, 'label'));
        $this->assertSame([], $this->actingAs($manager)->getJson(route('search', ['keyword' => 'kampanye rahasia']))->json('projects'), 'scope still applies');

        $task = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->firstOrFail();
        $tasks = $this->actingAs($manager)->getJson(route('search', ['keyword' => $task->title]))->json('tasks');
        $hit = collect($tasks)->firstWhere('url', route('tasks.show', $task));
        $this->assertNotNull($hit);
        $this->assertTrue($hit['panel'], 'a task opens in the side panel');

        $people = $this->actingAs($manager)->getJson(route('search', ['keyword' => $member->name]))->json('members');
        $this->assertSame(route('tasks.index', ['assignee' => $member->id]), $people[0]['url'], 'without member management a person opens their tasks');

        $this->actingAs($manager)->get(route('search', ['keyword' => 'Kampanye']))->assertOk()->assertSee('Kampanye Ramadan Toko Sinar');
    }
}
