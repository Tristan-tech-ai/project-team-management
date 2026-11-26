<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** The project form saves "Hasil task boleh kosong" (table project_settings) on create and on edit. */
class ProjectSettingsTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_result_optional_is_chosen_on_create_and_changed_on_edit(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $this->actingAs($admin)->get('/projects/create')->assertOk()->assertSee('Hasil task boleh kosong')->assertSee('name="result_optional"', false);

        $this->actingAs($admin)->post('/projects', ['company_id' => $c->id, 'name' => 'Maintenance Server Klien', 'project_type' => 'ongoing',
            'pic_user_id' => $admin->id, 'result_optional' => '1'])->assertSessionHasNoErrors();
        $project = Project::query()->where('name', 'Maintenance Server Klien')->firstOrFail();
        $this->assertTrue($project->resultOptional());

        $this->actingAs($admin)->get("/projects/{$project->id}/edit")->assertOk()->assertSee('id="sw-result_optional" name="result_optional" value="1" checked', false);
        $this->actingAs($admin)->put("/projects/{$project->id}", ['name' => $project->name, 'pic_user_id' => $admin->id, 'result_optional' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($project->fresh()->resultOptional());
        $this->assertSame(1, ProjectSetting::query()->where('project_id', $project->id)->count(), 'one settings row per project');
    }
}
