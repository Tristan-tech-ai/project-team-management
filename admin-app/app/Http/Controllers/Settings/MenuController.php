<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Menu Management: order, icon, name, active/inactive, permission (Technical Guide 5.1).
 * route_name deliberately cannot be changed from the screen (section 01: the menu editor does not change PHP routes; 4.2: routes must be registered).
 */
class MenuController extends Controller
{
    public function index(): View
    {
        $all = MenuItem::query()->with('permission:id,slug,name')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.settings.menus', [
            'parent' => $all->whereNull('parent_id')->values(),
            'children' => $all->whereNotNull('parent_id')->groupBy('parent_id'),
            'permissions' => Permission::query()->orderBy('module')->orderBy('id')->get(['id', 'slug', 'name']),
            'icon' => self::availableIcons(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'menu' => ['required', 'array'],
            'menu.*.title' => ['required', 'string', 'max:150'],
            'menu.*.icon' => ['nullable', Rule::in(self::availableIcons())],
            'menu.*.sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'menu.*.is_active' => ['boolean'],
            'menu.*.permission_id' => ['nullable', 'integer', 'exists:permissions,id'],
        ]);
        DB::transaction(function () use ($data) {
            foreach ($data['menu'] as $id => $content) {
                $m = MenuItem::query()->find((int) $id);
                if (! $m) {
                    continue;
                }
                $update = ['title' => $content['title'], 'sort_order' => (int) $content['sort_order'], 'is_active' => (bool) ($content['is_active'] ?? false)];
                if ($m->parent_id === null) {
                    $update['icon'] = $content['icon'] ?? $m->icon;
                } else {
                    $update['permission_id'] = $content['permission_id'] ?? null;
                }
                $m->update($update);
            }
        });

        return back()->with('ok', 'Menu tersimpan. Menu tetap hanya navigasi; hak akses diperiksa di server.');
    }

    /** Icons available as local SVG files (Lucide, ISC). */
    public static function availableIcons(): array
    {
        return collect(glob(resource_path('icons/*.svg')) ?: [])->map(fn ($f) => basename($f, '.svg'))->sort()->values()->all();
    }
}
