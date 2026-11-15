<?php

namespace Tests\Feature;

use App\Services\MenuService;
use App\Support\MenuIcons;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** U2: every sidebar destination is one click; a parent with one submenu is a direct link; every submenu has its own icon. */
class SidebarTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_single_submenu_parents_are_direct_links(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $html = $this->actingAs($admin)->get('/projects')->assertOk()->getContent();
        $menu = app(MenuService::class)->for($admin);
        foreach ($menu as $parent) {
            if (count($parent['children']) === 1) {
                $url = preg_quote($parent['children'][0]['url'], '#');
                $this->assertMatchesRegularExpression('#<a class="side-link side-single" href="'.$url.'"#', $html, "{$parent['title']} must be one click");
            } else {
                foreach ($parent['children'] as $child) {
                    $this->assertStringContainsString('href="'.$child['url'].'"', $html, "{$child['title']} must be visible without opening a group");
                }
            }
        }
        $this->assertStringNotContainsString('<details', substr($html, 0, strpos($html, '<main')), 'no fold-out menus that need two clicks');
    }

    public function test_every_submenu_has_its_own_icon(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $children = collect(app(MenuService::class)->for($admin))->pluck('children')->flatten(1);
        $this->assertGreaterThanOrEqual(20, $children->count());
        $icons = $children->pluck('icon');
        $this->assertSame($icons->count(), $icons->unique()->count(), 'two destinations must not share an icon');
        foreach ($icons as $icon) {
            $this->assertFileExists(resource_path("icons/{$icon}.svg"));
        }
        $this->assertSame('folder', MenuIcons::forRoute('unknown.route'));
    }

    public function test_detail_pages_highlight_their_list_menu(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $menu = app(MenuService::class)->for($admin);
        $this->assertSame('projects.index', MenuService::activeRoute($menu, 'projects.show'));
        $this->assertSame('tasks.index', MenuService::activeRoute($menu, 'tasks.show'));
        $this->assertSame('tasks.mine', MenuService::activeRoute($menu, 'tasks.mine'));
        $this->assertSame('templates.projects.index', MenuService::activeRoute($menu, 'templates.projects.edit'));
        $this->assertSame('settings.general', MenuService::activeRoute($menu, 'settings.audit'));
        $this->assertNull(MenuService::activeRoute($menu, 'account.edit'));
    }

    /** I7: the header bell loads the same to-do list as JSON after the page shows; guests are sent to the login page. */
    public function test_bell_loads_the_todo_list(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->get('/projects')->assertOk()->assertSee('data-todo-bell', false);
        $this->actingAs($admin)->getJson('/todo')->assertOk()->assertJsonStructure(['items']);
        $this->post('/logout');
        $this->getJson('/todo')->assertUnauthorized();
    }

    /** I8: the Super Admin sees first steps until they are done; other roles never do. */
    public function test_first_steps_for_super_admin_only(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        \App\Models\Company::query()->update(['email' => null, 'phone' => null]);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Langkah pertama')->assertSee('Lengkapi data perusahaan');
        $this->actingAs($this->user('manager', $c))->get('/dashboard')->assertOk()->assertDontSee('Langkah pertama');
    }
}
