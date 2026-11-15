<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** U6 Menu settings: order from drag and drop, moving a page to another group, adding and removing groups, no technical words on screen. */
class MenuSettingsTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    /** The form the page sends: every row with its current values. */
    private function payload(): array
    {
        $menu = [];
        foreach (MenuItem::query()->get() as $m) {
            $menu[$m->id] = ['title' => $m->title, 'icon' => $m->icon, 'sort_order' => $m->sort_order, 'is_active' => $m->is_active ? '1' : '0'];
            if ($m->parent_id) {
                $menu[$m->id] += ['parent_id' => $m->parent_id, 'permission_id' => $m->permission_id];
            }
        }

        return $menu;
    }

    public function test_page_has_no_route_names_or_permission_codes(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $html = $this->actingAs($admin)->get('/settings/menus')->assertOk()->assertSee('Simpan pengaturan')->assertSee('data-sortable-items', false)->getContent();
        $main = substr($html, strpos($html, '<main'));
        foreach (['dashboard.index', 'projects.index', 'settings.menus.manage', 'tasks.view', 'Route', 'route'] as $technical) {
            $this->assertStringNotContainsString('>'.$technical, $main, "no technical word '{$technical}' as visible text");
        }
        $this->assertStringContainsString('Lihat Project', $main, 'permissions are shown by their human name');
    }

    public function test_reorder_and_move_a_page_to_another_group(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $calendar = MenuItem::query()->where('slug', 'calendar')->firstOrFail();
        $tasks = MenuItem::query()->where('slug', 'tasks')->firstOrFail();
        $review = MenuItem::query()->where('slug', 'tasks-review')->firstOrFail();
        $menu = $this->payload();
        $menu[$calendar->id]['sort_order'] = $tasks->sort_order - 5;
        $menu[$review->id]['parent_id'] = MenuItem::query()->where('slug', 'dashboard')->value('id');
        $menu[$review->id]['title'] = 'Perlu direview';
        $this->actingAs($admin)->put('/settings/menus', ['menu' => $menu])->assertRedirect()->assertSessionHas('ok');

        $sidebar = collect(app(MenuService::class)->for($admin->fresh()));
        $this->assertLessThan($sidebar->search(fn ($p) => $p['slug'] === 'tasks'), $sidebar->search(fn ($p) => $p['slug'] === 'calendar'));
        $dashboardChildren = collect($sidebar->firstWhere('slug', 'dashboard')['children'])->pluck('title')->all();
        $this->assertContains('Perlu direview', $dashboardChildren);
        $this->assertSame('tasks.review', MenuItem::query()->find($review->id)->route_name, 'moving never changes the page address');
    }

    public function test_a_page_cannot_become_a_group_or_join_a_page(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $review = MenuItem::query()->where('slug', 'tasks-review')->firstOrFail();
        $mine = MenuItem::query()->where('slug', 'tasks-mine')->firstOrFail();
        $menu = $this->payload();
        $menu[$review->id]['parent_id'] = $mine->id;
        $this->actingAs($admin)->put('/settings/menus', ['menu' => $menu])->assertSessionHasErrors("menu.{$review->id}.parent_id");
        $this->assertSame($review->parent_id, $review->fresh()->parent_id);
    }

    public function test_add_and_remove_an_empty_group(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->post('/settings/menus/groups', ['title' => 'Keuangan'])->assertRedirect()->assertSessionHas('ok');
        $group = MenuItem::query()->where('title', 'Keuangan')->whereNull('parent_id')->firstOrFail();
        $this->assertSame('keuangan', $group->slug);
        $this->assertNotContains('Keuangan', collect(app(MenuService::class)->for($admin))->pluck('title'), 'an empty group stays out of the sidebar');

        $tasks = MenuItem::query()->where('slug', 'tasks')->firstOrFail();
        $this->actingAs($admin)->delete("/settings/menus/groups/{$tasks->id}")->assertSessionHas('error');
        $this->assertNotNull($tasks->fresh(), 'a group with pages is never deleted');
        $this->actingAs($admin)->delete("/settings/menus/groups/{$group->id}")->assertSessionHas('ok');
        $this->assertNull($group->fresh());
    }

    public function test_manager_cannot_change_menus(): void
    {
        $manager = $this->user('manager', $this->company());
        $this->actingAs($manager)->get('/settings/menus')->assertForbidden();
        $this->actingAs($manager)->put('/settings/menus', ['menu' => []])->assertForbidden();
        $this->actingAs($manager)->post('/settings/menus/groups', ['title' => 'X'])->assertForbidden();
    }

    /** U8: everyday titles replace the technical SQL titles on install, but never a title the admin renamed. */
    public function test_label_seeder_only_renames_untouched_titles(): void
    {
        MenuItem::query()->where('slug', 'system-settings')->update(['title' => 'System Settings']);
        MenuItem::query()->where('slug', 'master-data')->update(['title' => 'Data Perusahaan']);
        $this->seed(\Database\Seeders\MenuLabelSeeder::class);
        $this->assertSame('Pengaturan', MenuItem::query()->where('slug', 'system-settings')->value('title'));
        $this->assertSame('Data Perusahaan', MenuItem::query()->where('slug', 'master-data')->value('title'), 'a renamed title is kept');
        $this->assertSame(9, MenuItem::query()->whereNull('parent_id')->whereIn('slug', ['dashboard', 'projects', 'tasks', 'calendar', 'activities', 'templates', 'management', 'master-data', 'system-settings'])->count());
    }
}
