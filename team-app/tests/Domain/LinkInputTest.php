<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\ProjectTeamService;
use App\Support\Links;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** A result link may be typed without https:// (PKL supervisor, 26 Nov 2026); the app adds it, a single word is still refused. */
class LinkInputTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_normalize_adds_https_only_when_the_scheme_is_missing(): void
    {
        $this->assertSame('https://instagram.com/p/Dc5Z7HrtBOt/', Links::normalize(' instagram.com/p/Dc5Z7HrtBOt/ '));
        $this->assertSame('http://contoh.id/a', Links::normalize('http://contoh.id/a'));
        $this->assertSame('https://drive.google.com/x', Links::normalize('https://drive.google.com/x'));
        $this->assertSame('https://figma.com/f', Links::normalize('//figma.com/f'));
        $this->assertNull(Links::normalize('   '));
        $this->assertNull(Links::normalize(null));
    }

    public function test_member_submits_a_link_without_https(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($t, $member->id, [$member->id], $manager);
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk();

        $this->actingAs($member)->post("/tasks/{$t->id}/submissions", ['external_url' => 'abc', 'submission_note' => 'Coba'])
            ->assertSessionHasErrors(['external_url' => Links::MESSAGE]);
        $this->assertSame(0, TaskAttachment::query()->where('task_id', $t->id)->count(), 'a single word is not a link');

        $this->actingAs($member)->post("/tasks/{$t->id}/submissions", ['external_url' => 'instagram.com/p/Dc5Z7HrtBOt/', 'submission_note' => 'Sudah tayang'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://instagram.com/p/Dc5Z7HrtBOt/', TaskAttachment::query()->where('task_id', $t->id)->value('external_url'));
    }

    public function test_link_field_accepts_text_without_scheme_in_the_browser(): void
    {
        $html = (string) $this->withViewErrors([])->blade('<x-field name="external_url" label="Tautan hasil" type="link"/>');
        $this->assertStringContainsString('type="text"', $html, 'type=url makes the browser refuse instagram.com/...');
        $this->assertStringContainsString('inputmode="url"', $html, 'phones still show the keyboard for links');
    }
}
