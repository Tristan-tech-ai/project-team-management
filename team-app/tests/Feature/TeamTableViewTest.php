<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\ProjectTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Technical Guide 2.1 and 6.1: the Team App uses cards on phones and a server-side Yajra table for large lists on desktop. */
class TeamTableViewTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_my_tasks_has_yajra_table_view_with_own_tasks_only(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $b = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a, $b], [], 'one_time', $this->template());
        $ownedByA = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($ownedByA, $a->id, [$a->id], $manager);

        $card = $this->actingAs($a)->get('/tasks')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-table=', $card);
        $this->assertStringContainsString('view=table', $card);
        $table = $this->actingAs($a)->get('/tasks?view=table')->assertOk()->getContent();
        $this->assertStringContainsString('data-table="'.route('tasks.data').'"', $table);
        $this->assertMatchesRegularExpression('/<div class="d-lg-none">/', $table);

        $q = http_build_query(['draw' => 1, 'start' => 0, 'length' => 25, 'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $r = $this->actingAs($a)->getJson('/tasks/data?'.$q)->assertOk()->json();
        $this->assertSame(1, $r['recordsTotal']);
        $this->assertSame('Desain', $r['data'][0]['title']);
        $this->assertSame(['Mulai', 'Buka'], array_column($r['data'][0]['actions'], 'label'));
        $this->assertSame(0, $this->actingAs($b)->getJson('/tasks/data?'.$q)->assertOk()->json('recordsTotal'));
    }
}
