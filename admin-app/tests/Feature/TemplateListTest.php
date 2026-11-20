<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** U4: the template lists show their status (Aktif/Nonaktif) and the edit action, using the same columns the page sends. */
class TemplateListTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    /** DataTables request built from the data-table-columns attribute of the page itself. */
    private function tableRequest(string $page, string $feed): array
    {
        $admin = $this->user('super_admin', $this->company());
        $html = $this->actingAs($admin)->get($page)->assertOk()->getContent();
        preg_match('/data-table-columns="([^"]+)"/', $html, $m);
        $columns = json_decode(html_entity_decode($m[1]), true);
        $query = ['draw' => 1, 'start' => 0, 'length' => 25, 'search' => ['value' => '', 'regex' => 'false'], 'order' => [['column' => 0, 'dir' => 'asc']]];
        foreach ($columns as $i => $c) {
            $query['columns'][$i] = ['data' => $c['data'], 'name' => $c['name'] ?? $c['data'], 'searchable' => ($c['searchable'] ?? true) ? 'true' : 'false',
                'orderable' => ($c['orderable'] ?? true) ? 'true' : 'false', 'search' => ['value' => '', 'regex' => 'false']];
        }

        return $this->actingAs($admin)->getJson($feed.'?'.http_build_query($query))->assertOk()->json('data');
    }

    public function test_project_templates_show_status_and_edit_action(): void
    {
        $this->template();
        $rows = $this->tableRequest('/templates/projects', '/templates/projects/data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertContains($row['status'], ['Aktif', 'Nonaktif'], json_encode($row));
            $this->assertNotEmpty($row['actions'], 'Super Admin can edit every template');
        }
    }

    public function test_task_templates_show_status_and_edit_action(): void
    {
        \App\Models\TaskTemplate::create(['name' => 'Desain feed', 'task_type' => 'design', 'is_active' => true]);
        $rows = $this->tableRequest('/templates/tasks', '/templates/tasks/data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertContains($row['status'], ['Aktif', 'Nonaktif'], json_encode($row));
            $this->assertNotEmpty($row['actions']);
        }
    }

    /** Regression: the server sent HTML-escaped text and the browser escaped it again, so "Testing & Deployment" read "Testing &amp; Deployment".
     *  Text now leaves the server as plain text; resources/js/table.js puts every value in the page as text. */
    public function test_table_text_is_sent_once_unescaped(): void
    {
        $template = $this->template();
        $template->tasks()->create(['title' => 'Testing & Deployment <QA>', 'task_type' => 'general', 'sort_order' => 9]);
        $row = collect($this->tableRequest('/templates/projects', '/templates/projects/data'))->firstWhere('id', $template->id);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Testing & Deployment <QA>', $row['content']);
        $this->assertStringNotContainsString('&amp;', $row['content']);
        $this->assertStringContainsString('DataTable.render.text()', file_get_contents(resource_path('js/table.js')));
    }

    /** Regression: DataTables 2 treats a data-columns attribute as its own option and drops the render functions (Status and actions went blank). */
    public function test_no_table_uses_the_datatables_option_attribute(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($views as $file) {
            $this->assertStringNotContainsString('data-columns=', file_get_contents($file->getPathname()), $file->getPathname());
        }
    }

    /** U9: "Pakai" opens Buat project with the template chosen and its project type. */
    public function test_use_template_opens_project_form_with_template(): void
    {
        $template = $this->template('ongoing');
        $admin = $this->user('super_admin', $this->company());
        $rows = $this->tableRequest('/templates/projects', '/templates/projects/data');
        $row = collect($rows)->firstWhere('name', $template->name);
        $this->assertSame('Pakai', $row['actions'][0]['label']);
        $html = $this->actingAs($admin)->get($row['actions'][0]['url'])->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<option value="'.$template->id.'"\s+selected#', $html);
        $this->assertMatchesRegularExpression('#<option value="ongoing"\s+selected#', $html);
    }
}
