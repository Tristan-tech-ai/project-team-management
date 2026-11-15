<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Support\MenuIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Menu Management (Technical Guide 5.1: order, icon, name, active/inactive, permission), arranged by drag and drop (U6).
 * Groups (parent menus) and their pages can be reordered, a page can move to another group, groups can be added and empty
 * groups removed. route_name is never shown or changed here (guide 01 and 4.2: the menu editor does not create or change routes).
 */
class MenuController extends Controller
{
    public function index(): View
    {
        $all = MenuItem::query()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.settings.menus', [
            'groups' => $all->whereNull('parent_id')->values(),
            'children' => $all->whereNotNull('parent_id')->groupBy('parent_id'),
            'permissions' => Permission::query()->orderBy('id')->pluck('name', 'id'),
            'icons' => self::availableIcons(),
            'defaultIcon' => fn (MenuItem $m) => $m->icon ?: MenuIcons::forRoute($m->route_name),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu' => ['required', 'array'],
            'menu.*.title' => ['required', 'string', 'max:120'],
            'menu.*.icon' => ['nullable', Rule::in(self::availableIcons())],
            'menu.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'menu.*.is_active' => ['boolean'],
            'menu.*.permission_id' => ['nullable', 'integer', 'exists:permissions,id'],
            'menu.*.parent_id' => ['nullable', 'integer'],
        ], [], ['menu.*.title' => 'nama menu']);
        $items = MenuItem::query()->whereIn('id', array_keys($data['menu']))->get()->keyBy('id');
        $groupIds = MenuItem::query()->whereNull('parent_id')->pluck('id')->all();

        DB::transaction(function () use ($data, $items, $groupIds) {
            foreach ($data['menu'] as $id => $content) {
                $m = $items->get((int) $id);
                if (! $m) {
                    continue;
                }
                $update = ['title' => $content['title'], 'sort_order' => (int) $content['sort_order'], 'is_active' => (bool) ($content['is_active'] ?? false)];
                if (array_key_exists('icon', $content)) {
                    $update['icon'] = $content['icon'] ?: null;
                }
                if ($m->parent_id !== null) {
                    // A page can move to another group, but never becomes a group itself and never loses its route.
                    $parent = (int) ($content['parent_id'] ?? $m->parent_id);
                    if (! in_array($parent, $groupIds, true)) {
                        throw ValidationException::withMessages(["menu.$id.parent_id" => "Kelompok untuk menu {$m->title} tidak ditemukan."]);
                    }
                    $update['parent_id'] = $parent;
                    $update['permission_id'] = $content['permission_id'] ?? null;
                }
                $m->update($update);
            }
        });

        return back()->with('ok', 'Pengaturan menu tersimpan. Sidebar sudah memakai susunan baru.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']], [], ['title' => 'nama kelompok']);
        $slug = Str::slug($data['title']) ?: 'group';
        $unique = $slug;
        for ($n = 2; MenuItem::query()->where('slug', $unique)->exists(); $n++) {
            $unique = "{$slug}-{$n}";
        }
        MenuItem::create(['slug' => $unique, 'title' => $data['title'], 'icon' => 'folder', 'is_active' => true,
            'sort_order' => (int) MenuItem::query()->whereNull('parent_id')->max('sort_order') + 10]);

        return back()->with('ok', "Kelompok {$data['title']} ditambahkan. Seret menu ke dalamnya; kelompok kosong tidak tampil di sidebar.");
    }

    public function destroyGroup(MenuItem $menuItem): RedirectResponse
    {
        abort_unless($menuItem->parent_id === null, 404);
        if ($menuItem->children()->exists()) {
            return back()->with('error', "Kelompok {$menuItem->title} masih berisi menu. Pindahkan menunya dulu.");
        }
        $menuItem->delete();

        return back()->with('ok', "Kelompok {$menuItem->title} dihapus.");
    }

    /** Icons available as local SVG files (Lucide, ISC). */
    public static function availableIcons(): array
    {
        return collect(glob(resource_path('icons/*.svg')) ?: [])->map(fn ($f) => basename($f, '.svg'))->sort()->values()->all();
    }
}
